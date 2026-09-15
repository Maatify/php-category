<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use DateTimeImmutable;
use Maatify\Category\ImageRole\Contract\CategoryImageRoleCommandRepositoryInterface;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;

/** @internal Test-only in-memory image role command port. */
final class InMemoryCategoryImageRoleCommandRepository implements CategoryImageRoleCommandRepositoryInterface
{
    public ?CreateCategoryImageRoleCommand $created = null;
    public ?UpdateCategoryImageRoleStatusCommand $statusUpdated = null;
    public ?SoftDeleteCategoryImageRoleCommand $softDeleted = null;
    public ?RestoreCategoryImageRoleCommand $restored = null;

    public function create(CreateCategoryImageRoleCommand $command, DateTimeImmutable $occurredAt): int
    {
        $this->created = $command;

        return 66;
    }

    public function updateStatus(UpdateCategoryImageRoleStatusCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->statusUpdated = $command;

        return true;
    }

    public function softDelete(SoftDeleteCategoryImageRoleCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->softDeleted = $command;

        return true;
    }

    public function restore(RestoreCategoryImageRoleCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->restored = $command;

        return true;
    }
}
