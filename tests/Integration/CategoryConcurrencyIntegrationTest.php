<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Closure;
use DateTimeImmutable;
use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Exception\CategoryCodeAlreadyExistsException;
use Maatify\Category\Hierarchy\Exception\CategoryCycleException;
use Maatify\Category\Lifecycle\Exception\CategoryHasNonDeletedChildrenException;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Content\Mutation\Exception\CategoryContentAlreadyExistsException;
use Maatify\Category\ImageAssignment\Assignment\Exception\CategoryImageAssignmentAlreadyExistsException;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Infrastructure\PdoCategoryCommandRepository;
use Maatify\Category\Query\Infrastructure\PdoCategoryQueryReader;
use Maatify\Category\Content\Infrastructure\PdoCategoryContentCommandRepository;
use Maatify\Category\ImageAssignment\Infrastructure\PdoCategoryImageAssignmentCommandRepository;
use Maatify\Category\ImageRole\Infrastructure\PdoCategoryImageRoleCommandRepository;
use Maatify\Category\ContentField\Infrastructure\PdoCategoryContentFieldCommandRepository;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use PDO;
use PDOException;
use Throwable;

final class CategoryConcurrencyIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testCompetingMovesSerializeOnTheCategoryRowAndPreserveAValidHierarchy(): void
    {
        $service = $this->service($this->connection());
        $sourceParentId = $service->create(new CreateCategoryCommand('competing-move-source'));
        $targetParentId = $service->create(new CreateCategoryCommand('competing-move-target'));
        $categoryId = $service->create(new CreateCategoryCommand('competing-move-category', $sourceParentId));

        $locker = $this->newConnection();
        $this->lockCategoryRow($locker, $categoryId);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $categoryId, $targetParentId): void {
                    $blockedService->move(new MoveCategoryCommand($categoryId, $targetParentId));
                },
                $blockedConnection,
                'A competing move must wait for the category row lock.',
            );

            $locker->rollBack();
            $blockedService->move(new MoveCategoryCommand($categoryId, $targetParentId));

            $reader = new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock());
            self::assertSame($targetParentId, $reader->findById($categoryId)?->parentId);
            $this->assertHierarchyIsValid($blockedConnection);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testParentChangeRaceSerializesBeforeTheChildMoveAndPreservesTheChain(): void
    {
        $service = $this->service($this->connection());
        $sourceParentId = $service->create(new CreateCategoryCommand('parent-race-source'));
        $targetParentId = $service->create(new CreateCategoryCommand('parent-race-target'));
        $newAncestorId = $service->create(new CreateCategoryCommand('parent-race-ancestor'));
        $categoryId = $service->create(new CreateCategoryCommand('parent-race-category', $sourceParentId));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        $lockStatement = $locker->prepare(
            'SELECT `id` FROM `maa_category_categories` WHERE `id` = :id FOR UPDATE',
        );
        $lockStatement->execute(['id' => $targetParentId]);
        $lockerTimestamp = (new FixedCategoryClock('2026-02-09 00:00:00 Africa/Cairo'))
            ->now()
            ->format('Y-m-d H:i:s');
        $updateStatement = $locker->prepare(
            'UPDATE `maa_category_categories` SET `parent_id` = :parent_id, `updated_at` = :updated_at '
            . 'WHERE `id` = :id',
        );
        $updateStatement->execute([
            'parent_id' => $newAncestorId,
            'updated_at' => $lockerTimestamp,
            'id' => $targetParentId,
        ]);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $categoryId, $targetParentId): void {
                    $blockedService->move(new MoveCategoryCommand($categoryId, $targetParentId));
                },
                $blockedConnection,
                'A child move must wait while its target parent changes.',
            );

            $locker->commit();
            $blockedService->move(new MoveCategoryCommand($categoryId, $targetParentId));

            $reader = new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock());
            self::assertSame($targetParentId, $reader->findById($categoryId)?->parentId);
            self::assertSame($newAncestorId, $reader->findById($targetParentId)?->parentId);
            $this->assertHierarchyIsValid($blockedConnection);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testDirectCycleAttemptWaitsForTheAncestorLockThenRemainsRejected(): void
    {
        $service = $this->service($this->connection());
        $rootId = $service->create(new CreateCategoryCommand('direct-cycle-root'));
        $childId = $service->create(new CreateCategoryCommand('direct-cycle-child', $rootId));

        $locker = $this->newConnection();
        $this->lockCategoryRow($locker, $childId);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $rootId, $childId): void {
                    $blockedService->move(new MoveCategoryCommand($rootId, $childId));
                },
                $blockedConnection,
                'A direct cycle attempt must wait on the locked ancestor chain.',
            );

            $locker->rollBack();

            try {
                $blockedService->move(new MoveCategoryCommand($rootId, $childId));
                self::fail('A direct cycle must remain rejected after the lock is released.');
            } catch (CategoryCycleException) {
                self::assertFalse($blockedConnection->inTransaction());
            }

            $this->assertHierarchyIsValid($blockedConnection);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testIndirectCycleAttemptWaitsForTheCompleteAncestorChainThenRemainsRejected(): void
    {
        $service = $this->service($this->connection());
        $rootId = $service->create(new CreateCategoryCommand('indirect-cycle-root'));
        $middleId = $service->create(new CreateCategoryCommand('indirect-cycle-middle', $rootId));
        $leafId = $service->create(new CreateCategoryCommand('indirect-cycle-leaf', $middleId));

        $locker = $this->newConnection();
        $this->lockCategoryRow($locker, $middleId);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $rootId, $leafId): void {
                    $blockedService->move(new MoveCategoryCommand($rootId, $leafId));
                },
                $blockedConnection,
                'An indirect cycle attempt must wait on the complete ancestor chain.',
            );

            $locker->rollBack();

            try {
                $blockedService->move(new MoveCategoryCommand($rootId, $leafId));
                self::fail('An indirect cycle must remain rejected after the lock is released.');
            } catch (CategoryCycleException) {
                self::assertFalse($blockedConnection->inTransaction());
            }

            $this->assertHierarchyIsValid($blockedConnection);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testChildCreationRacingWithParentDeletionLeavesNoInvalidChild(): void
    {
        $service = $this->service($this->connection());
        $parentId = $service->create(new CreateCategoryCommand('create-delete-parent'));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        $lockStatement = $locker->prepare(
            'SELECT `id` FROM `maa_category_categories` WHERE `id` = :id FOR UPDATE',
        );
        $lockStatement->execute(['id' => $parentId]);
        $lockerTimestamp = (new FixedCategoryClock('2026-02-09 00:00:00 Africa/Cairo'))
            ->now()
            ->format('Y-m-d H:i:s');
        $deleteStatement = $locker->prepare(
            'UPDATE `maa_category_categories` SET `deleted_at` = :deleted_at, `updated_at` = :updated_at '
            . 'WHERE `id` = :id',
        );
        $deleteStatement->execute([
            'deleted_at' => $lockerTimestamp,
            'updated_at' => $lockerTimestamp,
            'id' => $parentId,
        ]);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $parentId): void {
                    $blockedService->create(new CreateCategoryCommand('create-delete-child', $parentId));
                },
                $blockedConnection,
                'Child creation must wait for a concurrent parent deletion.',
            );

            $locker->commit();

            try {
                $blockedService->create(new CreateCategoryCommand('create-delete-child', $parentId));
                self::fail('A child must not be created under a deleted parent.');
            } catch (CategoryNotFoundException) {
                self::assertFalse($blockedConnection->inTransaction());
            }

            $reader = new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock());
            self::assertNotNull($reader->findById($parentId)?->deletedAt);
            $statement = $blockedConnection->prepare(
                'SELECT COUNT(*) FROM `maa_category_categories` WHERE `parent_id` = :parent_id',
            );
            $statement->execute(['parent_id' => $parentId]);
            self::assertSame(0, (int) $statement->fetchColumn());
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testParentDeletionRacingWithChildMutationLeavesBothRowsValid(): void
    {
        $service = $this->service($this->connection());
        $parentId = $service->create(new CreateCategoryCommand('delete-race-parent'));
        $childId = $service->create(new CreateCategoryCommand('delete-race-child', $parentId));

        $locker = $this->newConnection();
        $this->lockCategoryRow($locker, $childId);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $parentId): void {
                    $blockedService->softDelete(new SoftDeleteCategoryCommand($parentId));
                },
                $blockedConnection,
                'Parent deletion must wait while the child lifecycle is changing.',
            );

            $locker->rollBack();

            try {
                $blockedService->softDelete(new SoftDeleteCategoryCommand($parentId));
                self::fail('A parent with an active child must remain undeletable.');
            } catch (CategoryHasNonDeletedChildrenException) {
                self::assertFalse($blockedConnection->inTransaction());
            }

            $reader = new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock());
            self::assertNull($reader->findById($parentId)?->deletedAt);
            self::assertNull($reader->findById($childId)?->deletedAt);
            $this->assertHierarchyIsValid($blockedConnection);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testConcurrentRootReorderPreservesAContiguousSequenceAndTimestamp(): void
    {
        $service = $this->service($this->connection());
        $firstId = $service->create(new CreateCategoryCommand('root-reorder-first'));
        $secondId = $service->create(new CreateCategoryCommand('root-reorder-second'));
        $thirdId = $service->create(new CreateCategoryCommand('root-reorder-third'));

        $locker = $this->newConnection();
        $this->lockOrderingScope($locker, null);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service(
            $blockedConnection,
            new FixedCategoryClock('2026-02-10 00:00:00 Africa/Cairo'),
        );

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $secondId): void {
                    $blockedService->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondId, 1));
                },
                $blockedConnection,
                'Root reorder must wait for the root ordering scope lock.',
            );

            $locker->rollBack();
            $blockedService->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondId, 1));

            self::assertSame(
                [$secondId => 1, $firstId => 2, $thirdId => 3],
                $this->ordersForScope($blockedConnection, null),
            );
            self::assertSame(
                '2026-02-10 00:00:00',
                $this->updatedAt($blockedConnection, $secondId),
            );
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testConcurrentSiblingReorderPreservesAContiguousSequenceAndTimestamp(): void
    {
        $service = $this->service($this->connection());
        $parentId = $service->create(new CreateCategoryCommand('sibling-reorder-parent'));
        $firstId = $service->create(new CreateCategoryCommand('sibling-reorder-first', $parentId));
        $secondId = $service->create(new CreateCategoryCommand('sibling-reorder-second', $parentId));
        $thirdId = $service->create(new CreateCategoryCommand('sibling-reorder-third', $parentId));

        $locker = $this->newConnection();
        $this->lockOrderingScope($locker, $parentId);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service(
            $blockedConnection,
            new FixedCategoryClock('2026-02-11 00:00:00 Africa/Cairo'),
        );

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $secondId): void {
                    $blockedService->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondId, 1));
                },
                $blockedConnection,
                'Sibling reorder must wait for the sibling ordering scope lock.',
            );

            $locker->rollBack();
            $blockedService->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondId, 1));

            self::assertSame(
                [$secondId => 1, $firstId => 2, $thirdId => 3],
                $this->ordersForScope($blockedConnection, $parentId),
            );
            self::assertSame(
                '2026-02-11 00:00:00',
                $this->updatedAt($blockedConnection, $secondId),
            );
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testConcurrentCategoryCodeCreationUsesUniqueConstraintAfterBothFlowsObserveAbsence(): void
    {
        $bootstrapService = $this->service($this->connection());
        $leftParentId = $bootstrapService->create(new CreateCategoryCommand('code-race-left-parent'));
        $rightParentId = $bootstrapService->create(new CreateCategoryCommand('code-race-right-parent'));

        $leftConnection = $this->newConnection();
        $rightConnection = $this->newConnection();
        $leftReader = new PdoCategoryQueryReader($leftConnection, new FixedCategoryClock());
        $rightReader = new PdoCategoryQueryReader($rightConnection, new FixedCategoryClock());
        $leftRepository = new PdoCategoryCommandRepository($leftConnection, new ScopedOrderingManager());
        $rightRepository = new PdoCategoryCommandRepository($rightConnection, new ScopedOrderingManager());
        $code = 'concurrent-absent-code';
        $occurredAt = new DateTimeImmutable('2026-02-12 00:00:00 Africa/Cairo');

        try {
            // Both independent flows observe the same code as absent before either insert.
            $leftConnection->beginTransaction();
            self::assertNull($leftReader->findByCode($code));

            $rightConnection->beginTransaction();
            self::assertNull($rightReader->findByCode($code));

            $winnerId = $leftRepository->create(
                new CreateCategoryCommand($code, $leftParentId),
                $occurredAt,
            );
            $leftConnection->commit();

            try {
                $rightRepository->create(
                    new CreateCategoryCommand($code, $rightParentId),
                    $occurredAt,
                );
                self::fail('The losing creation flow must fail on the Category code unique key.');
            } catch (CategoryCodeAlreadyExistsException $exception) {
                self::assertInstanceOf(PDOException::class, $exception->getPrevious());
                self::assertTrue($rightConnection->inTransaction());
                $rightConnection->rollBack();
            }

            self::assertFalse($rightConnection->inTransaction());
            $reader = new PdoCategoryQueryReader($this->connection(), new FixedCategoryClock());
            self::assertSame(
                $winnerId,
                $reader->findByCode($code)?->id,
            );
            $countStatement = $this->connection()->prepare(
                'SELECT COUNT(*) FROM `maa_category_categories` WHERE `code` = :code',
            );
            $countStatement->execute(['code' => $code]);
            self::assertSame(1, (int) $countStatement->fetchColumn());
        } finally {
            $this->closeConnection($leftConnection);
            $this->closeConnection($rightConnection);
        }
    }

    public function testConcurrentContentCreationCannotDuplicateLogicalIdentity(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-content-category'));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        $lockerTimestamp = (new FixedCategoryClock('2026-02-09 00:00:00 Africa/Cairo'))
            ->now()
            ->format('Y-m-d H:i:s');
        $insert = $locker->prepare(
            'INSERT INTO `maa_category_category_contents` '
            . '(`category_id`, `language_code`, `name`, `created_at`, `updated_at`) '
            . 'VALUES (:category_id, :language_code, :name, :created_at, :updated_at)',
        );
        $insert->execute([
            'category_id' => $categoryId,
            'language_code' => 'en-US',
            'name' => 'Reserved',
            'created_at' => $lockerTimestamp,
            'updated_at' => $lockerTimestamp,
        ]);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);
        $blockedContentService = $this->contentService($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedContentService, $categoryId): void {
                    $blockedContentService->create(
                        new CreateCategoryContentCommand($categoryId, 'en-US', 'Competing', null),
                    );
                },
                $blockedConnection,
                'A competing content creation must wait on its logical identity.',
            );

            $locker->commit();

            try {
                $blockedContentService->create(
                    new CreateCategoryContentCommand($categoryId, 'en-US', 'Competing', null),
                );
                self::fail('A committed content identity must not be duplicated.');
            } catch (CategoryContentAlreadyExistsException) {
                self::assertFalse($blockedConnection->inTransaction());
            }

            $statement = $blockedConnection->prepare(
                'SELECT COUNT(*) FROM `maa_category_category_contents` '
                . 'WHERE `category_id` = :category_id AND `language_code` = :language_code',
            );
            $statement->execute([
                'category_id' => $categoryId,
                'language_code' => 'en-US',
            ]);
            self::assertSame(1, (int) $statement->fetchColumn());
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testConcurrentImageAssignmentCreationCannotDuplicateExactNullScopeIdentity(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-image-category'));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        $lockerTimestamp = (new FixedCategoryClock('2026-02-09 00:00:00 Africa/Cairo'))
            ->now()
            ->format('Y-m-d H:i:s');
        $insert = $locker->prepare(
            'INSERT INTO `maa_category_category_image_assignments` '
            . '(`category_id`, `media_asset_id`, `language_code`, `platform`, `display_order`, `created_at`, `updated_at`) '
            . 'VALUES (:category_id, :media_asset_id, NULL, NULL, 1, :created_at, :updated_at)',
        );
        $insert->execute([
            'category_id' => $categoryId,
            'media_asset_id' => 700,
            'created_at' => $lockerTimestamp,
            'updated_at' => $lockerTimestamp,
        ]);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);
        $blockedImageService = $this->imageService($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedImageService, $categoryId): void {
                    $blockedImageService->assign(
                        new CreateCategoryImageAssignmentCommand($categoryId, 700),
                    );
                },
                $blockedConnection,
                'A competing Image Assignment creation must wait on its exact identity.',
            );

            $locker->commit();

            try {
                $blockedImageService->assign(
                    new CreateCategoryImageAssignmentCommand($categoryId, 700),
                );
                self::fail('A committed Image Assignment identity must not be duplicated.');
            } catch (CategoryImageAssignmentAlreadyExistsException) {
                self::assertFalse($blockedConnection->inTransaction());
            }

            $statement = $blockedConnection->prepare(
                'SELECT COUNT(*) FROM `maa_category_category_image_assignments` '
                . 'WHERE `category_id` = :category_id AND `media_asset_id` = :media_asset_id '
                . 'AND `language_code` IS NULL AND `platform` IS NULL',
            );
            $statement->execute([
                'category_id' => $categoryId,
                'media_asset_id' => 700,
            ]);
            self::assertSame(1, (int) $statement->fetchColumn());
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testConcurrentImageAssignmentCreationAllocatesDistinctPositionsWithinExactScope(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-image-order-category'));
        $firstConnection = $this->newConnection();
        $secondConnection = $this->newConnection();
        $firstRepository = new PdoCategoryImageAssignmentCommandRepository(
            $firstConnection,
            new ScopedOrderingManager(),
        );
        $secondRepository = new PdoCategoryImageAssignmentCommandRepository(
            $secondConnection,
            new ScopedOrderingManager(),
        );
        $occurredAt = new DateTimeImmutable('2026-02-13 00:00:00 Africa/Cairo');

        try {
            // Keep the first real creation transaction open while the second
            // real creation reaches lockCreationScope() for the same scope.
            $firstConnection->beginTransaction();
            $firstId = $firstRepository->create(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    701,
                    new CategoryImageAssignmentScopeDTO('en-US', 'web'),
                ),
                $occurredAt,
            );

            $secondConnection->beginTransaction();
            $this->setLockWaitTimeout($secondConnection);

            try {
                $secondRepository->create(
                    new CreateCategoryImageAssignmentCommand(
                        $categoryId,
                        702,
                        new CategoryImageAssignmentScopeDTO('en-US', 'web'),
                    ),
                    $occurredAt,
                );
                self::fail('A concurrent creation must wait for the exact ordering scope lock.');
            } catch (PDOException $exception) {
                $driverCode = $exception->errorInfo[1] ?? null;
                if (!is_int($driverCode) && !is_string($driverCode)) {
                    self::fail('The concurrent creation must expose the MySQL lock wait timeout code.');
                }
                self::assertSame(1205, (int) $driverCode, $exception->getMessage());
                self::assertTrue($secondConnection->inTransaction());
                $secondConnection->rollBack();
            }

            $firstConnection->commit();

            $secondConnection->beginTransaction();
            $secondId = $secondRepository->create(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    702,
                    new CategoryImageAssignmentScopeDTO('en-US', 'web'),
                ),
                $occurredAt,
            );
            $secondConnection->commit();

            $orders = $this->imageAssignmentOrdersForScope(
                $this->connection(),
                $categoryId,
                'en-US',
                'web',
            );
            self::assertSame([$firstId => 1, $secondId => 2], $orders);
            self::assertSame(
                count($orders),
                count(array_unique(array_values($orders))),
                'The active exact scope must not contain duplicate display_order values.',
            );
        } finally {
            $this->closeConnection($firstConnection);
            $this->closeConnection($secondConnection);
        }
    }

    public function testConcurrentImageAssignmentCreationAllocatesDistinctPositionsWithinExactRoleScope(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-image-role-order-category'));
        $roleId = $this->roleService($this->connection())->create(
            new CreateCategoryImageRoleCommand('concurrent-gallery'),
        );
        $firstConnection = $this->newConnection();
        $secondConnection = $this->newConnection();
        $firstRepository = new PdoCategoryImageAssignmentCommandRepository(
            $firstConnection,
            new ScopedOrderingManager(),
        );
        $secondRepository = new PdoCategoryImageAssignmentCommandRepository(
            $secondConnection,
            new ScopedOrderingManager(),
        );
        $occurredAt = new DateTimeImmutable('2026-02-13 00:00:00 Africa/Cairo');

        try {
            $firstConnection->beginTransaction();
            $firstId = $firstRepository->create(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    703,
                    new CategoryImageAssignmentScopeDTO('en-US', 'web', $roleId),
                ),
                $occurredAt,
            );

            $secondConnection->beginTransaction();
            $this->setLockWaitTimeout($secondConnection);

            try {
                $secondRepository->create(
                    new CreateCategoryImageAssignmentCommand(
                        $categoryId,
                        704,
                        new CategoryImageAssignmentScopeDTO('en-US', 'web', $roleId),
                    ),
                    $occurredAt,
                );
                self::fail('A concurrent creation must wait for the exact Role ordering scope lock.');
            } catch (PDOException $exception) {
                $driverCode = $exception->errorInfo[1] ?? null;
                if (!is_int($driverCode) && !is_string($driverCode)) {
                    self::fail('The concurrent Role creation must expose the MySQL lock wait timeout code.');
                }
                self::assertSame(1205, (int) $driverCode, $exception->getMessage());
                self::assertTrue($secondConnection->inTransaction());
                $secondConnection->rollBack();
            }

            $firstConnection->commit();

            $secondConnection->beginTransaction();
            $secondId = $secondRepository->create(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    704,
                    new CategoryImageAssignmentScopeDTO('en-US', 'web', $roleId),
                ),
                $occurredAt,
            );
            $secondConnection->commit();

            $orders = $this->imageAssignmentOrdersForScope(
                $this->connection(),
                $categoryId,
                'en-US',
                'web',
                $roleId,
            );
            self::assertSame([$firstId => 1, $secondId => 2], $orders);
        } finally {
            $this->closeConnection($firstConnection);
            $this->closeConnection($secondConnection);
        }
    }

    public function testConcurrentImageAssignmentCreationInDifferentRoleScopesPreservesEachSequence(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-image-role-isolation-category'));
        $roleService = $this->roleService($this->connection());
        $firstRoleId = $roleService->create(new CreateCategoryImageRoleCommand('concurrent-first'));
        $secondRoleId = $roleService->create(new CreateCategoryImageRoleCommand('concurrent-second'));
        $firstConnection = $this->newConnection();
        $secondConnection = $this->newConnection();
        $firstRepository = new PdoCategoryImageAssignmentCommandRepository(
            $firstConnection,
            new ScopedOrderingManager(),
        );
        $secondRepository = new PdoCategoryImageAssignmentCommandRepository(
            $secondConnection,
            new ScopedOrderingManager(),
        );
        $occurredAt = new DateTimeImmutable('2026-02-13 00:00:00 Africa/Cairo');

        try {
            $this->setLockWaitTimeout($secondConnection);
            $firstConnection->beginTransaction();
            $secondConnection->beginTransaction();

            $firstId = $firstRepository->create(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    705,
                    new CategoryImageAssignmentScopeDTO('en-US', 'web', $firstRoleId),
                ),
                $occurredAt,
            );

            try {
                $secondRepository->create(
                    new CreateCategoryImageAssignmentCommand(
                        $categoryId,
                        706,
                        new CategoryImageAssignmentScopeDTO('en-US', 'web', $secondRoleId),
                    ),
                    $occurredAt,
                );
                self::fail('A concurrent operation may wait, but must not corrupt a different Role scope.');
            } catch (PDOException $exception) {
                $driverCode = $exception->errorInfo[1] ?? null;
                if (!is_int($driverCode) && !is_string($driverCode)) {
                    self::fail('The different Role scope operation must expose the MySQL lock wait timeout code.');
                }
                self::assertSame(1205, (int) $driverCode, $exception->getMessage());
                self::assertTrue($secondConnection->inTransaction());
                $secondConnection->rollBack();
            }

            $firstConnection->commit();

            $secondConnection->beginTransaction();
            $secondId = $secondRepository->create(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    706,
                    new CategoryImageAssignmentScopeDTO('en-US', 'web', $secondRoleId),
                ),
                $occurredAt,
            );
            $secondConnection->commit();

            self::assertSame(
                [$firstId => 1],
                $this->imageAssignmentOrdersForScope(
                    $this->connection(),
                    $categoryId,
                    'en-US',
                    'web',
                    $firstRoleId,
                ),
            );
            self::assertSame(
                [$secondId => 1],
                $this->imageAssignmentOrdersForScope(
                    $this->connection(),
                    $categoryId,
                    'en-US',
                    'web',
                    $secondRoleId,
                ),
            );
        } finally {
            $this->closeConnection($firstConnection);
            $this->closeConnection($secondConnection);
        }
    }

    public function testConcurrentContentFieldCreationAllocatesDistinctPositionsWithinExactScope(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-field-order-category'));
        $firstConnection = $this->newConnection();
        $secondConnection = $this->newConnection();
        $firstRepository = new PdoCategoryContentFieldCommandRepository(
            $firstConnection,
            new ScopedOrderingManager(),
        );
        $secondRepository = new PdoCategoryContentFieldCommandRepository(
            $secondConnection,
            new ScopedOrderingManager(),
        );
        $occurredAt = new DateTimeImmutable('2026-02-13 00:00:00 Africa/Cairo');

        try {
            $firstConnection->beginTransaction();
            $firstId = $firstRepository->create(
                new CreateCategoryContentFieldCommand(
                    $categoryId,
                    'first',
                    'en-US',
                    'web',
                    CategoryContentFieldFormatEnum::TEXT,
                    'first',
                ),
                $occurredAt,
            );

            $secondConnection->beginTransaction();
            $this->setLockWaitTimeout($secondConnection);

            try {
                $secondRepository->create(
                    new CreateCategoryContentFieldCommand(
                        $categoryId,
                        'second',
                        'en-US',
                        'web',
                        CategoryContentFieldFormatEnum::TEXT,
                        'second',
                    ),
                    $occurredAt,
                );
                self::fail('A concurrent field creation must wait for the exact ordering scope lock.');
            } catch (PDOException $exception) {
                $driverCode = $exception->errorInfo[1] ?? null;
                if (!is_int($driverCode) && !is_string($driverCode)) {
                    self::fail('The concurrent field creation must expose the MySQL lock wait timeout code.');
                }
                self::assertSame(1205, (int) $driverCode, $exception->getMessage());
                self::assertTrue($secondConnection->inTransaction());
                $secondConnection->rollBack();
            }

            $firstConnection->commit();

            $secondConnection->beginTransaction();
            $secondId = $secondRepository->create(
                new CreateCategoryContentFieldCommand(
                    $categoryId,
                    'second',
                    'en-US',
                    'web',
                    CategoryContentFieldFormatEnum::TEXT,
                    'second',
                ),
                $occurredAt,
            );
            $secondConnection->commit();

            $statement = $this->connection()->prepare(
                'SELECT `id`, `display_order` FROM `maa_category_category_content_fields` '
                . 'WHERE `category_id` = :category_id AND `language_code` = :language_code '
                . 'AND `platform` = :platform AND `deleted_at` IS NULL ORDER BY `display_order`, `id`',
            );
            $statement->execute([
                'category_id' => $categoryId,
                'language_code' => 'en-US',
                'platform' => 'web',
            ]);
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $firstRowId = $rows[0]['id'] ?? null;
            $secondRowId = $rows[1]['id'] ?? null;
            $firstRowOrder = $rows[0]['display_order'] ?? null;
            $secondRowOrder = $rows[1]['display_order'] ?? null;
            if (!is_int($firstRowId) && !is_string($firstRowId)) {
                self::fail('The first field row identity must be scalar.');
            }
            if (!is_int($secondRowId) && !is_string($secondRowId)) {
                self::fail('The second field row identity must be scalar.');
            }
            if (!is_int($firstRowOrder) && !is_string($firstRowOrder)) {
                self::fail('The first field row order must be scalar.');
            }
            if (!is_int($secondRowOrder) && !is_string($secondRowOrder)) {
                self::fail('The second field row order must be scalar.');
            }
            self::assertSame([$firstId, $secondId], [(int) $firstRowId, (int) $secondRowId]);
            self::assertSame([1, 2], [(int) $firstRowOrder, (int) $secondRowOrder]);
        } finally {
            $this->closeConnection($firstConnection);
            $this->closeConnection($secondConnection);
        }
    }

    public function testRestoreWaitsOnTheExistingRowAndPreservesItsIdentity(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-restore-category'));
        $service->softDelete(new SoftDeleteCategoryCommand($categoryId));

        $locker = $this->newConnection();
        $this->lockCategoryRow($locker, $categoryId);

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedService, $categoryId): void {
                    $blockedService->restore(new RestoreCategoryCommand($categoryId));
                },
                $blockedConnection,
                'Restore must wait on the existing Category row.',
            );

            $locker->rollBack();
            $blockedService->restore(new RestoreCategoryCommand($categoryId));

            $restored = (new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock()))->findById($categoryId);
            self::assertNotNull($restored);
            self::assertSame($categoryId, $restored->id);
            self::assertNull($restored->deletedAt);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    public function testWriteFailureRollsBackPartialMutationsAndCleansTheConnection(): void
    {
        $connection = $this->connection();
        $transaction = new PdoTransactionRunner($connection);
        $timestamp = (new FixedCategoryClock('2026-02-09 00:00:00 Africa/Cairo'))
            ->now()
            ->format('Y-m-d H:i:s');

        try {
            $transaction->run(function () use ($connection, $timestamp): void {
                $partialInsert = $connection->prepare(
                    'INSERT INTO `maa_category_categories` '
                    . '(`code`, `status`, `display_order`, `created_at`, `updated_at`) '
                    . 'VALUES (:code, :status, :display_order, :created_at, :updated_at)',
                );
                $partialInsert->execute([
                    'code' => 'rolled-back-partial-write',
                    'status' => 'active',
                    'display_order' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $failedInsert = $connection->prepare(
                    'INSERT INTO `maa_category_categories` '
                    . '(`code`, `status`, `display_order`, `created_at`, `updated_at`) '
                    . 'VALUES (:code, :status, :display_order, :created_at, :updated_at)',
                );
                $failedInsert->execute([
                    'code' => 'failed-write',
                    'status' => 'invalid',
                    'display_order' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            });
            self::fail('The invalid write must fail inside the transaction.');
        } catch (PDOException) {
            self::assertFalse($connection->inTransaction());
        }

        $partialLookup = $connection->prepare(
            'SELECT COUNT(*) FROM `maa_category_categories` WHERE `code` = :code',
        );
        $partialLookup->execute(['code' => 'rolled-back-partial-write']);
        self::assertSame(0, (int) $partialLookup->fetchColumn());

        $service = $this->service($connection);
        $createdId = $service->create(new CreateCategoryCommand('after-write-failure'));
        self::assertSame($createdId, (new PdoCategoryQueryReader($connection, new FixedCategoryClock()))->findByCode('after-write-failure')?->id);
        self::assertFalse($connection->inTransaction());
    }

    public function testConcurrentDefaultChangesWaitOnTheExactScopeAndPreserveOneDefault(): void
    {
        $service = $this->service($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('concurrent-image-default-category'));
        $imageService = $this->imageService($this->connection());
        $firstId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 705),
        );
        $secondId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 706),
        );

        $locker = $this->newConnection();
        $locker->beginTransaction();
        $lockStatement = $locker->prepare(
            'SELECT id FROM maa_category_category_image_assignments '
            . 'WHERE ordering_scope = (SELECT ordering_scope '
            . 'FROM maa_category_category_image_assignments WHERE id = :assignment_id) '
            . 'FOR UPDATE',
        );
        $lockStatement->execute(['assignment_id' => $firstId]);
        self::assertNotFalse($lockStatement->fetchColumn());

        $blockedConnection = $this->newConnection();
        $this->setLockWaitTimeout($blockedConnection);
        $blockedService = $this->service($blockedConnection);
        $blockedImageService = $this->imageService($blockedConnection);

        try {
            $this->assertLockWaitTimeout(
                function () use ($blockedImageService, $secondId): void {
                    $blockedImageService->setDefault(
                        new SetCategoryImageAssignmentDefaultCommand($secondId),
                    );
                },
                $blockedConnection,
                'A concurrent default change must wait for the exact default scope lock.',
            );

            $locker->rollBack();
            $blockedImageService->setDefault(
                new SetCategoryImageAssignmentDefaultCommand($secondId),
            );

            $defaultStatement = $this->connection()->prepare(
                'SELECT id FROM maa_category_category_image_assignments '
                . 'WHERE category_id = :category_id '
                . 'AND language_code IS NULL AND platform IS NULL AND role_id IS NULL '
                . 'AND deleted_at IS NULL AND is_default = 1',
            );
            $defaultStatement->execute(['category_id' => $categoryId]);
            $defaultIds = [];
            foreach ($defaultStatement->fetchAll(PDO::FETCH_COLUMN) as $id) {
                if (!is_int($id) && !is_string($id)) {
                    self::fail('The active default Image Assignment identity must be scalar.');
                }
                $defaultIds[] = (int) $id;
            }
            self::assertSame([$secondId], $defaultIds);
        } finally {
            $this->closeConnection($locker);
            $this->closeConnection($blockedConnection);
        }
    }

    private function service(PDO $connection, ?FixedCategoryClock $clock = null): CategoryApiInterface
    {
        $clock ??= new FixedCategoryClock();

        return CategoryFactory::create($connection, $clock)->categories();
    }

    private function contentService(PDO $connection): ContentApiInterface
    {
        return CategoryFactory::create($connection, new FixedCategoryClock())->contents();
    }

    private function imageService(PDO $connection): ImageAssignmentApiInterface
    {
        return CategoryFactory::create($connection, new FixedCategoryClock())->images();
    }

    private function roleService(PDO $connection): ImageRoleApiInterface
    {
        return CategoryFactory::create($connection, new FixedCategoryClock())->imageRoles();
    }

    private function setLockWaitTimeout(PDO $connection): void
    {
        $connection->exec('SET SESSION innodb_lock_wait_timeout = 1');
    }

    private function lockCategoryRow(PDO $connection, int $categoryId): void
    {
        $connection->beginTransaction();
        $statement = $connection->prepare(
            'SELECT `id` FROM `maa_category_categories` WHERE `id` = :id FOR UPDATE',
        );
        $statement->execute(['id' => $categoryId]);
        self::assertSame($categoryId, (int) $statement->fetchColumn());
    }

    private function lockOrderingScope(PDO $connection, ?int $parentId): void
    {
        $connection->beginTransaction();
        $statement = $connection->prepare(
            'SELECT `id` FROM `maa_category_categories` '
            . 'WHERE `parent_id` <=> :parent_id AND `deleted_at` IS NULL '
            . 'ORDER BY `display_order`, `id` FOR UPDATE',
        );
        $statement->execute(['parent_id' => $parentId]);
        self::assertNotFalse($statement->fetchColumn());
    }

    /** @param Closure(): void $operation */
    private function assertLockWaitTimeout(Closure $operation, PDO $connection, string $message): void
    {
        try {
            $operation();
            self::fail($message);
        } catch (PDOException $exception) {
            $driverCode = null;
            if (is_array($exception->errorInfo)) {
                $rawDriverCode = $exception->errorInfo[1] ?? null;
                if (is_int($rawDriverCode) || is_string($rawDriverCode)) {
                    $driverCode = (int) $rawDriverCode;
                }
            }
            self::assertSame(1205, $driverCode, $exception->getMessage());
            self::assertFalse($connection->inTransaction());
        }
    }

    private function closeConnection(PDO $connection): void
    {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }

    /** @return array<int, int|null> */
    private function parentMap(PDO $connection): array
    {
        $statement = $connection->query(
            'SELECT `id`, `parent_id` FROM `maa_category_categories` ORDER BY `id`',
        );
        self::assertNotFalse($statement);

        /** @var array<int, int|null> $parents */
        $parents = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                self::fail('Category row must be an associative array.');
            }

            $idValue = $row['id'] ?? null;
            if (!is_int($idValue) && !is_string($idValue)) {
                self::fail('Category id must be an integer value.');
            }
            $id = (int) $idValue;

            $parentId = $row['parent_id'] ?? null;
            if ($parentId !== null && !is_int($parentId) && !is_string($parentId)) {
                self::fail('Category parent_id must be an integer or null.');
            }
            $parents[$id] = $parentId === null ? null : (int) $parentId;
        }

        return $parents;
    }

    private function assertHierarchyIsValid(PDO $connection): void
    {
        $parents = $this->parentMap($connection);

        foreach ($parents as $categoryId => $parentId) {
            $visited = [$categoryId => true];
            while ($parentId !== null) {
                self::assertArrayHasKey($parentId, $parents);
                self::assertArrayNotHasKey($parentId, $visited);
                $visited[$parentId] = true;
                $parentId = $parents[$parentId];
            }
        }
    }

    /** @return array<int, int> */
    private function ordersForScope(PDO $connection, ?int $parentId): array
    {
        $statement = $connection->prepare(
            'SELECT `id`, `display_order` FROM `maa_category_categories` '
            . 'WHERE `parent_id` <=> :parent_id AND `deleted_at` IS NULL '
            . 'ORDER BY `display_order`, `id`',
        );
        $statement->execute(['parent_id' => $parentId]);

        /** @var array<int|string, int|string> $orders */
        $orders = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        $normalized = [];
        foreach ($orders as $id => $order) {
            $normalized[(int) $id] = (int) $order;
        }

        return $normalized;
    }

    /** @return array<int, int> */
    private function imageAssignmentOrdersForScope(
        PDO $connection,
        int $categoryId,
        string $languageCode,
        string $platform,
        ?int $roleId = null,
    ): array {
        $rolePredicate = $roleId === null ? '`role_id` IS NULL' : '`role_id` = :role_id';
        $statement = $connection->prepare(
            'SELECT `id`, `display_order` FROM `maa_category_category_image_assignments` '
            . 'WHERE `category_id` = :category_id AND `language_code` = :language_code '
            . 'AND `platform` = :platform AND ' . $rolePredicate . ' AND `deleted_at` IS NULL '
            . 'ORDER BY `display_order`, `id`',
        );
        $statement->execute([
            'category_id' => $categoryId,
            'language_code' => $languageCode,
            'platform' => $platform,
            ...($roleId === null ? [] : ['role_id' => $roleId]),
        ]);

        /** @var array<int|string, int|string> $orders */
        $orders = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        $normalized = [];
        foreach ($orders as $id => $order) {
            $normalized[(int) $id] = (int) $order;
        }

        return $normalized;
    }

    private function updatedAt(PDO $connection, int $categoryId): string
    {
        $statement = $connection->prepare(
            'SELECT `updated_at` FROM `maa_category_categories` WHERE `id` = :id',
        );
        $statement->execute(['id' => $categoryId]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }
}
