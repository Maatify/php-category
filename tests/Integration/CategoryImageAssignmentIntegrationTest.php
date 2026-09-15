<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentRoleFilterDTO;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\ImageAssignment\Assignment\Exception\CategoryImageAssignmentAlreadyExistsException;
use Maatify\Category\ImageAssignment\Exception\CategoryImageAssignmentNotFoundException;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\ImageAssignment\Query\Infrastructure\PdoCategoryImageAssignmentQueryReader;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use PDO;

final class CategoryImageAssignmentIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testHostCanCompleteTheImageAssignmentWorkflowThroughTheFacadeAndObserveRealState(): void
    {
        $connection = $this->connection();
        $category = CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        );
        $categoryId = $category->categories()->create(new CreateCategoryCommand('image-consumer-workflow-category'));
        $genericScope = new CategoryImageAssignmentScopeDTO();
        $localizedScope = new CategoryImageAssignmentScopeDTO('en-US', 'web');
        $galleryRoleId = $category->imageRoles()->create(new CreateCategoryImageRoleCommand('workflow-gallery'));

        $firstAssignmentId = $category->images()->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 900, $genericScope),
        );
        $secondAssignmentId = $category->images()->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 901, $genericScope),
        );
        $localizedAssignmentId = $category->images()->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 900, $localizedScope),
        );
        $roleAssignmentId = $category->images()->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                902,
                new CategoryImageAssignmentScopeDTO('en-US', 'web', $galleryRoleId),
            ),
        );

        self::assertSame(
            [$firstAssignmentId, $secondAssignmentId],
            $this->ids($category->images()->listVisibleForCategory($categoryId, $genericScope)),
        );
        self::assertSame(
            [$localizedAssignmentId],
            $this->ids($category->images()->listVisibleForCategory($categoryId, $localizedScope)),
        );
        self::assertSame(
            [$roleAssignmentId],
            $this->ids($category->images()->listVisibleForCategory(
                $categoryId,
                new CategoryImageAssignmentScopeDTO('en-US', 'web', $galleryRoleId),
            )),
        );
        self::assertTrue(
            $category->images()->listVisibleForCategory(
                $categoryId,
                new CategoryImageAssignmentScopeDTO('en-US'),
            )->isEmpty(),
            'A missing platform must remain an exact scope and must not fall back.',
        );

        $category->images()->reorder(
            new UpdateCategoryImageAssignmentDisplayOrderCommand($secondAssignmentId, 1),
        );
        self::assertSame(
            [$secondAssignmentId, $firstAssignmentId],
            $this->ids($category->images()->listVisibleForCategory($categoryId, $genericScope)),
        );

        $orderedStatement = $connection->prepare(
            'SELECT id, display_order FROM maa_category_category_image_assignments '
            . 'WHERE category_id = :workflow_category_id '
            . 'AND language_code IS NULL AND platform IS NULL AND role_id IS NULL '
            . 'AND deleted_at IS NULL ORDER BY display_order ASC, id ASC',
        );
        $orderedStatement->execute(['workflow_category_id' => $categoryId]);
        /** @var list<array<string, mixed>> $orderedRows */
        $orderedRows = $orderedStatement->fetchAll(PDO::FETCH_ASSOC);
        $orderedIds = [];
        foreach ($orderedRows as $orderedRow) {
            $id = $orderedRow['id'] ?? null;
            $orderedIds[] = is_int($id) || is_string($id) ? (int) $id : 0;
        }
        self::assertSame([$secondAssignmentId, $firstAssignmentId], $orderedIds);
        self::assertSame(2, $this->integerFromRow($orderedRows[1] ?? [], 'display_order'));

        $category->images()->setDefault(new SetCategoryImageAssignmentDefaultCommand($firstAssignmentId));
        self::assertTrue($category->images()->getByIdForManagement($firstAssignmentId)->isDefault);
        self::assertFalse($category->images()->getByIdForManagement($secondAssignmentId)->isDefault);
        self::assertFalse($category->images()->getByIdForManagement($localizedAssignmentId)->isDefault);

        $category->images()->remove(new SoftDeleteCategoryImageAssignmentCommand($firstAssignmentId));
        self::assertSame(
            [$secondAssignmentId],
            $this->ids($category->images()->listVisibleForCategory($categoryId, $genericScope)),
        );
        $deletedAssignment = $category->images()->getByIdForManagement(
            $firstAssignmentId,
            CategoryDeletedStateEnum::DELETED_ONLY,
        );
        self::assertNotNull($deletedAssignment->deletedAt);
        self::assertFalse($deletedAssignment->isDefault);
        self::assertFalse($category->images()->getByIdForManagement($secondAssignmentId)->isDefault);

        $stateStatement = $connection->prepare(
            'SELECT media_asset_id, is_default, deleted_at '
            . 'FROM maa_category_category_image_assignments '
            . 'WHERE id = :workflow_assignment_id',
        );
        $stateStatement->execute(['workflow_assignment_id' => $firstAssignmentId]);
        /** @var array<string, mixed>|false $stateRow */
        $stateRow = $stateStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($stateRow);
        self::assertSame(900, $this->integerFromRow($stateRow, 'media_asset_id'));
        self::assertSame(0, $this->integerFromRow($stateRow, 'is_default', 1));
        self::assertIsString($stateRow['deleted_at'] ?? null);

        $category->images()->restore(new RestoreCategoryImageAssignmentCommand($firstAssignmentId));
        $restoredAssignment = $category->images()->getByIdForManagement($firstAssignmentId);
        self::assertNull($restoredAssignment->deletedAt);
        self::assertFalse($restoredAssignment->isDefault);
        self::assertSame(
            [$secondAssignmentId, $firstAssignmentId],
            $this->ids($category->images()->listVisibleForCategory($categoryId, $genericScope)),
        );
        self::assertSame(
            1,
            $category->images()->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(
                    categoryId: $categoryId,
                    scope: $localizedScope,
                    roleFilter: CategoryImageAssignmentRoleFilterDTO::exactNull(),
                ),
            )->count(),
        );
    }

    public function testDefaultIsExplicitPerExactScopeAndIndependentFromOrdering(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $clock = new FixedCategoryClock();
        $mutationReader = $this->queryReader($connection, $clock);
        $imageService = $this->imageService($connection);
        $roleService = $this->roleService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-default-category'));
        $roleId = $roleService->create(new CreateCategoryImageRoleCommand('default-gallery'));

        $neutralFirst = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 100),
        );
        $neutralSecond = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 101),
        );
        $languageOnly = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                102,
                new CategoryImageAssignmentScopeDTO('en-US'),
            ),
        );
        $platformOnly = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                103,
                new CategoryImageAssignmentScopeDTO(null, 'web'),
            ),
        );
        $roleScoped = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                104,
                new CategoryImageAssignmentScopeDTO('en-US', 'web', $roleId),
            ),
        );

        self::assertSame(0, $this->defaultCount($connection));
        self::assertFalse($imageService->getByIdForManagement($neutralFirst)->isDefault);

        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($neutralFirst));
        self::assertSame($neutralFirst, $this->defaultIdForScope($connection, $categoryId, null, null, null));
        self::assertTrue($imageService->getByIdForManagement($neutralFirst)->isDefault);
        $hydratedAssignment = $mutationReader->findImageAssignmentById($neutralFirst);
        self::assertNotNull($hydratedAssignment);
        self::assertTrue($hydratedAssignment->isDefault);
        $visibleDefaultId = null;
        foreach ($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO()) as $assignment) {
            if ($assignment->isDefault) {
                $visibleDefaultId = $assignment->id;
            }
        }
        self::assertSame($neutralFirst, $visibleDefaultId);

        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($neutralSecond));
        self::assertSame($neutralSecond, $this->defaultIdForScope($connection, $categoryId, null, null, null));
        self::assertFalse($imageService->getByIdForManagement($neutralFirst)->isDefault);
        self::assertTrue($imageService->getByIdForManagement($neutralSecond)->isDefault);

        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($languageOnly));
        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($platformOnly));
        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($roleScoped));
        self::assertSame(4, $this->defaultCount($connection));
        self::assertSame($languageOnly, $this->defaultIdForScope($connection, $categoryId, 'en-US', null, null));
        self::assertSame($platformOnly, $this->defaultIdForScope($connection, $categoryId, null, 'web', null));
        self::assertSame($roleScoped, $this->defaultIdForScope($connection, $categoryId, 'en-US', 'web', $roleId));

        $imageService->clearDefault(new ClearCategoryImageAssignmentDefaultCommand($neutralSecond));
        self::assertSame(3, $this->defaultCount($connection));
        self::assertNull($this->defaultIdForScope($connection, $categoryId, null, null, null));

        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($neutralFirst));
        $imageService->reorder(
            new UpdateCategoryImageAssignmentDisplayOrderCommand($neutralFirst, 2),
        );
        self::assertSame(
            [$neutralSecond, $neutralFirst],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO())),
        );
        self::assertTrue($imageService->getByIdForManagement($neutralFirst)->isDefault);
        self::assertSame(4, $this->defaultCount($connection));
    }

    public function testSoftDeleteClearsDefaultWithoutPromotionAndRestoreRemainsNonDefault(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $clock = new FixedCategoryClock();
        $imageService = $this->imageService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-default-lifecycle-category'));
        $firstId = $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 200));
        $secondId = $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 201));

        $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($firstId));
        $imageService->remove(new SoftDeleteCategoryImageAssignmentCommand($firstId));

        self::assertSame(0, $this->defaultCount($connection));
        self::assertFalse(
            $imageService->getByIdForManagement($firstId, CategoryDeletedStateEnum::DELETED_ONLY)->isDefault,
        );
        self::assertFalse($imageService->getByIdForManagement($secondId)->isDefault);
        self::assertSame(
            [$secondId],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO())),
        );

        try {
            $imageService->setDefault(new SetCategoryImageAssignmentDefaultCommand($firstId));
            self::fail('A soft-deleted assignment must not be eligible for a default.');
        } catch (CategoryImageAssignmentNotFoundException) {
        }

        $imageService->restore(new RestoreCategoryImageAssignmentCommand($firstId));
        self::assertFalse($imageService->getByIdForManagement($firstId)->isDefault);
        self::assertSame(0, $this->defaultCount($connection));
    }

    public function testAllFourScopesHaveIndependentOrderingAndExactVisibleReads(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $imageService = $this->imageService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-scopes-category'));

        $neutralFirst = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 100),
        );
        $neutralSecond = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 101),
        );
        $languageOnly = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                100,
                new CategoryImageAssignmentScopeDTO('en-US'),
            ),
        );
        $platformOnly = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                100,
                new CategoryImageAssignmentScopeDTO(null, 'web'),
            ),
        );
        $languageAndPlatform = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                100,
                new CategoryImageAssignmentScopeDTO('en-US', 'web'),
            ),
        );

        self::assertSame(
            [$neutralFirst, $neutralSecond],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO())),
        );
        self::assertSame(
            [$languageOnly],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO('en-US'))),
        );
        self::assertSame(
            [$platformOnly],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO(null, 'web'))),
        );
        self::assertSame(
            [$languageAndPlatform],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO('en-US', 'web'))),
        );

        $imageService->reorder(
            new UpdateCategoryImageAssignmentDisplayOrderCommand($neutralSecond, 1),
        );
        self::assertSame(
            [$neutralSecond, $neutralFirst],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO())),
        );
    }

    public function testSoftDeleteRestoreAndStableIdentityAreIndependentFromParentVisibility(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $clock = new FixedCategoryClock();
        $imageService = $this->imageService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-lifecycle-category'));
        $assignmentId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                300,
                new CategoryImageAssignmentScopeDTO('en-US', 'web'),
            ),
        );

        $imageService->remove(new SoftDeleteCategoryImageAssignmentCommand($assignmentId));
        self::assertTrue(
            $imageService->listVisibleForCategory(
                $categoryId,
                new CategoryImageAssignmentScopeDTO('en-US', 'web'),
            )->isEmpty(),
        );
        self::assertNotNull(
            $imageService->getByIdForManagement($assignmentId, CategoryDeletedStateEnum::DELETED_ONLY)->deletedAt,
        );

        $this->expectException(CategoryImageAssignmentAlreadyExistsException::class);
        $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                300,
                new CategoryImageAssignmentScopeDTO('en-US', 'web'),
            ),
        );
    }

    public function testDeletedAssignmentCanBeRestoredAndInactiveParentDoesNotBlockCreate(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $imageService = $this->imageService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-parent-state-category'));
        $assignmentId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 301),
        );

        $imageService->remove(new SoftDeleteCategoryImageAssignmentCommand($assignmentId));
        $imageService->restore(new RestoreCategoryImageAssignmentCommand($assignmentId));
        self::assertSame(
            [$assignmentId],
            $this->ids($imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO())),
        );

        $service->updateStatus(new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::INACTIVE));
        $inactiveParentAssignment = $imageService->assign(
            new CreateCategoryImageAssignmentCommand($categoryId, 302),
        );
        self::assertTrue(
            $imageService->listVisibleForCategory($categoryId, new CategoryImageAssignmentScopeDTO())->isEmpty(),
        );
        self::assertSame(
            [$assignmentId, $inactiveParentAssignment],
            $this->ids(
                $imageService->listForManagement(new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId)),
            ),
        );

        $service->softDelete(new SoftDeleteCategoryCommand($categoryId));
        $imageService->reorder(
            new UpdateCategoryImageAssignmentDisplayOrderCommand($inactiveParentAssignment, 1),
        );
        $imageService->remove(new SoftDeleteCategoryImageAssignmentCommand($inactiveParentAssignment));
        $imageService->restore(new RestoreCategoryImageAssignmentCommand($inactiveParentAssignment));
        $this->expectException(CategoryNotFoundException::class);
        $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 303));
    }

    public function testVisibleReadsUseExactScopeWithoutFallbackAndRespectAncestors(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $imageService = $this->imageService($connection);
        $rootId = $service->create(new CreateCategoryCommand('image-visibility-root'));
        $childId = $service->create(new CreateCategoryCommand('image-visibility-child', $rootId));
        $assignmentId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $childId,
                400,
                new CategoryImageAssignmentScopeDTO('en-US', 'web'),
            ),
        );

        self::assertSame(
            [$assignmentId],
            $this->ids($imageService->listVisibleForCategory($childId, new CategoryImageAssignmentScopeDTO('en-US', 'web'))),
        );
        self::assertTrue(
            $imageService->listVisibleForCategory($childId, new CategoryImageAssignmentScopeDTO('en-GB', 'web'))->isEmpty(),
        );
        self::assertTrue(
            $imageService->listVisibleForCategory($childId, new CategoryImageAssignmentScopeDTO())->isEmpty(),
        );

        $service->updateStatus(new UpdateCategoryStatusCommand($rootId, CategoryStatusEnum::INACTIVE));
        self::assertTrue(
            $imageService->listVisibleForCategory($childId, new CategoryImageAssignmentScopeDTO('en-US', 'web'))->isEmpty(),
        );
    }

    public function testManagementPaginationPreservesExactScopeOrderingByDefault(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $imageService = $this->imageService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-pagination-category'));
        $neutralId = $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 600));
        $localizedId = $imageService->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                601,
                new CategoryImageAssignmentScopeDTO('en-US'),
            ),
        );
        $neutralSecondId = $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 602));

        $page = $imageService->paginateForManagement(
            new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
            new PageRequest(perPage: 3),
        );
        $ids = [];
        foreach ($page->data as $assignment) {
            $ids[] = $assignment->id;
        }

        self::assertSame('business_order', $page->sortBy);
        self::assertSame([$localizedId, $neutralId, $neutralSecondId], $ids);

        $categorySortedPage = $imageService->paginateForManagement(
            new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
            new PageRequest(perPage: 3, sortBy: 'category_id'),
        );
        $categorySortedIds = [];
        foreach ($categorySortedPage->data as $assignment) {
            $categorySortedIds[] = $assignment->id;
        }

        self::assertSame('category_id', $categorySortedPage->sortBy);
        self::assertSame([$neutralId, $localizedId, $neutralSecondId], $categorySortedIds);
    }

    public function testManagementReadsDistinguishNoScopeFilterFromExactNullScope(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $imageService = $this->imageService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('image-management-category'));
        $neutral = $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 500));
        $neutralSecond = $imageService->assign(new CreateCategoryImageAssignmentCommand($categoryId, 501));
        $imageService->assign(new CreateCategoryImageAssignmentCommand(
            $categoryId,
            502,
            new CategoryImageAssignmentScopeDTO('en-US'),
        ));
        $imageService->assign(new CreateCategoryImageAssignmentCommand(
            $categoryId,
            503,
            new CategoryImageAssignmentScopeDTO(null, 'web'),
        ));

        self::assertSame(
            4,
            $imageService->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
            )->count(),
        );
        self::assertSame(
            [$neutral, $neutralSecond],
            $this->ids($imageService->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(
                    categoryId: $categoryId,
                    scope: new CategoryImageAssignmentScopeDTO(),
                ),
            )),
        );
    }

    /** @return list<int> */
    private function ids(\Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO $assignments): array
    {
        $ids = [];
        foreach ($assignments as $assignment) {
            $ids[] = $assignment->id;
        }

        return $ids;
    }

    /** @param array<string, mixed> $row */
    private function integerFromRow(array $row, string $key, int $default = 0): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }

    private function commandService(PDO $connection): CategoryApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        )->categories();
    }

    private function imageService(PDO $connection): ImageAssignmentApiInterface
    {
        return CategoryFactory::create($connection, new FixedCategoryClock())->images();
    }

    private function roleService(PDO $connection): ImageRoleApiInterface
    {
        return CategoryFactory::create($connection, new FixedCategoryClock())->imageRoles();
    }

    private function queryReader(PDO $connection, FixedCategoryClock $clock): PdoCategoryImageAssignmentQueryReader
    {
        return new PdoCategoryImageAssignmentQueryReader($connection, $clock);
    }

    private function defaultCount(PDO $connection): int
    {
        $statement = $connection->query(
            'SELECT COUNT(*) FROM `maa_category_category_image_assignments` '
            . 'WHERE `deleted_at` IS NULL AND `is_default` = 1',
        );

        if ($statement === false) {
            self::fail('Unable to count active default Image Assignments.');
        }

        return (int) $statement->fetchColumn();
    }

    private function defaultIdForScope(
        PDO $connection,
        int $categoryId,
        ?string $languageCode,
        ?string $platform,
        ?int $roleId,
    ): ?int {
        $statement = $connection->prepare(
            'SELECT `id` FROM `maa_category_category_image_assignments` '
            . 'WHERE `category_id` = :category_id '
            . 'AND `language_code` <=> :language_code '
            . 'AND `platform` <=> :platform '
            . 'AND `role_id` <=> :role_id '
            . 'AND `deleted_at` IS NULL AND `is_default` = 1 LIMIT 1',
        );
        $statement->execute([
            'category_id' => $categoryId,
            'language_code' => $languageCode,
            'platform' => $platform,
            'role_id' => $roleId,
        ]);
        $id = $statement->fetchColumn();

        if ($id === false) {
            return null;
        }
        if (!is_int($id) && !is_string($id)) {
            self::fail('The default assignment identity must be scalar.');
        }

        return (int) $id;
    }
}
