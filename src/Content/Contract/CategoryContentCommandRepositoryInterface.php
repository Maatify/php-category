<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Contract;

use DateTimeImmutable;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;

/** Write port for the complete Category Content mutation lifecycle. */
interface CategoryContentCommandRepositoryInterface
{
    public function create(CreateCategoryContentCommand $command, DateTimeImmutable $occurredAt): int;

    public function update(UpdateCategoryContentCommand $command, DateTimeImmutable $occurredAt): bool;

    public function softDelete(SoftDeleteCategoryContentCommand $command, DateTimeImmutable $occurredAt): bool;

    public function restore(RestoreCategoryContentCommand $command, DateTimeImmutable $occurredAt): bool;
}
