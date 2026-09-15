<?php

declare(strict_types=1);

namespace Maatify\Category\Lifecycle\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated command for restoring a Category with its existing identity. */
final readonly class RestoreCategoryCommand implements \JsonSerializable
{
    public int $categoryId;

    public function __construct(int|string $categoryId)
    {
        $this->categoryId = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
    }

    /** @return array{categoryId: int} */
    public function jsonSerialize(): mixed
    {
        return ['categoryId' => $this->categoryId];
    }
}
