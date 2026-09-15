<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Ordering\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Dedicated ordering operation; assignment identity cannot be changed. */
final readonly class UpdateCategoryImageAssignmentDisplayOrderCommand implements \JsonSerializable
{
    public int $assignmentId;
    public int $displayOrder;

    public function __construct(int|string $assignmentId, int $displayOrder)
    {
        if ($displayOrder < 1) {
            throw CategoryInvalidArgumentException::invalidDisplayOrder($displayOrder);
        }

        $this->assignmentId = (new CategoryIdDTO($assignmentId, 'assignmentId'))->value;
        $this->displayOrder = $displayOrder;
    }

    /** @return array{assignmentId: int, displayOrder: int} */
    public function jsonSerialize(): mixed
    {
        return [
            'assignmentId' => $this->assignmentId,
            'displayOrder' => $this->displayOrder,
        ];
    }
}
