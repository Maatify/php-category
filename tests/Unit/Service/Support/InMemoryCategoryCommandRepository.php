<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use DateTimeImmutable;
use Maatify\Category\Contract\CategoryCommandRepositoryInterface;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;

/** @internal Test-only in-memory category command port. */
final class InMemoryCategoryCommandRepository implements CategoryCommandRepositoryInterface
{
    public ?DateTimeImmutable $occurredAt = null;

    public ?CreateCategoryCommand $created = null;
    public ?MoveCategoryCommand $moved = null;
    public ?SoftDeleteCategoryCommand $softDeleted = null;
    public ?RestoreCategoryCommand $restored = null;
    public ?UpdateCategoryStatusCommand $statusUpdated = null;
    public ?UpdateCategoryDisplayOrderCommand $displayOrderUpdated = null;

    public function create(CreateCategoryCommand $command, DateTimeImmutable $occurredAt): int
    {
        $this->created = $command;
        $this->occurredAt = $occurredAt;

        return 99;
    }

    public function move(MoveCategoryCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->moved = $command;
        $this->occurredAt = $occurredAt;

        return true;
    }

    public function softDelete(SoftDeleteCategoryCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->softDeleted = $command;
        $this->occurredAt = $occurredAt;

        return true;
    }

    public function restore(RestoreCategoryCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->restored = $command;
        $this->occurredAt = $occurredAt;

        return true;
    }

    public function updateStatus(UpdateCategoryStatusCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->statusUpdated = $command;
        $this->occurredAt = $occurredAt;

        return true;
    }

    public function updateDisplayOrder(UpdateCategoryDisplayOrderCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->displayOrderUpdated = $command;
        $this->occurredAt = $occurredAt;

        return true;
    }
}
