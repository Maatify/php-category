<?php

declare(strict_types=1);

namespace Maatify\Category\Ordering\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Validated command for the dedicated Category display-order operation. */
final readonly class UpdateCategoryDisplayOrderCommand implements \JsonSerializable
{
    public int $categoryId;
    public int $displayOrder;

    public function __construct(int|string $categoryId, int $displayOrder)
    {
        if ($displayOrder < 1) {
            throw CategoryInvalidArgumentException::invalidDisplayOrder($displayOrder);
        }

        $this->categoryId = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
        $this->displayOrder = $displayOrder;
    }

    /** @return array{categoryId: int, displayOrder: int} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'displayOrder' => $this->displayOrder,
        ];
    }
}
