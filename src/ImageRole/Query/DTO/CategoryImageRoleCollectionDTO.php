<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Query\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;

/** @implements IteratorAggregate<int, CategoryImageRoleDTO> */
final readonly class CategoryImageRoleCollectionDTO implements IteratorAggregate, Countable, \JsonSerializable
{
    /** @param list<CategoryImageRoleDTO> $items */
    public function __construct(private array $items) {}

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, CategoryImageRoleDTO> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return list<CategoryImageRoleDTO> */
    public function jsonSerialize(): mixed
    {
        return $this->items;
    }
}
