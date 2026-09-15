<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use DateTimeImmutable;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Contract\CategoryImageAssignmentCommandRepositoryInterface;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;

/** @internal Test-only in-memory image assignment command port. */
final class InMemoryCategoryImageAssignmentCommandRepository implements CategoryImageAssignmentCommandRepositoryInterface
{
    public ?CreateCategoryImageAssignmentCommand $created = null;
    public ?UpdateCategoryImageAssignmentDisplayOrderCommand $displayOrderUpdated = null;
    public ?SetCategoryImageAssignmentDefaultCommand $defaultSet = null;
    public ?ClearCategoryImageAssignmentDefaultCommand $defaultCleared = null;
    public ?SoftDeleteCategoryImageAssignmentCommand $softDeleted = null;
    public ?RestoreCategoryImageAssignmentCommand $restored = null;

    public function create(CreateCategoryImageAssignmentCommand $command, DateTimeImmutable $occurredAt): int
    {
        $this->created = $command;

        return 77;
    }

    public function updateDisplayOrder(
        UpdateCategoryImageAssignmentDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $this->displayOrderUpdated = $command;

        return true;
    }

    public function setDefault(SetCategoryImageAssignmentDefaultCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->defaultSet = $command;

        return true;
    }

    public function clearDefault(ClearCategoryImageAssignmentDefaultCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->defaultCleared = $command;

        return true;
    }

    public function softDelete(SoftDeleteCategoryImageAssignmentCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->softDeleted = $command;

        return true;
    }

    public function restore(RestoreCategoryImageAssignmentCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $this->restored = $command;

        return true;
    }
}
