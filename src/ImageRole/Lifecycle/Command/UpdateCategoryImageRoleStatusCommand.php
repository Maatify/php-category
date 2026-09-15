<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Lifecycle\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;

/** Dedicated operation for changing only an Image Role lifecycle status. */
final readonly class UpdateCategoryImageRoleStatusCommand implements \JsonSerializable
{
    public int $roleId;
    public CategoryImageRoleStatusEnum $status;

    public function __construct(int|string $roleId, CategoryImageRoleStatusEnum $status)
    {
        $this->roleId = (new CategoryIdDTO($roleId, 'roleId'))->value;
        $this->status = $status;
    }

    /** @return array{roleId: int, status: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'roleId' => $this->roleId,
            'status' => $this->status->value,
        ];
    }
}
