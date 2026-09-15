<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\Contract;

use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;

/** Dedicated public read port for visible Category Content behavior. */
interface CategoryContentReadQueryInterface
{
    /**
     * Lists non-deleted contents for a visible Category in language-code
     * order. The Package validates the syntactic/storage contract; the Host
     * validates semantic language support and owns fallback/locale policy.
     */
    public function listVisibleContents(
        int $categoryId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentCollectionDTO;
}
