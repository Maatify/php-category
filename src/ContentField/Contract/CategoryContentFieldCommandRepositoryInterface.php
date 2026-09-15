<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Contract;

use DateTimeImmutable;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;

/** Write port for the complete Category Content Field lifecycle. */
interface CategoryContentFieldCommandRepositoryInterface
{
    public function create(CreateCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): int;

    public function update(UpdateCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): bool;

    public function updateDisplayOrder(
        UpdateCategoryContentFieldDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    public function softDelete(
        SoftDeleteCategoryContentFieldCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    public function restore(
        RestoreCategoryContentFieldCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;
}
