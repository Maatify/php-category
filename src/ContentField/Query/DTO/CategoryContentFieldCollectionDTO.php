<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Query\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/** Typed Category Content Field query result collection. */
/** @implements IteratorAggregate<int, CategoryContentFieldDTO> */
final readonly class CategoryContentFieldCollectionDTO implements IteratorAggregate, Countable, \JsonSerializable
{
    /** @param list<CategoryContentFieldDTO> $items */
    public function __construct(private array $items) {}

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, CategoryContentFieldDTO> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return list<CategoryContentFieldDTO> */
    public function jsonSerialize(): mixed
    {
        return $this->items;
    }
}
