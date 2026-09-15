<?php

declare(strict_types=1);

namespace Maatify\Category\Contract;

use DateTimeImmutable;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;

/**
 * Write port for Category persistence.
 *
 * The Category application receives mutation timestamps from the Host-provided
 * ClockInterface. Persistence adapters only persist the supplied timestamp and
 * delegate display-order mechanics to the approved persistence capability.
 */
interface CategoryCommandRepositoryInterface
{
    public function create(CreateCategoryCommand $command, DateTimeImmutable $occurredAt): int;

    public function move(MoveCategoryCommand $command, DateTimeImmutable $occurredAt): bool;

    public function softDelete(SoftDeleteCategoryCommand $command, DateTimeImmutable $occurredAt): bool;

    public function restore(RestoreCategoryCommand $command, DateTimeImmutable $occurredAt): bool;

    public function updateStatus(UpdateCategoryStatusCommand $command, DateTimeImmutable $occurredAt): bool;

    public function updateDisplayOrder(UpdateCategoryDisplayOrderCommand $command, DateTimeImmutable $occurredAt): bool;
}
