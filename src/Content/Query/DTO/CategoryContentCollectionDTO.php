<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Typed Category Content query result collection.
 *
 * @implements IteratorAggregate<int, CategoryContentDTO>
 */
final readonly class CategoryContentCollectionDTO implements IteratorAggregate, Countable, \JsonSerializable
{
    /**
     * @param list<CategoryContentDTO> $items
     */
    public function __construct(private array $items) {}

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, CategoryContentDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return list<CategoryContentDTO> */
    public function jsonSerialize(): mixed
    {
        return $this->items;
    }
}
