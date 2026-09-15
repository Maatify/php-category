<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Contract;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public application contract for Content business mutations and visible/management reads and lists. */
interface ContentServiceInterface
{
    public function create(CreateCategoryContentCommand $command): int;

    public function update(UpdateCategoryContentCommand $command): void;

    public function updateName(UpdateCategoryContentNameCommand $command): void;

    public function updateDescription(UpdateCategoryContentDescriptionCommand $command): void;

    public function softDelete(SoftDeleteCategoryContentCommand $command): void;

    public function restore(RestoreCategoryContentCommand $command): void;

    public function listVisibleForCategory(
        int $categoryId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentCollectionDTO;

    /** @throws \Maatify\Category\Content\Exception\CategoryContentNotFoundException */
    public function getByIdForManagement(
        int $contentId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryContentDTO;

    public function listForManagement(CategoryContentListCriteriaDTO $criteria): CategoryContentCollectionDTO;

    /** @return PageResult<CategoryContentDTO> */
    public function paginateForManagement(CategoryContentListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult;
}
