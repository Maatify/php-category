<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\Contract;

use Maatify\Category\Content\Query\DTO\CategoryContentDTO;

/** Read port consumed by Category Content mutation orchestration. */
interface CategoryContentQueryReaderInterface
{
    public function findContentById(int $contentId): ?CategoryContentDTO;

    /**
     * Finds and locks a Category Content regardless of soft-delete state.
     *
     * This lookup MUST execute with a row lock inside the current
     * TransactionRunnerInterface::run() transaction.
     */
    public function findContentByIdForUpdate(int $contentId): ?CategoryContentDTO;
}
