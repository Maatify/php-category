<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Contract;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public application contract for ContentField business mutations and visible/management reads and lists. */
interface ContentFieldServiceInterface
{
    public function create(CreateCategoryContentFieldCommand $command): int;

    public function update(UpdateCategoryContentFieldCommand $command): void;

    public function updateValue(UpdateCategoryContentFieldValueCommand $command): void;

    public function updateDisplayOrder(UpdateCategoryContentFieldDisplayOrderCommand $command): void;

    public function softDelete(SoftDeleteCategoryContentFieldCommand $command): void;

    public function restore(RestoreCategoryContentFieldCommand $command): void;

    public function listVisibleForCategory(
        int $categoryId,
        CategoryContentFieldScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentFieldCollectionDTO;

    /** @throws \Maatify\Category\ContentField\Exception\CategoryContentFieldNotFoundException */
    public function getByIdForManagement(
        int $fieldId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryContentFieldDTO;

    public function listForManagement(CategoryContentFieldListCriteriaDTO $criteria): CategoryContentFieldCollectionDTO;

    /** @return PageResult<CategoryContentFieldDTO> */
    public function paginateForManagement(
        CategoryContentFieldListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult;
}
