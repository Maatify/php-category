<?php

declare(strict_types=1);

namespace Maatify\Category\Api;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Public application contract for Category business mutations.
 * Public application contract for visible Category reads and lists.
 * Public application contract for management Category reads.
 */
interface CategoryApiInterface
{
    public function create(CreateCategoryCommand $command): int;

    public function move(MoveCategoryCommand $command): void;

    public function softDelete(SoftDeleteCategoryCommand $command): void;

    public function restore(RestoreCategoryCommand $command): void;

    public function updateStatus(UpdateCategoryStatusCommand $command): void;

    public function updateDisplayOrder(UpdateCategoryDisplayOrderCommand $command): void;

    /** @throws \Maatify\Category\Exception\CategoryNotFoundException */
    public function getById(int $categoryId): CategoryDTO;

    public function listRootCategories(
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO;

    public function listChildren(
        int $parentId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO;

    /** @throws \Maatify\Category\Exception\CategoryNotFoundException */
    public function getByIdForManagement(
        int $categoryId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryDTO;

    /** @throws \Maatify\Category\Exception\CategoryNotFoundException */
    public function getByCode(
        string $code,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryDTO;

    public function listForManagement(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO;

    public function listRootCategoriesForManagement(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO;

    public function listChildrenForManagement(int $parentId, CategoryListCriteriaDTO $criteria): CategoryCollectionDTO;

    /** @return PageResult<CategoryDTO> */
    public function paginateForManagement(CategoryListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult;

    /** @return PageResult<CategoryDTO> */
    public function paginateRootCategoriesForManagement(
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult;

    /** @return PageResult<CategoryDTO> */
    public function paginateChildrenForManagement(
        int $parentId,
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult;
}
