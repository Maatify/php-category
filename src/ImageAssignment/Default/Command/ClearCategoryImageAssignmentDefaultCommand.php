<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Default\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated explicit mutation clearing an Image Assignment's scope default. */
final readonly class ClearCategoryImageAssignmentDefaultCommand implements \JsonSerializable
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
