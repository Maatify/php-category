<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\Contract;

use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;

/** Read port consumed by Image Assignment mutation orchestration. */
interface CategoryImageAssignmentQueryReaderInterface
{
    public function findImageAssignmentById(int $assignmentId): ?CategoryImageAssignmentDTO;

    /** Finds and locks an Image Assignment regardless of soft-delete state. */
    public function findImageAssignmentByIdForUpdate(int $assignmentId): ?CategoryImageAssignmentDTO;
}
