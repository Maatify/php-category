<?php

declare(strict_types=1);

namespace Maatify\Category\Lifecycle\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;

/** Validated command for changing only a Category status. */
final readonly class UpdateCategoryStatusCommand implements \JsonSerializable
{
    public int $categoryId;
    public CategoryStatusEnum $status;

    public function __construct(int|string $categoryId, CategoryStatusEnum $status)
    {
        $this->categoryId = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
        $this->status = $status;
    }

    /** @return array{categoryId: int, status: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'status' => $this->status->value,
        ];
    }
}
