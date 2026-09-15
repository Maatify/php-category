<?php

declare(strict_types=1);

namespace Maatify\Category\Infrastructure;

use DateTimeImmutable;
use Maatify\Category\Contract\CategoryCommandRepositoryInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Lifecycle\Exception\CategoryCodeAlreadyExistsException;
use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use PDO;
use PDOException;

/** PDO write adapter for the Category persistence port. */
final readonly class PdoCategoryCommandRepository implements CategoryCommandRepositoryInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';

    public function __construct(
        private PDO $pdo,
        private ScopedOrderingManager $orderingManager,
    ) {}

    public function create(CreateCategoryCommand $command, DateTimeImmutable $occurredAt): int
    {
        $this->lockCreationScope($command->parentId);
        $displayOrder = $this->orderingManager->getNextPosition(
            $this->pdo,
            $this->orderingConfig(),
            $command->parentId,
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::CATEGORY_TABLE . '` '
            . '(`parent_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:parent_id, :code, :status, :display_order, :created_at, :updated_at, NULL)',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        try {
            $statement->execute([
                'parent_id' => $command->parentId,
                'code' => $command->code,
                'status' => $command->status->value,
                'display_order' => $displayOrder,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (PDOException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ((is_int($driverCode) || is_string($driverCode)) && (int) $driverCode === 1062) {
                throw CategoryCodeAlreadyExistsException::withCode($command->code, $exception);
            }

            throw $exception;
        }

        $id = $this->pdo->lastInsertId();
        if ($id === false || !ctype_digit($id) || (int) $id < 1) {
            throw CategoryPersistenceException::invalidAutoIncrementIdentity();
        }

        return (int) $id;
    }

    public function move(MoveCategoryCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::CATEGORY_TABLE . '` '
            . 'SET `parent_id` = :parent_id, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $statement->execute([
            'parent_id' => $command->parentId,
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->categoryId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function softDelete(SoftDeleteCategoryCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::CATEGORY_TABLE . '` '
            . 'SET `deleted_at` = :deleted_at, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        $statement->execute([
            'deleted_at' => $timestamp,
            'updated_at' => $timestamp,
            'id' => $command->categoryId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function restore(RestoreCategoryCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::CATEGORY_TABLE . '` '
            . 'SET `deleted_at` = NULL, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NOT NULL',
        );
        $statement->execute([
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->categoryId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function updateStatus(UpdateCategoryStatusCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::CATEGORY_TABLE . '` '
            . 'SET `status` = :status, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $statement->execute([
            'status' => $command->status->value,
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->categoryId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function updateDisplayOrder(
        UpdateCategoryDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $parentId = $this->activeParentId($command->categoryId);
        if ($parentId === false) {
            return false;
        }

        $moved = $this->orderingManager->moveWithinScope(
            $this->pdo,
            $this->orderingConfig(),
            $parentId,
            $command->categoryId,
            $command->displayOrder,
            $this->formatTimestamp($occurredAt),
        );

        return $moved;
    }

    private function activeParentId(int $categoryId): int|false|null
    {
        $statement = $this->pdo->prepare(
            'SELECT `parent_id` FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1',
        );
        $statement->execute(['id' => $categoryId]);
        $value = $statement->fetchColumn();

        if ($value === false) {
            return false;
        }
        if ($value === null) {
            return null;
        }
        return (int) $value;
    }

    /**
     * Serializes creation within the nullable parent scope before asking the
     * shared Ordering API for MAX(display_order) + 1.
     *
     * The Category service owns the surrounding transaction. InnoDB locks the
     * matching scope rows (or the empty indexed scope gap) so concurrent root
     * and child creations cannot calculate the same next position.
     */
    private function lockCreationScope(?int $parentId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT `id` FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `parent_id` <=> :parent_id '
            . 'FOR UPDATE',
        );
        $statement->execute(['parent_id' => $parentId]);
    }

    private function orderingConfig(): ScopedOrderingConfig
    {
        return new ScopedOrderingConfig(
            table: self::CATEGORY_TABLE,
            scopeColumn: 'parent_id',
            idColumn: 'id',
            orderColumn: 'display_order',
            deletedAtColumn: 'deleted_at',
            nullableScope: true,
            updatedAtColumn: 'updated_at',
        );
    }

    private function formatTimestamp(DateTimeImmutable $occurredAt): string
    {
        return $occurredAt->format('Y-m-d H:i:s');
    }
}
