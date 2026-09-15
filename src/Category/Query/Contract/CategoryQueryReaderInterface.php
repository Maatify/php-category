<?php

declare(strict_types=1);

namespace Maatify\Category\Query\Contract;

use Maatify\Category\Query\DTO\CategoryDTO;

/** Read port consumed by Category business orchestration. */
interface CategoryQueryReaderInterface
{
    /**
     * Finds an existing Category regardless of soft-delete state.
     *
     * This all-state lookup is used for stable-code reservation and restore.
     */
    public function findById(int $categoryId): ?CategoryDTO;

    /** Finds both active and soft-deleted rows so stable codes cannot be reused. */
    public function findByCode(string $code): ?CategoryDTO;

    /** Finds only a non-deleted Category for create and move validation. */
    public function findActiveById(int $categoryId): ?CategoryDTO;

    /**
     * Finds and locks an active Category row for the current transaction.
     *
     * Implementations MUST execute this lookup with a row lock and callers
     * MUST invoke it inside TransactionRunnerInterface::run().
     */
    public function findActiveByIdForUpdate(int $categoryId): ?CategoryDTO;

    /**
     * Finds and locks a Category row regardless of soft-delete state.
     *
     * This is the restore lookup and MUST execute with a row lock inside the
     * current TransactionRunnerInterface::run() transaction.
     */
    public function findByIdForUpdate(int $categoryId): ?CategoryDTO;

    /**
     * Returns whether a non-deleted child exists and locks matching child rows.
     *
     * The parent row MUST already be locked by findActiveByIdForUpdate() so a
     * concurrent child creation cannot pass the check between read and delete.
     */
    public function hasNonDeletedChildrenForUpdate(int $categoryId): bool;
}
