<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Contract;

use DateTimeImmutable;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;

/** Write port for the package-owned Category Image Role lifecycle. */
interface CategoryImageRoleCommandRepositoryInterface
{
    public function create(CreateCategoryImageRoleCommand $command, DateTimeImmutable $occurredAt): int;

    public function updateStatus(
        UpdateCategoryImageRoleStatusCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    public function softDelete(
        SoftDeleteCategoryImageRoleCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;

    public function restore(
        RestoreCategoryImageRoleCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool;
}
