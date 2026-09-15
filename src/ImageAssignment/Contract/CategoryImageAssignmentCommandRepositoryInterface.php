<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Contract;

use DateTimeImmutable;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;

/** Write port for the Category-owned Media Asset assignment lifecycle. */
interface CategoryImageAssignmentCommandRepositoryInterface
{
    public function create(CreateCategoryImageAssignmentCommand $command, DateTimeImmutable $occurredAt): int;

    public function updateDisplayOrder(
        UpdateCategoryImageAssignmentDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    /** Returns false when the target is missing or soft-deleted. */
    public function setDefault(
        SetCategoryImageAssignmentDefaultCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    /** Returns false when the target is missing or soft-deleted. */
    public function clearDefault(
        ClearCategoryImageAssignmentDefaultCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    public function softDelete(
        SoftDeleteCategoryImageAssignmentCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    public function restore(
        RestoreCategoryImageAssignmentCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;
}
