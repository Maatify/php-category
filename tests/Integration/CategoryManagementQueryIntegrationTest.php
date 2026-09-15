<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Content\Exception\CategoryContentNotFoundException;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use PDO;

final class CategoryManagementQueryIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testCategoryManagementReadsFilterStateAndStatusWithoutConsumerAncestorVisibility(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $contentService = $this->contentService($connection);
        $activeId = $commandService->create(new CreateCategoryCommand('management-active'));
        $inactiveParentId = $commandService->create(new CreateCategoryCommand('management-inactive-parent'));
        $childId = $commandService->create(new CreateCategoryCommand('management-child', $inactiveParentId));
        $deletedId = $commandService->create(new CreateCategoryCommand('management-deleted'));

        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveParentId, CategoryStatusEnum::INACTIVE));
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedId));
        $this->setDisplayOrder($connection, $activeId, 1);
        $this->setDisplayOrder($connection, $inactiveParentId, 1);
        $this->setDisplayOrder($connection, $childId, 2);
        $this->setDisplayOrder($connection, $deletedId, 3);

        $service = $this->categoryService($connection);

        self::assertSame(
            [$activeId, $inactiveParentId, $childId],
            $this->categoryIds($service->listForManagement(new CategoryListCriteriaDTO())),
        );
        self::assertSame(
            [$inactiveParentId],
            $this->categoryIds($service->listForManagement(new CategoryListCriteriaDTO(
                status: CategoryStatusEnum::INACTIVE,
            ))),
        );
        self::assertSame(
            [$deletedId],
            $this->categoryIds($service->listForManagement(new CategoryListCriteriaDTO(
                deletedState: CategoryDeletedStateEnum::DELETED_ONLY,
            ))),
        );
        self::assertSame(
            [$activeId, $inactiveParentId, $childId, $deletedId],
            $this->categoryIds($service->listForManagement(new CategoryListCriteriaDTO(
                deletedState: CategoryDeletedStateEnum::INCLUDE_DELETED,
                maxResults: 4,
            ))),
        );

        self::assertSame(
            $inactiveParentId,
            $service->getByIdForManagement($inactiveParentId)->id,
        );
        self::assertSame($childId, $service->getByIdForManagement($childId)->id);
        self::assertSame(
            $deletedId,
            $service->getByIdForManagement($deletedId, CategoryDeletedStateEnum::DELETED_ONLY)->id,
        );
        $this->expectException(CategoryNotFoundException::class);
        $service->getByIdForManagement($deletedId);
    }

    public function testRootAndChildListsAreBoundedAndOrderedByDisplayOrderThenId(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $parentId = $commandService->create(new CreateCategoryCommand('management-list-parent'));
        $firstId = $commandService->create(new CreateCategoryCommand('management-list-first', $parentId));
        $secondId = $commandService->create(new CreateCategoryCommand('management-list-second', $parentId));
        $thirdId = $commandService->create(new CreateCategoryCommand('management-list-third', $parentId));
        $this->setDisplayOrder($connection, $firstId, 1);
        $this->setDisplayOrder($connection, $secondId, 1);
        $this->setDisplayOrder($connection, $thirdId, 2);

        $service = $this->categoryService($connection);
        $criteria = new CategoryListCriteriaDTO(maxResults: 2);

        self::assertSame([$parentId], $this->categoryIds($service->listRootCategoriesForManagement($criteria)));
        self::assertSame(
            [$firstId, $secondId],
            $this->categoryIds($service->listChildrenForManagement($parentId, $criteria)),
        );
    }

    public function testPublicRootAndChildManagementPaginationUsesRealMySqlQueries(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $firstRootId = $commandService->create(new CreateCategoryCommand('management-pagination-root-first'));
        $secondRootId = $commandService->create(new CreateCategoryCommand('management-pagination-root-second'));
        $firstChildId = $commandService->create(
            new CreateCategoryCommand('management-pagination-child-first', $firstRootId),
        );
        $secondChildId = $commandService->create(
            new CreateCategoryCommand('management-pagination-child-second', $firstRootId),
        );
        $deletedChildId = $commandService->create(
            new CreateCategoryCommand('management-pagination-child-deleted', $firstRootId),
        );
        $this->setDisplayOrder($connection, $firstRootId, 2);
        $this->setDisplayOrder($connection, $secondRootId, 1);
        $this->setDisplayOrder($connection, $firstChildId, 2);
        $this->setDisplayOrder($connection, $secondChildId, 1);
        $this->setDisplayOrder($connection, $deletedChildId, 3);
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedChildId));

        $service = $this->categoryService($connection);
        $rootPage = $service->paginateRootCategoriesForManagement(
            new CategoryListCriteriaDTO(deletedState: CategoryDeletedStateEnum::NON_DELETED),
            new PageRequest(page: 1, perPage: 1),
        );
        self::assertSame(2, $rootPage->total);
        self::assertSame(2, $rootPage->filtered);
        self::assertSame(2, $rootPage->totalPages);
        self::assertTrue($rootPage->hasNext);
        self::assertFalse($rootPage->hasPrevious);
        self::assertCount(1, $rootPage->data);
        self::assertSame($secondRootId, $rootPage->data[0]->id);

        $childPage = $service->paginateChildrenForManagement(
            $firstRootId,
            new CategoryListCriteriaDTO(deletedState: CategoryDeletedStateEnum::NON_DELETED),
            new PageRequest(page: 2, perPage: 1),
        );
        self::assertSame(2, $childPage->total);
        self::assertSame(2, $childPage->filtered);
        self::assertSame(2, $childPage->page);
        self::assertFalse($childPage->hasNext);
        self::assertTrue($childPage->hasPrevious);
        self::assertCount(1, $childPage->data);
        self::assertSame($firstChildId, $childPage->data[0]->id);

        $deletedChildPage = $service->paginateChildrenForManagement(
            $firstRootId,
            new CategoryListCriteriaDTO(deletedState: CategoryDeletedStateEnum::DELETED_ONLY),
            new PageRequest(perPage: 1),
        );
        self::assertSame(1, $deletedChildPage->total);
        self::assertSame(1, $deletedChildPage->filtered);
        self::assertSame($deletedChildId, $deletedChildPage->data[0]->id);
    }

    public function testCategoryManagementPaginationSearchAndCodeLookupUseThePublicApi(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $firstId = $commandService->create(new CreateCategoryCommand('stage3-alpha'));
        $secondId = $commandService->create(new CreateCategoryCommand('stage3-beta'));
        $deletedId = $commandService->create(new CreateCategoryCommand('stage3-deleted'));
        $literalPercentId = $commandService->create(new CreateCategoryCommand('literal%code'));
        $percentWildcardId = $commandService->create(new CreateCategoryCommand('literalXcode'));
        $literalUnderscoreId = $commandService->create(new CreateCategoryCommand('literal_code'));
        $underscoreWildcardId = $commandService->create(new CreateCategoryCommand('literalYcode'));
        $literalBackslashId = $commandService->create(new CreateCategoryCommand('literal\\code'));
        $backslashWildcardId = $commandService->create(new CreateCategoryCommand('literalcode'));
        $this->setDisplayOrder($connection, $firstId, 1);
        $this->setDisplayOrder($connection, $secondId, 2);
        $this->setDisplayOrder($connection, $deletedId, 3);
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedId));

        self::assertSame($firstId, $commandService->getByCode('stage3-alpha')->id);
        self::assertSame(
            $deletedId,
            $commandService->getByCode('stage3-deleted', CategoryDeletedStateEnum::DELETED_ONLY)->id,
        );

        $page = $commandService->paginateForManagement(
            new CategoryListCriteriaDTO(search: 'stage3-', deletedState: CategoryDeletedStateEnum::NON_DELETED),
            new PageRequest(page: 2, perPage: 1, sortBy: 'code', sortDirection: 'ASC'),
        );
        self::assertSame(8, $page->total);
        self::assertSame(2, $page->filtered);
        self::assertSame(2, $page->page);
        self::assertSame(2, $page->totalPages);
        self::assertFalse($page->hasNext);
        self::assertTrue($page->hasPrevious);
        self::assertCount(1, $page->data);
        self::assertSame($secondId, $page->data[0]->id);

        $percentSearchPage = $commandService->paginateForManagement(
            new CategoryListCriteriaDTO(search: 'literal%code'),
            new PageRequest(perPage: 10),
        );
        self::assertSame(1, $percentSearchPage->filtered);
        self::assertSame($literalPercentId, $percentSearchPage->data[0]->id);
        self::assertNotSame($percentWildcardId, $percentSearchPage->data[0]->id);

        $underscoreSearchPage = $commandService->paginateForManagement(
            new CategoryListCriteriaDTO(search: 'literal_code'),
            new PageRequest(perPage: 10),
        );
        self::assertSame(1, $underscoreSearchPage->filtered);
        self::assertSame($literalUnderscoreId, $underscoreSearchPage->data[0]->id);
        self::assertNotSame($underscoreWildcardId, $underscoreSearchPage->data[0]->id);

        $backslashSearchPage = $commandService->paginateForManagement(
            new CategoryListCriteriaDTO(search: 'literal\\code'),
            new PageRequest(perPage: 10),
        );
        self::assertSame(1, $backslashSearchPage->filtered);
        self::assertSame($literalBackslashId, $backslashSearchPage->data[0]->id);
        self::assertNotSame($backslashWildcardId, $backslashSearchPage->data[0]->id);

        $this->expectException(CategoryNotFoundException::class);
        $commandService->getByCode('stage3-deleted');
    }

    public function testContentManagementReadsFilterByCategoryAndDeletedState(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $contentService = $this->contentService($connection);
        $categoryId = $commandService->create(new CreateCategoryCommand('management-contents'));
        $otherCategoryId = $commandService->create(new CreateCategoryCommand('management-other-contents'));
        $englishId = $contentService->create(
            new CreateCategoryContentCommand($categoryId, 'en-US', 'Shirts', null),
        );
        $arabicId = $contentService->create(
            new CreateCategoryContentCommand($categoryId, 'ar-EG', 'قمصان', null),
        );
        $deletedId = $contentService->create(
            new CreateCategoryContentCommand($categoryId, 'fr-FR', 'Chemises', null),
        );
        $contentService->create(
            new CreateCategoryContentCommand($otherCategoryId, 'en-US', 'Other', null),
        );
        $contentService->softDelete(new SoftDeleteCategoryContentCommand($deletedId));

        $service = $this->contentService($connection);
        $categoryCriteria = new CategoryContentListCriteriaDTO(categoryId: $categoryId);
        $activeIds = $this->contentIds($service->listForManagement($categoryCriteria));

        self::assertSame([$arabicId, $englishId], $activeIds);
        $page = $service->paginateForManagement(
            new CategoryContentListCriteriaDTO(categoryId: $categoryId),
            new PageRequest(page: 1, perPage: 1, sortBy: 'language_code', sortDirection: 'ASC'),
        );
        self::assertSame(2, $page->total);
        self::assertSame(2, $page->filtered);
        self::assertCount(1, $page->data);
        self::assertSame($arabicId, $page->data[0]->id);
        self::assertSame(
            [$deletedId],
            $this->contentIds($service->listForManagement(new CategoryContentListCriteriaDTO(
                categoryId: $categoryId,
                deletedState: CategoryDeletedStateEnum::DELETED_ONLY,
            ))),
        );
        self::assertSame(
            [$arabicId, $englishId, $deletedId],
            $this->contentIds($service->listForManagement(new CategoryContentListCriteriaDTO(
                categoryId: $categoryId,
                deletedState: CategoryDeletedStateEnum::INCLUDE_DELETED,
            ))),
        );
        self::assertSame($deletedId, $service->getByIdForManagement(
            $deletedId,
            CategoryDeletedStateEnum::DELETED_ONLY,
        )->id);

        $this->expectException(CategoryContentNotFoundException::class);
        $service->getByIdForManagement($deletedId);
    }

    /** @return list<int> */
    private function categoryIds(\Maatify\Category\Query\DTO\CategoryCollectionDTO $categories): array
    {
        $ids = [];
        foreach ($categories as $category) {
            $ids[] = $category->id;
        }

        return $ids;
    }

    /** @return list<int> */
    private function contentIds(\Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO $contents): array
    {
        $ids = [];
        foreach ($contents as $content) {
            $ids[] = $content->id;
        }

        return $ids;
    }

    private function commandService(PDO $connection): CategoryApiInterface
    {
        return $this->categoryService($connection);
    }

    private function categoryService(PDO $connection): CategoryApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        )->categories();
    }

    private function contentService(PDO $connection): ContentApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        )->contents();
    }

    private function setDisplayOrder(PDO $connection, int $categoryId, int $displayOrder): void
    {
        $statement = $connection->prepare(
            'UPDATE `maa_category_categories` '
            . 'SET `display_order` = :display_order WHERE `id` = :category_id',
        );
        $statement->execute([
            'display_order' => $displayOrder,
            'category_id' => $categoryId,
        ]);
    }
}
