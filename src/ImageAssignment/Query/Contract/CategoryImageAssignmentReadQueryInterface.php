<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\Contract;

use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;

/** Dedicated public read port for visible Image Assignment behavior. */
interface CategoryImageAssignmentReadQueryInterface
{
    /** Lists assignments for the exact requested scope, with no fallback. */
    public function listVisibleImageAssignments(
        int $categoryId,
        CategoryImageAssignmentScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryImageAssignmentCollectionDTO;
}
