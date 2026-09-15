<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Lifecycle\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated soft-delete operation for a Category Image Role. */
final readonly class SoftDeleteCategoryImageRoleCommand implements \JsonSerializable
{
    public int $roleId;

    public function __construct(int|string $roleId)
    {
        $this->roleId = (new CategoryIdDTO($roleId, 'roleId'))->value;
    }

    /** @return array{roleId: int} */
    public function jsonSerialize(): mixed
    {
        return ['roleId' => $this->roleId];
    }
}
