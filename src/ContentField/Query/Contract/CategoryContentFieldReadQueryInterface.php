<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Query\Contract;

use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;

/** Dedicated public read port for visible Content Field behavior. */
interface CategoryContentFieldReadQueryInterface
{
    /** Lists fields for the exact requested scope, with no fallback. */
    public function listVisibleContentFields(
        int $categoryId,
        CategoryContentFieldScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentFieldCollectionDTO;
}
