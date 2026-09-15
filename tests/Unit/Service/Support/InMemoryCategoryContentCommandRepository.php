<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use DateTimeImmutable;
use Maatify\Category\Content\Contract\CategoryContentCommandRepositoryInterface;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;

/** @internal Test-only in-memory content command port. */
final class InMemoryCategoryContentCommandRepository implements CategoryContentCommandRepositoryInterface
{
    public ?CreateCategoryContentCommand $created = null;
    public ?UpdateCategoryContentCommand $updated = null;
    /** @var list<UpdateCategoryContentCommand> */
    public array $updates = [];
    public ?SoftDeleteCategoryContentCommand $softDeleted = null;
    public ?RestoreCategoryContentCommand $restored = null;

    public function create(CreateCategoryContentCommand $command, DateTimeImmutable $occurredAt): int
    {
        $this->created = $command;

        return 77;
    }

    public function update(UpdateCategoryContentCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->updated = $command;
        $this->updates[] = $command;

        return true;
    }

    public function softDelete(SoftDeleteCategoryContentCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->softDeleted = $command;

        return true;
    }

    public function restore(RestoreCategoryContentCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->restored = $command;

        return true;
    }
}
