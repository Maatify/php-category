<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/** @implements IteratorAggregate<int, CategoryImageAssignmentDTO> */
final readonly class CategoryImageAssignmentCollectionDTO implements IteratorAggregate, Countable, \JsonSerializable
{
    /** @param list<CategoryImageAssignmentDTO> $items */
    public function __construct(private array $items) {}

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, CategoryImageAssignmentDTO> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return list<CategoryImageAssignmentDTO> */
    public function jsonSerialize(): mixed
    {
        return $this->items;
    }
}
