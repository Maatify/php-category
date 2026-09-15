<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\DTO;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\ImageAssignment\Query\Enum\CategoryImageAssignmentRoleFilterModeEnum;

/** Explicit management Role filter: omitted, exact NULL, or one concrete Role. */
final readonly class CategoryImageAssignmentRoleFilterDTO implements \JsonSerializable
{
    private function __construct(
        public CategoryImageAssignmentRoleFilterModeEnum $mode,
        public ?int $roleId,
    ) {}

    public static function omitted(): self
    {
        return new self(CategoryImageAssignmentRoleFilterModeEnum::OMITTED, null);
    }

    public static function exactNull(): self
    {
        return new self(CategoryImageAssignmentRoleFilterModeEnum::EXACT_NULL, null);
    }

    public static function forRole(int|string $roleId): self
    {
        return new self(
            CategoryImageAssignmentRoleFilterModeEnum::CONCRETE,
            (new CategoryIdDTO($roleId, 'roleId'))->value,
        );
    }

    /** @return array{mode: string, roleId: ?int} */
    public function jsonSerialize(): mixed
    {
        return [
            'mode' => $this->mode->value,
            'roleId' => $this->roleId,
        ];
    }
}
