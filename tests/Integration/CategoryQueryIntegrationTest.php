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
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Query\Infrastructure\PdoCategoryQueryReader;
use Maatify\Category\Query\Infrastructure\PdoCategoryReadQuery;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use PDO;

final class CategoryQueryIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testVisibleIdentityAndRootListExcludeDeletedOrInactiveRowsAndUseDisplayOrderThenId(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $contentService = $this->contentService($connection);
        $firstId = $commandService->create(new CreateCategoryCommand('root-first'));
        $secondId = $commandService->create(new CreateCategoryCommand('root-second'));
        $thirdId = $commandService->create(new CreateCategoryCommand('root-third'));
        $inactiveId = $commandService->create(new CreateCategoryCommand('root-inactive'));
        $deletedId = $commandService->create(new CreateCategoryCommand('root-deleted'));

        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveId, CategoryStatusEnum::INACTIVE));
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedId));
        $this->setDisplayOrder($connection, $firstId, 2);
        $this->setDisplayOrder($connection, $secondId, 1);
        $this->setDisplayOrder($connection, $thirdId, 1);
        $this->setDisplayOrder($connection, $inactiveId, 3);
        $this->setDisplayOrder($connection, $deletedId, 3);

        $reader = new PdoCategoryReadQuery($connection, new FixedCategoryClock());
        $queryService = $this->categoryQuery($connection);

        self::assertSame($secondId, $queryService->getById($secondId)->id);
        self::assertNull($reader->findVisibleById($inactiveId));
        self::assertNull($reader->findVisibleById($deletedId));
        self::assertSame([$secondId, $thirdId, $firstId], $this->categoryIds($queryService->listRootCategories()));
    }

    public function testConsumerVisibilityListsAreBoundedAndRetainVisibilityOrdering(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $contentService = $this->contentService($connection);
        $firstRootId = $commandService->create(new CreateCategoryCommand('bounded-root-first'));
        $secondRootId = $commandService->create(new CreateCategoryCommand('bounded-root-second'));
        $thirdRootId = $commandService->create(new CreateCategoryCommand('bounded-root-third'));
        $inactiveRootId = $commandService->create(new CreateCategoryCommand('bounded-root-inactive'));
        $deletedRootId = $commandService->create(new CreateCategoryCommand('bounded-root-deleted'));
        $firstChildId = $commandService->create(new CreateCategoryCommand('bounded-child-first', $firstRootId));
        $secondChildId = $commandService->create(new CreateCategoryCommand('bounded-child-second', $firstRootId));
        $thirdChildId = $commandService->create(new CreateCategoryCommand('bounded-child-third', $firstRootId));
        $inactiveChildId = $commandService->create(new CreateCategoryCommand('bounded-child-inactive', $firstRootId));
        $deletedChildId = $commandService->create(new CreateCategoryCommand('bounded-child-deleted', $firstRootId));

        $contentService->create(
            new CreateCategoryContentCommand($firstRootId, null, 'Bounded', null),
        );
        $contentService->create(
            new CreateCategoryContentCommand($firstRootId, 'en-US', 'Bounded English', null),
        );
        $contentService->create(
            new CreateCategoryContentCommand($firstRootId, 'ar-EG', 'محدود', null),
        );
        $deletedContentId = $contentService->create(
            new CreateCategoryContentCommand($firstRootId, 'fr-FR', 'Limite', null),
        );

        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveRootId, CategoryStatusEnum::INACTIVE));
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedRootId));
        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveChildId, CategoryStatusEnum::INACTIVE));
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedChildId));
        $contentService->softDelete(new SoftDeleteCategoryContentCommand($deletedContentId));

        $this->setDisplayOrder($connection, $firstRootId, 1);
        $this->setDisplayOrder($connection, $secondRootId, 1);
        $this->setDisplayOrder($connection, $thirdRootId, 2);
        $this->setDisplayOrder($connection, $inactiveRootId, 3);
        $this->setDisplayOrder($connection, $deletedRootId, 3);
        $this->setDisplayOrder($connection, $firstChildId, 1);
        $this->setDisplayOrder($connection, $secondChildId, 1);
        $this->setDisplayOrder($connection, $thirdChildId, 2);

        $queryService = $this->categoryQuery($connection);
        $bounded = new CategoryVisibleListCriteriaDTO(2);
        $defaultBound = new CategoryVisibleListCriteriaDTO();

        self::assertSame(
            [$firstRootId, $secondRootId],
            $this->categoryIds($queryService->listRootCategories($bounded)),
        );
        self::assertSame(
            [$firstChildId, $secondChildId],
            $this->categoryIds($queryService->listChildren($firstRootId, $bounded)),
        );
        self::assertSame(
            [null, 'ar-EG'],
            $this->contentLanguages($contentService->listVisibleForCategory($firstRootId, $bounded)),
        );

        $visibleRoots = $this->categoryIds($queryService->listRootCategories($defaultBound));
        self::assertSame([$firstRootId, $secondRootId, $thirdRootId], $visibleRoots);
        self::assertNotContains($inactiveRootId, $visibleRoots);
        self::assertNotContains($deletedRootId, $visibleRoots);

        $visibleChildren = $this->categoryIds($queryService->listChildren($firstRootId, $defaultBound));
        self::assertSame([$firstChildId, $secondChildId, $thirdChildId], $visibleChildren);
        self::assertNotContains($inactiveChildId, $visibleChildren);
        self::assertNotContains($deletedChildId, $visibleChildren);

        self::assertSame(
            [null, 'ar-EG', 'en-US'],
            $this->contentLanguages($contentService->listVisibleForCategory($firstRootId, $defaultBound)),
        );

        $commandService->updateStatus(new UpdateCategoryStatusCommand($firstRootId, CategoryStatusEnum::INACTIVE));
        self::assertSame([], $this->categoryIds($queryService->listChildren($firstRootId, $defaultBound)));
        self::assertTrue($contentService->listVisibleForCategory($firstRootId, $defaultBound)->isEmpty());
    }

    public function testChildrenAreOrderedAndHiddenWhenAnyAncestorIsInactive(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $contentService = $this->contentService($connection);
        $activeRootId = $commandService->create(new CreateCategoryCommand('active-root'));
        $inactiveRootId = $commandService->create(new CreateCategoryCommand('inactive-root'));
        $firstChildId = $commandService->create(new CreateCategoryCommand('first-child', $activeRootId));
        $secondChildId = $commandService->create(new CreateCategoryCommand('second-child', $activeRootId));
        $inactiveChildId = $commandService->create(new CreateCategoryCommand('inactive-child', $activeRootId));
        $deletedChildId = $commandService->create(new CreateCategoryCommand('deleted-child', $activeRootId));
        $grandchildId = $commandService->create(new CreateCategoryCommand('grandchild', $firstChildId));

        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveRootId, CategoryStatusEnum::INACTIVE));
        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveChildId, CategoryStatusEnum::INACTIVE));
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedChildId));
        $this->setDisplayOrder($connection, $firstChildId, 1);
        $this->setDisplayOrder($connection, $secondChildId, 1);
        $this->setDisplayOrder($connection, $inactiveChildId, 3);
        $this->setDisplayOrder($connection, $deletedChildId, 3);

        $reader = new PdoCategoryReadQuery($connection, new FixedCategoryClock());
        $queryService = $this->categoryQuery($connection);

        self::assertSame(
            [$firstChildId, $secondChildId],
            $this->categoryIds($queryService->listChildren($activeRootId)),
        );
        self::assertSame([], $this->categoryIds($queryService->listChildren($inactiveRootId)));
        self::assertSame($grandchildId, $queryService->getById($grandchildId)->id);

        $commandService->updateStatus(new UpdateCategoryStatusCommand($activeRootId, CategoryStatusEnum::INACTIVE));

        self::assertSame([], $this->categoryIds($queryService->listChildren($activeRootId)));
        self::assertNull($reader->findVisibleById($grandchildId));
    }

    public function testContentsExcludeSoftDeletedRowsAndInvisibleCategoryPaths(): void
    {
        $connection = $this->connection();
        $commandService = $this->commandService($connection);
        $contentService = $this->contentService($connection);
        $visibleId = $commandService->create(new CreateCategoryCommand('translated-category'));
        $inactiveId = $commandService->create(new CreateCategoryCommand('translated-inactive'));
        $deletedId = $commandService->create(new CreateCategoryCommand('translated-deleted'));

        $contentService->create(
            new CreateCategoryContentCommand($visibleId, 'en-US', 'Shirts', null),
        );
        $contentService->create(
            new CreateCategoryContentCommand($visibleId, 'ar-EG', 'قمصان', 'وصف'),
        );
        $deletedVisibleContentId = $contentService->create(
            new CreateCategoryContentCommand($visibleId, 'fr-FR', 'Chemises', null),
        );
        $contentService->create(
            new CreateCategoryContentCommand($inactiveId, 'en-US', 'Inactive', null),
        );
        $deletedCategoryContentId = $contentService->create(
            new CreateCategoryContentCommand($deletedId, 'en-US', 'Deleted', null),
        );

        $commandService->updateStatus(new UpdateCategoryStatusCommand($inactiveId, CategoryStatusEnum::INACTIVE));
        $commandService->softDelete(new SoftDeleteCategoryCommand($deletedId));
        $contentService->softDelete(
            new SoftDeleteCategoryContentCommand($deletedVisibleContentId),
        );
        $contentService->softDelete(
            new SoftDeleteCategoryContentCommand($deletedCategoryContentId),
        );

        $visibleContents = $contentService->listVisibleForCategory($visibleId);
        $languages = [];
        foreach ($visibleContents as $content) {
            $languages[] = $content->languageCode;
        }

        self::assertSame(['ar-EG', 'en-US'], $languages);
        self::assertTrue($contentService->listVisibleForCategory($inactiveId)->isEmpty());
        self::assertTrue($contentService->listVisibleForCategory($deletedId)->isEmpty());
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

    /** @return list<?string> */
    private function contentLanguages(\Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO $contents): array
    {
        $languages = [];
        foreach ($contents as $content) {
            $languages[] = $content->languageCode;
        }

        return $languages;
    }

    private function commandService(PDO $connection): CategoryApiInterface
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

    private function categoryQuery(PDO $connection): CategoryApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        )->categories();
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
