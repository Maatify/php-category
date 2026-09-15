<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Lifecycle\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated soft-delete operation for a Category Image Assignment. */
final readonly class SoftDeleteCategoryImageAssignmentCommand implements \JsonSerializable
{
    public int $assignmentId;

    public function __construct(int|string $assignmentId)
    {
        $this->assignmentId = (new CategoryIdDTO($assignmentId, 'assignmentId'))->value;
    }

    /** @return array{assignmentId: int} */
    public function jsonSerialize(): mixed
    {
        return ['assignmentId' => $this->assignmentId];
    }
}
