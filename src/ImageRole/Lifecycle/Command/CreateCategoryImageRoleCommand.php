<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Lifecycle\Command;

use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;

/** Validated command for creating a package-owned Category Image Role. */
final readonly class CreateCategoryImageRoleCommand implements \JsonSerializable
{
    public string $roleKey;
    public CategoryImageRoleStatusEnum $status;

    public function __construct(
        string $roleKey,
        CategoryImageRoleStatusEnum $status = CategoryImageRoleStatusEnum::ACTIVE,
    ) {
        CategoryImageRoleDTO::assertValidRoleKey($roleKey);
        $this->roleKey = $roleKey;
        $this->status = $status;
    }

    /** @return array{roleKey: string, status: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'roleKey' => $this->roleKey,
            'status' => $this->status->value,
        ];
    }
}
