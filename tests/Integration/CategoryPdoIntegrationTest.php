<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Hierarchy\Exception\CategoryCycleException;
use Maatify\Category\Lifecycle\Exception\CategoryHasNonDeletedChildrenException;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Content\Mutation\Exception\CategoryContentAlreadyExistsException;
use Maatify\Category\Content\Exception\CategoryContentNotFoundException;
use Maatify\Category\Query\Infrastructure\PdoCategoryManagementReadQuery;
use Maatify\Category\Query\Infrastructure\PdoCategoryQueryReader;
use Maatify\Category\Query\Infrastructure\PdoCategoryReadQuery;
use Maatify\Category\Content\Query\Infrastructure\PdoCategoryContentManagementReadQuery;
use Maatify\Category\Content\Query\Infrastructure\PdoCategoryContentQueryReader;
use Maatify\Category\Content\Query\Infrastructure\PdoCategoryContentReadQuery;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class CategoryPdoIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testHostClockTimezoneIsPreservedAcrossPersistenceAndAllHydrationAdapters(): void
    {
        $connection = $this->connection();
        $createClock = new FixedCategoryClock('2026-03-01 14:30:45 Africa/Cairo');
        $createService = $this->service($connection, $createClock);
        $categoryId = $createService->create(new CreateCategoryCommand('host-timezone-category'));
        $contentId = $this->contentService($connection, $createClock)->create(
            new CreateCategoryContentCommand($categoryId, null, 'Host clock content', null),
        );

        $updateClock = new FixedCategoryClock('2026-03-01 15:45:12 Africa/Cairo');
        $updateService = $this->service($connection, $updateClock);
        $this->contentService($connection, $updateClock)->update(new UpdateCategoryContentCommand(
            $contentId,
            'Updated host clock content',
            null,
        ));

        $timestamps = $connection->prepare(
            'SELECT `created_at`, `updated_at` FROM `maa_category_category_contents` WHERE `id` = :id',
        );
        $timestamps->execute(['id' => $contentId]);
        /** @var array{created_at: string, updated_at: string}|false $storedTimestamps */
        $storedTimestamps = $timestamps->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($storedTimestamps);
        self::assertSame('2026-03-01 14:30:45', $storedTimestamps['created_at']);
        self::assertSame('2026-03-01 15:45:12', $storedTimestamps['updated_at']);

        $internalReader = new PdoCategoryQueryReader($connection, $updateClock);
        $visibleReader = new PdoCategoryReadQuery($connection, $updateClock);
        $managementReader = new PdoCategoryManagementReadQuery($connection, $updateClock);
        $internalContentReader = new PdoCategoryContentQueryReader($connection, $updateClock);
        $visibleContentReader = new PdoCategoryContentReadQuery($connection, $updateClock);
        $managementContentReader = new PdoCategoryContentManagementReadQuery($connection, $updateClock);

        $internalCategory = $internalReader->findById($categoryId);
        $visibleCategory = $visibleReader->findVisibleById($categoryId);
        $managementCategory = $managementReader->findById(
            $categoryId,
            CategoryDeletedStateEnum::NON_DELETED,
        );
        self::assertNotNull($internalCategory);
        self::assertNotNull($visibleCategory);
        self::assertNotNull($managementCategory);
        foreach ([$internalCategory, $visibleCategory, $managementCategory] as $category) {
            self::assertSame('Africa/Cairo', $category->createdAt->getTimezone()->getName());
            self::assertSame('2026-03-01 14:30:45', $category->createdAt->format('Y-m-d H:i:s'));
        }

        $internalContent = $internalContentReader->findContentById($contentId);
        $visibleContents = $visibleContentReader->listVisibleContents($categoryId);
        $managementContent = $managementContentReader->findContentById(
            $contentId,
            CategoryDeletedStateEnum::NON_DELETED,
        );
        self::assertNotNull($internalContent);
        self::assertNotNull($managementContent);
        self::assertCount(1, $visibleContents);
        $visibleContent = null;
        foreach ($visibleContents as $content) {
            $visibleContent = $content;
        }
        self::assertNotNull($visibleContent);
        foreach ([$internalContent, $visibleContent, $managementContent] as $content) {
            self::assertSame('Africa/Cairo', $content->updatedAt->getTimezone()->getName());
            self::assertSame('2026-03-01 15:45:12', $content->updatedAt->format('Y-m-d H:i:s'));
        }
    }

    public function testApplicationClockAndAllStateReaderSupportLifecycleIdentity(): void
    {
        $clock = new FixedCategoryClock();
        $service = $this->service($this->connection(), $clock);
        $queryReader = new PdoCategoryQueryReader($this->connection(), $clock);

        $categoryId = $service->create(new CreateCategoryCommand('root-category'));
        self::assertSame(1, $categoryId);
        self::assertSame(
            '2026-01-03 00:00:00',
            $queryReader->findById($categoryId)?->createdAt->format('Y-m-d H:i:s'),
        );

        $service->softDelete(new SoftDeleteCategoryCommand($categoryId));
        $deleted = $queryReader->findById($categoryId);
        self::assertNotNull($deleted->deletedAt);
        self::assertNull($queryReader->findActiveById($categoryId));

        $service->restore(new RestoreCategoryCommand($categoryId));
        $restored = $queryReader->findById($categoryId);
        self::assertSame($categoryId, $restored->id);
        self::assertNull($restored->deletedAt);
        self::assertSame('root-category', $restored->code);
    }

    public function testCompleteAncestorChainRejectsAnIndirectCycleOnMySql(): void
    {
        $service = $this->service($this->connection(), new FixedCategoryClock());
        $a = $service->create(new CreateCategoryCommand('cycle-a'));
        $b = $service->create(new CreateCategoryCommand('cycle-b', $a));
        $c = $service->create(new CreateCategoryCommand('cycle-c', $b));

        $this->expectException(CategoryCycleException::class);
        $service->move(new MoveCategoryCommand($a, $c));
    }

    public function testCreatedRootAndChildRowsReceiveScopedPositionsAndMoveImmediately(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock());
        $firstRootId = $service->create(new CreateCategoryCommand('created-root-first'));
        $secondRootId = $service->create(new CreateCategoryCommand('created-root-second'));
        $firstChildId = $service->create(new CreateCategoryCommand('created-child-first', $firstRootId));
        $secondChildId = $service->create(new CreateCategoryCommand('created-child-second', $firstRootId));

        self::assertSame([
            $firstRootId => 1,
            $secondRootId => 2,
        ], $this->ordersForScope($connection, null));
        self::assertSame([
            $firstChildId => 1,
            $secondChildId => 2,
        ], $this->ordersForScope($connection, $firstRootId));

        $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondRootId, 1));
        $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondChildId, 1));

        self::assertSame([
            $secondRootId => 1,
            $firstRootId => 2,
        ], $this->ordersForScope($connection, null));
        self::assertSame([
            $secondChildId => 1,
            $firstChildId => 2,
        ], $this->ordersForScope($connection, $firstRootId));
    }

    public function testContentLifecycleIsPackageOwnedAndPreservesLogicalIdentity(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock('2026-01-03 00:00:00 Africa/Cairo'));
        $contentService = $this->contentService($connection, new FixedCategoryClock('2026-01-03 00:00:00 Africa/Cairo'));
        $queryReader = new PdoCategoryContentQueryReader($connection, new FixedCategoryClock());
        $categoryId = $service->create(new CreateCategoryCommand('content-lifecycle-category'));

        $contentId = $contentService->create(
            new CreateCategoryContentCommand($categoryId, null, 'Shirts', 'Base description'),
        );
        $created = $queryReader->findContentById($contentId);
        self::assertNotNull($created);
        self::assertSame($contentId, $created->id);
        self::assertSame($categoryId, $created->categoryId);
        self::assertNull($created->languageCode);

        $contentService->update(new UpdateCategoryContentCommand(
            $contentId,
            'قمصان',
            'وصف',
        ));
        $updated = $queryReader->findContentById($contentId);
        self::assertNotNull($updated);
        self::assertSame($contentId, $updated->id);
        self::assertSame($categoryId, $updated->categoryId);
        self::assertNull($updated->languageCode);
        self::assertSame('قمصان', $updated->name);

        $contentService->softDelete(new SoftDeleteCategoryContentCommand($contentId));
        $deleted = $queryReader->findContentById($contentId);
        self::assertNotNull($deleted);
        self::assertNotNull($deleted->deletedAt);
        self::assertSame($contentId, $deleted->id);

        $contentService->restore(new RestoreCategoryContentCommand($contentId));
        $restored = $queryReader->findContentById($contentId);
        self::assertNotNull($restored);
        self::assertSame($contentId, $restored->id);
        self::assertSame($categoryId, $restored->categoryId);
        self::assertNull($restored->languageCode);
        self::assertNull($restored->deletedAt);
        self::assertSame('قمصان', $restored->name);
    }

    public function testContentInlineMutationsPreserveIdentityAndRejectDeletedContent(): void
    {
        $connection = $this->connection();
        $clock = new FixedCategoryClock('2026-01-03 00:00:00 Africa/Cairo');
        $categoryService = $this->service($connection, $clock);
        $contentService = $this->contentService($connection, $clock);
        $categoryId = $categoryService->create(new CreateCategoryCommand('content-inline-category'));
        $contentId = $contentService->create(
            new CreateCategoryContentCommand($categoryId, 'en-US', 'Original name', 'Original description'),
        );

        $contentService->updateName(new UpdateCategoryContentNameCommand($contentId, 'Inline name'));
        $updated = $contentService->getByIdForManagement($contentId);
        self::assertSame('Inline name', $updated->name);
        self::assertSame('Original description', $updated->description);
        self::assertSame($categoryId, $updated->categoryId);
        self::assertSame('en-US', $updated->languageCode);

        $contentService->updateDescription(new UpdateCategoryContentDescriptionCommand($contentId, null));
        $updated = $contentService->getByIdForManagement($contentId);
        self::assertSame('Inline name', $updated->name);
        self::assertNull($updated->description);

        $contentService->softDelete(new SoftDeleteCategoryContentCommand($contentId));
        $this->expectException(CategoryContentNotFoundException::class);
        $contentService->updateName(new UpdateCategoryContentNameCommand($contentId, 'Must fail'));
    }

    public function testContentMutationsFollowParentLifecycleStateContractOnMySql(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock('2026-01-03 00:00:00 Africa/Cairo'));
        $contentService = $this->contentService($connection, new FixedCategoryClock('2026-01-03 00:00:00 Africa/Cairo'));
        $queryReader = new PdoCategoryContentQueryReader($connection, new FixedCategoryClock());

        $inactiveCategoryId = $service->create(new CreateCategoryCommand('inactive-content-parent'));
        $service->updateStatus(
            new UpdateCategoryStatusCommand($inactiveCategoryId, CategoryStatusEnum::INACTIVE),
        );

        $inactiveContentId = $contentService->create(
            new CreateCategoryContentCommand($inactiveCategoryId, 'en-US', 'Inactive parent', null),
        );
        $inactiveContent = $queryReader->findContentById($inactiveContentId);
        self::assertNotNull($inactiveContent);
        self::assertSame($inactiveCategoryId, $inactiveContent->categoryId);

        $contentService->update(
            new UpdateCategoryContentCommand($inactiveContentId, 'Updated inactive parent', null),
        );
        $inactiveContent = $queryReader->findContentById($inactiveContentId);
        self::assertNotNull($inactiveContent);
        self::assertSame('Updated inactive parent', $inactiveContent->name);

        $contentService->softDelete(new SoftDeleteCategoryContentCommand($inactiveContentId));
        $inactiveContent = $queryReader->findContentById($inactiveContentId);
        self::assertNotNull($inactiveContent);
        self::assertNotNull($inactiveContent->deletedAt);

        $contentService->restore(new RestoreCategoryContentCommand($inactiveContentId));
        $inactiveContent = $queryReader->findContentById($inactiveContentId);
        self::assertNotNull($inactiveContent);
        self::assertNull($inactiveContent->deletedAt);

        $deletedCategoryId = $service->create(new CreateCategoryCommand('deleted-content-parent'));
        $deletedContentId = $contentService->create(
            new CreateCategoryContentCommand($deletedCategoryId, 'en-US', 'Deleted parent', null),
        );
        $service->softDelete(new SoftDeleteCategoryCommand($deletedCategoryId));

        try {
            $contentService->create(
                new CreateCategoryContentCommand($deletedCategoryId, 'ar-EG', 'Rejected', null),
            );
            self::fail('A Content must not be created under a soft-deleted Category.');
        } catch (CategoryNotFoundException) {
            // Creation requires a non-deleted parent Category.
        }

        $contentService->update(
            new UpdateCategoryContentCommand($deletedContentId, 'Updated deleted parent', null),
        );
        $deletedContent = $queryReader->findContentById($deletedContentId);
        self::assertNotNull($deletedContent);
        self::assertSame('Updated deleted parent', $deletedContent->name);

        $contentService->softDelete(new SoftDeleteCategoryContentCommand($deletedContentId));
        $deletedContent = $queryReader->findContentById($deletedContentId);
        self::assertNotNull($deletedContent);
        self::assertNotNull($deletedContent->deletedAt);

        $contentService->restore(new RestoreCategoryContentCommand($deletedContentId));
        $restored = $queryReader->findContentById($deletedContentId);
        self::assertNotNull($restored);
        self::assertNull($restored->deletedAt);
        self::assertSame($deletedCategoryId, $restored->categoryId);
    }

    public function testContentCreationRejectsDuplicateLogicalIdentityIncludingSoftDeletedRows(): void
    {
        $service = $this->service($this->connection(), new FixedCategoryClock());
        $contentService = $this->contentService($this->connection(), new FixedCategoryClock());
        $categoryId = $service->create(new CreateCategoryCommand('content-identity-category'));
        $command = new CreateCategoryContentCommand($categoryId, null, 'Shirts', null);

        $contentId = $contentService->create($command);
        $contentService->softDelete(new SoftDeleteCategoryContentCommand($contentId));

        $this->expectException(CategoryContentAlreadyExistsException::class);
        $contentService->create($command);
    }

    public function testSoftDeleteChecksNonDeletedChildrenAndAllowsTheParentAfterChildDeletion(): void
    {
        $service = $this->service($this->connection(), new FixedCategoryClock());
        $parentId = $service->create(new CreateCategoryCommand('delete-parent'));
        $childId = $service->create(new CreateCategoryCommand('delete-child', $parentId));

        try {
            $service->softDelete(new SoftDeleteCategoryCommand($parentId));
            self::fail('A parent with a non-deleted child must not be soft-deleted.');
        } catch (CategoryHasNonDeletedChildrenException) {
            // The failed operation must have rolled back before the child deletion.
        }

        $service->softDelete(new SoftDeleteCategoryCommand($childId));
        $service->softDelete(new SoftDeleteCategoryCommand($parentId));

        $queryReader = new PdoCategoryQueryReader($this->connection(), new FixedCategoryClock());
        self::assertNull($queryReader->findActiveById($parentId));
        self::assertNotNull($queryReader->findById($parentId));
    }

    public function testSharedOrderingApiMovesRowsInsideTheSameParentScope(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock());
        $parentId = $service->create(new CreateCategoryCommand('ordering-parent'));
        $firstId = $service->create(new CreateCategoryCommand('ordering-first', $parentId));
        $secondId = $service->create(new CreateCategoryCommand('ordering-second', $parentId));

        $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondId, 1));

        $ordersStatement = $connection->query(
            'SELECT `id`, `display_order` FROM `maa_category_categories` '
            . 'WHERE `parent_id` = ' . $parentId . ' ORDER BY `display_order`, `id`',
        );
        if ($ordersStatement === false) {
            self::fail('Unable to inspect Category ordering.');
        }
        /** @var array<int|string, int|string> $orders */
        $orders = $ordersStatement->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame(1, (int) $orders[$secondId]);
        self::assertSame(2, (int) $orders[$firstId]);
    }

    public function testSharedOrderingApiMovesRootRowsAndUpdatesTimestampAtomically(): void
    {
        $connection = $this->connection();
        $createService = $this->service($connection, new FixedCategoryClock('2026-01-03 00:00:00 Africa/Cairo'));
        $firstId = $createService->create(new CreateCategoryCommand('root-ordering-first'));
        $secondId = $createService->create(new CreateCategoryCommand('root-ordering-second'));

        $updateService = $this->service($connection, new FixedCategoryClock('2026-01-04 00:00:00 Africa/Cairo'));
        $updateService->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand($secondId, 1));

        $ordersStatement = $connection->query(
            'SELECT `id`, `display_order` FROM `maa_category_categories` '
            . 'WHERE `parent_id` IS NULL ORDER BY `display_order`, `id`',
        );
        if ($ordersStatement === false) {
            self::fail('Unable to inspect root Category ordering.');
        }
        /** @var array<int|string, int|string> $orders */
        $orders = $ordersStatement->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame(1, (int) $orders[$secondId]);
        self::assertSame(2, (int) $orders[$firstId]);

        $timestampStatement = $connection->prepare(
            'SELECT `updated_at` FROM `maa_category_categories` WHERE `id` = :id',
        );
        $timestampStatement->execute(['id' => $secondId]);
        self::assertSame('2026-01-04 00:00:00', $timestampStatement->fetchColumn());
    }

    public function testTransactionPreservesTheOriginalThrowableWhenTransactionIsAlreadyClosed(): void
    {
        $transaction = new PdoTransactionRunner($this->connection());
        $original = new RuntimeException('original transaction failure');

        $thrown = null;
        try {
            $transaction->run(function () use ($original): void {
                throw $original;
            });
        } catch (Throwable $thrown) {
        }
        self::assertSame($original, $thrown);
        self::assertFalse($this->connection()->inTransaction());

        $thrown = null;
        try {
            $transaction->run(function () use ($original): void {
                // Simulate a driver/operation that closes the transaction before
                // reporting its failure to the transaction adapter.
                $this->connection()->commit();
                throw $original;
            });
        } catch (Throwable $thrown) {
        }
        self::assertSame($original, $thrown);
        self::assertFalse($this->connection()->inTransaction());
    }

    public function testMoveWaitsOnLockedParentAndThenSucceedsAfterTheTransactionReleasesIt(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock());
        $sourceParentId = $service->create(new CreateCategoryCommand('source-parent'));
        $targetParentId = $service->create(new CreateCategoryCommand('target-parent'));
        $categoryId = $service->create(new CreateCategoryCommand('movable-category', $sourceParentId));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        $lockingReader = new PdoCategoryQueryReader($locker, new FixedCategoryClock());
        self::assertNotNull($lockingReader->findActiveByIdForUpdate($targetParentId));

        $blockedConnection = $this->newConnection();
        $blockedConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $blockedService = $this->service($blockedConnection, new FixedCategoryClock());

        try {
            $blockedService->move(new MoveCategoryCommand($categoryId, $targetParentId));
            self::fail('Moving under a locked parent must wait for the lock.');
        } catch (PDOException) {
            self::assertFalse($blockedConnection->inTransaction());
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }
        }

        $blockedService->move(new MoveCategoryCommand($categoryId, $targetParentId));
        self::assertSame($targetParentId, (new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock()))->findById($categoryId)?->parentId);
        $blockedConnection = null;
    }

    public function testSoftDeleteWaitsOnTheLockedCategoryRow(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock());
        $categoryId = $service->create(new CreateCategoryCommand('locked-delete-category'));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        self::assertNotNull((new PdoCategoryQueryReader($locker, new FixedCategoryClock()))->findActiveByIdForUpdate($categoryId));

        $blockedConnection = $this->newConnection();
        $blockedConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $blockedService = $this->service($blockedConnection, new FixedCategoryClock());

        try {
            $blockedService->softDelete(new SoftDeleteCategoryCommand($categoryId));
            self::fail('Soft delete must wait for a lock on the category row.');
        } catch (PDOException) {
            self::assertFalse($blockedConnection->inTransaction());
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }
        }

        $blockedService->softDelete(new SoftDeleteCategoryCommand($categoryId));
        self::assertNull((new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock()))->findActiveById($categoryId));
        $blockedConnection = null;
    }

    public function testStatusUpdateWaitsOnTheLockedCategoryRow(): void
    {
        $connection = $this->connection();
        $service = $this->service($connection, new FixedCategoryClock());
        $categoryId = $service->create(new CreateCategoryCommand('locked-status-category'));

        $locker = $this->newConnection();
        $locker->beginTransaction();
        self::assertNotNull((new PdoCategoryQueryReader($locker, new FixedCategoryClock()))->findActiveByIdForUpdate($categoryId));

        $blockedConnection = $this->newConnection();
        $blockedConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $blockedService = $this->service($blockedConnection, new FixedCategoryClock());

        try {
            $blockedService->updateStatus(new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::INACTIVE));
            self::fail('Status update must wait for a lock on the category row.');
        } catch (PDOException) {
            self::assertFalse($blockedConnection->inTransaction());
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }
        }

        $blockedService->updateStatus(new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::INACTIVE));
        self::assertSame(
            CategoryStatusEnum::INACTIVE,
            (new PdoCategoryQueryReader($blockedConnection, new FixedCategoryClock()))->findById($categoryId)?->status,
        );
        $blockedConnection = null;
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

    private function service(PDO $connection, FixedCategoryClock $clock): CategoryApiInterface
    {
        return CategoryFactory::create($connection, $clock)->categories();
    }

    private function contentService(PDO $connection, FixedCategoryClock $clock): ContentApiInterface
    {
        return CategoryFactory::create($connection, $clock)->contents();
    }
}
