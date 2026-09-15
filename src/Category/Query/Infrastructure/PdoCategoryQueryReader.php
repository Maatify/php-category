<?php

declare(strict_types=1);

namespace Maatify\Category\Query\Infrastructure;

use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** PDO read adapter with explicit active/all-state and locking semantics. */
final readonly class PdoCategoryQueryReader extends PdoReadQuerySupport implements CategoryQueryReaderInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    public function findById(int $categoryId): ?CategoryDTO
    {
        return $this->findCategory($categoryId, false, false);
    }

    public function findByCode(string $code): ?CategoryDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `parent_id`, `code`, `status`, `display_order`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `code` = :code LIMIT 1',
        );
        $statement->execute(['code' => $code]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    public function findActiveById(int $categoryId): ?CategoryDTO
    {
        return $this->findCategory($categoryId, true, false);
    }

    /**
     * Finds and locks an active Category row for the current transaction.
     *
     * Implementations MUST execute this lookup with a row lock and callers
     * MUST invoke it inside TransactionRunnerInterface::run().
     */
    public function findActiveByIdForUpdate(int $categoryId): ?CategoryDTO
    {
        return $this->findCategory($categoryId, true, true);
    }

    /**
     * Finds and locks a Category row regardless of soft-delete state.
     *
     * This is the restore lookup and MUST execute with a row lock inside the
     * current TransactionRunnerInterface::run() transaction.
     */
    public function findByIdForUpdate(int $categoryId): ?CategoryDTO
    {
        return $this->findCategory($categoryId, false, true);
    }

    /**
     * Returns whether a non-deleted child exists and locks matching child rows.
     *
     * The parent row MUST already be locked by findActiveByIdForUpdate() so a
     * concurrent child creation cannot pass the check between read and delete.
     */
    public function hasNonDeletedChildrenForUpdate(int $categoryId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT `id` FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `parent_id` = :parent_id AND `deleted_at` IS NULL '
            . 'FOR UPDATE',
        );
        $statement->execute(['parent_id' => $categoryId]);

        return $statement->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private function findCategory(int $categoryId, bool $activeOnly, bool $forUpdate): ?CategoryDTO
    {
        $where = '`id` = :id';
        if ($activeOnly) {
            $where .= ' AND `deleted_at` IS NULL';
        }

        $statement = $this->pdo->prepare(
            'SELECT `id`, `parent_id`, `code`, `status`, `display_order`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE ' . $where . ' LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['id' => $categoryId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrateCategory(array $row): CategoryDTO
    {
        $status = $row['status'] ?? null;
        if (!is_string($status)) {
            throw CategoryPersistenceException::unexpectedColumnType('status');
        }

        $parentId = $row['parent_id'] ?? null;
        if ($parentId !== null && !is_int($parentId) && !is_string($parentId)) {
            throw CategoryPersistenceException::unexpectedColumnType('parent_id');
        }
        try {
            $categoryStatus = CategoryStatusEnum::from($status);
        } catch (\ValueError $exception) {
            throw CategoryPersistenceException::invalidStorageValue('status', $exception);
        }

        return new CategoryDTO(
            id: $this->integerValue($row, 'id'),
            parentId: $parentId === null ? null : (int) $parentId,
            code: $this->stringValue($row, 'code'),
            status: $categoryStatus,
            displayOrder: $this->integerValue($row, 'display_order'),
            createdAt: $this->timestampValue($row, 'created_at', $this->clock),
            updatedAt: $this->timestampValue($row, 'updated_at', $this->clock),
            deletedAt: $this->nullableTimestampValue($row, 'deleted_at', $this->clock),
        );
    }
}
