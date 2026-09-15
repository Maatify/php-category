<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\ContentField\Api\Contract\ContentFieldApiInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Query\Infrastructure\PdoCategoryQueryReader;
use Maatify\Category\ImageAssignment\Query\Infrastructure\PdoCategoryImageAssignmentQueryReader;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use PDO;

final class CategoryTransactionIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testCategoryMutationRunsStandaloneWithThePublishedPersistenceRunner(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection);

        $categoryId = $service->create(new CreateCategoryCommand('standalone-transaction-category'));

        self::assertNotNull((new PdoCategoryQueryReader($connection, new FixedCategoryClock()))->findById($categoryId));
        self::assertFalse($connection->inTransaction());
    }

    public function testHostRollbackCancelsCategoryMutationAndRemainsTheTransactionOwner(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection);
        $reader = new PdoCategoryQueryReader($connection, new FixedCategoryClock());

        $connection->beginTransaction();
        try {
            $categoryId = $service->create(new CreateCategoryCommand('host-rollback-category'));

            self::assertTrue($connection->inTransaction());
            self::assertSame($categoryId, $reader->findByCode('host-rollback-category')?->id);
            $connection->rollBack();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        self::assertNull($reader->findByCode('host-rollback-category'));
    }

    public function testHostCommitPersistsCategoryMutationAndRemainsTheTransactionOwner(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection);
        $reader = new PdoCategoryQueryReader($connection, new FixedCategoryClock());

        $connection->beginTransaction();
        try {
            $categoryId = $service->create(new CreateCategoryCommand('host-commit-category'));

            self::assertTrue($connection->inTransaction());
            self::assertSame($categoryId, $reader->findByCode('host-commit-category')?->id);
            $connection->commit();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        self::assertNotNull($reader->findByCode('host-commit-category'));
    }

    public function testHostCommitAndRollbackControlImageAssignmentDefaultMutation(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection);
        $reader = new PdoCategoryImageAssignmentQueryReader($connection, new FixedCategoryClock());
        $categoryId = $service->create(new CreateCategoryCommand('host-default-transaction-category'));
        $imageService = $this->imageService($connection);
        $firstId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 1003),
        );
        $secondId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 1004),
        );

        $connection->beginTransaction();
        try {
            $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($firstId));
            self::assertTrue($connection->inTransaction());
            $firstAssignment = $reader->findImageAssignmentById($firstId);
            self::assertNotNull($firstAssignment);
            self::assertTrue($firstAssignment->isDefault);
            $connection->rollBack();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $firstAssignment = $reader->findImageAssignmentById($firstId);
        self::assertNotNull($firstAssignment);
        self::assertFalse($firstAssignment->isDefault);

        $connection->beginTransaction();
        try {
            $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($secondId));
            self::assertTrue($connection->inTransaction());
            $secondAssignment = $reader->findImageAssignmentById($secondId);
            self::assertNotNull($secondAssignment);
            self::assertTrue($secondAssignment->isDefault);
            $connection->commit();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $firstAssignment = $reader->findImageAssignmentById($firstId);
        $secondAssignment = $reader->findImageAssignmentById($secondId);
        self::assertNotNull($firstAssignment);
        self::assertNotNull($secondAssignment);
        self::assertFalse($firstAssignment->isDefault);
        self::assertTrue($secondAssignment->isDefault);
    }

    public function testCategoryFailureDoesNotCloseTheCallerOwnedTransaction(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection);

        $connection->beginTransaction();
        try {
            $categoryId = $service->create(new CreateCategoryCommand('failure-keeps-outer-transaction'));

            try {
                $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand(999999, 1));
                self::fail('Updating a missing Category must fail.');
            } catch (CategoryNotFoundException) {
                self::assertTrue($connection->inTransaction());
            }

            $service->updateStatus(new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::INACTIVE));
            $connection->commit();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $updated = (new PdoCategoryQueryReader($connection, new FixedCategoryClock()))->findById($categoryId);
        self::assertNotNull($updated);
        self::assertSame(CategoryStatusEnum::INACTIVE, $updated->status);
    }

    public function testAllThreeOrderingMutationsParticipateInTheCallerOwnedTransaction(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection);
        $reader = new PdoCategoryQueryReader($connection, new FixedCategoryClock());

        $firstCategoryId = $service->create(new CreateCategoryCommand('outer-ordering-first'));
        $secondCategoryId = $service->create(new CreateCategoryCommand('outer-ordering-second'));
        $imageService = $this->imageService($connection);
        $fieldService = $this->fieldService($connection);
        $firstAssignmentId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($firstCategoryId, 1001),
        );
        $secondAssignmentId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($firstCategoryId, 1002),
        );
        $firstFieldId = $fieldService->create(
            new CreateCategoryContentFieldCommand(
                $firstCategoryId,
                'outer-first-field',
                null,
                null,
                CategoryContentFieldFormatEnum::TEXT,
                'first',
            ),
        );
        $secondFieldId = $fieldService->create(
            new CreateCategoryContentFieldCommand(
                $firstCategoryId,
                'outer-second-field',
                null,
                null,
                CategoryContentFieldFormatEnum::TEXT,
                'second',
            ),
        );

        $firstCategory = $reader->findById($firstCategoryId);
        $secondCategory = $reader->findById($secondCategoryId);
        self::assertNotNull($firstCategory);
        self::assertNotNull($secondCategory);
        self::assertSame(1, $firstCategory->displayOrder);
        self::assertSame(2, $secondCategory->displayOrder);

        $connection->beginTransaction();
        try {
            $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondCategoryId, 1));
            self::assertTrue($connection->inTransaction());

            $imageService->reorder(
                new UpdateCategoryImageAssignmentDisplayOrderCommand($secondAssignmentId, 1),
            );
            self::assertTrue($connection->inTransaction());

            $fieldService->updateDisplayOrder(
                new UpdateCategoryContentFieldDisplayOrderCommand($secondFieldId, 1),
            );
            self::assertTrue($connection->inTransaction());

            $firstCategory = $reader->findById($firstCategoryId);
            $secondCategory = $reader->findById($secondCategoryId);
            self::assertNotNull($firstCategory);
            self::assertNotNull($secondCategory);
            self::assertSame(2, $firstCategory->displayOrder);
            self::assertSame(1, $secondCategory->displayOrder);
            self::assertSame(
                [$secondAssignmentId, $firstAssignmentId],
                $this->idsForScope($connection, 'maa_category_category_image_assignments', $firstCategoryId),
            );
            self::assertSame(
                [$secondFieldId, $firstFieldId],
                $this->idsForScope($connection, 'maa_category_category_content_fields', $firstCategoryId),
            );
            self::assertSame(
                '2026-01-05 00:00:00',
                $this->updatedAtForId($connection, 'maa_category_categories', $secondCategoryId),
            );
            self::assertSame(
                '2026-01-05 00:00:00',
                $this->updatedAtForId($connection, 'maa_category_category_image_assignments', $secondAssignmentId),
            );
            self::assertSame(
                '2026-01-05 00:00:00',
                $this->updatedAtForId($connection, 'maa_category_category_content_fields', $secondFieldId),
            );

            $connection->commit();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $secondCategory = $reader->findById($secondCategoryId);
        self::assertNotNull($secondCategory);
        self::assertSame(1, $secondCategory->displayOrder);
    }

    /** @return list<int> */
    private function idsForScope(PDO $connection, string $table, int $categoryId): array
    {
        $statement = $connection->prepare(
            'SELECT `id` FROM `' . $table . '` '
            . 'WHERE `category_id` = :category_id '
            . 'AND `language_code` IS NULL AND `platform` IS NULL '
            . 'AND `deleted_at` IS NULL ORDER BY `display_order`, `id`',
        );
        $statement->execute(['category_id' => $categoryId]);

        $ids = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            self::assertTrue(is_int($id) || is_string($id));
            $ids[] = (int) $id;
        }

        return $ids;
    }

    private function updatedAtForId(PDO $connection, string $table, int $id): string
    {
        $statement = $connection->prepare(
            'SELECT `updated_at` FROM `' . $table . '` WHERE `id` = :id',
        );
        $statement->execute(['id' => $id]);
        $updatedAt = $statement->fetchColumn();

        if (!is_string($updatedAt)) {
            self::fail('Expected an updated_at value for ' . $table . ' #' . $id . '.');
        }

        return $updatedAt;
    }

    private function service(PDO $connection): CategoryApiInterface
    {
        $clock = new FixedCategoryClock('2026-01-05 00:00:00 Africa/Cairo');

        return CategoryFactory::create($connection, $clock)->categories();
    }

    private function imageService(PDO $connection): ImageAssignmentApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-05 00:00:00 Africa/Cairo'),
        )->images();
    }

    private function fieldService(PDO $connection): ContentFieldApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-05 00:00:00 Africa/Cairo'),
        )->contentFields();
    }
}
