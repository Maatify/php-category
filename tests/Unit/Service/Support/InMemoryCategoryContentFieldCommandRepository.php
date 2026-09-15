<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use DateTimeImmutable;
use Maatify\Category\ContentField\Contract\CategoryContentFieldCommandRepositoryInterface;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;

/** @internal Test-only in-memory content field command port. */
final class InMemoryCategoryContentFieldCommandRepository implements CategoryContentFieldCommandRepositoryInterface
{
    public ?CreateCategoryContentFieldCommand $created = null;
    public ?UpdateCategoryContentFieldCommand $updated = null;
    /** @var list<UpdateCategoryContentFieldCommand> */
    public array $updates = [];
    public ?UpdateCategoryContentFieldDisplayOrderCommand $displayOrderUpdated = null;
    public ?SoftDeleteCategoryContentFieldCommand $softDeleted = null;
    public ?RestoreCategoryContentFieldCommand $restored = null;

    public function create(CreateCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): int
    {
        $this->created = $command;

        return 88;
    }

    public function update(UpdateCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->updated = $command;
        $this->updates[] = $command;

        return true;
    }

    public function updateDisplayOrder(
        UpdateCategoryContentFieldDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $this->displayOrderUpdated = $command;

        return true;
    }

    public function softDelete(SoftDeleteCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->softDeleted = $command;

        return true;
    }

    public function restore(RestoreCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->restored = $command;

        return true;
    }
}
