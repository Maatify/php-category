<?php

declare(strict_types=1);

namespace Maatify\Category\Api;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Contract\CategoryServiceInterface;
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

/** Public Category domain API; all orchestration remains in CategoryService. */
final readonly class CategoryApi implements CategoryApiInterface
{
    public function __construct(private CategoryServiceInterface $service) {}

    public function create(CreateCategoryCommand $command): int
    {
        return $this->service->create($command);
    }

    public function move(MoveCategoryCommand $command): void
    {
        $this->service->move($command);
    }

    public function softDelete(SoftDeleteCategoryCommand $command): void
    {
        $this->service->softDelete($command);
    }

    public function restore(RestoreCategoryCommand $command): void
    {
        $this->service->restore($command);
    }

    public function updateStatus(UpdateCategoryStatusCommand $command): void
    {
        $this->service->updateStatus($command);
    }

    public function updateDisplayOrder(UpdateCategoryDisplayOrderCommand $command): void
    {
        $this->service->updateDisplayOrder($command);
    }

    public function getById(int $categoryId): CategoryDTO
    {
        return $this->service->getById($categoryId);
    }

    public function listRootCategories(
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO {
        return $this->service->listRootCategories($criteria);
    }

    public function listChildren(
        int $parentId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO {
        return $this->service->listChildren($parentId, $criteria);
    }

    public function getByIdForManagement(
        int $categoryId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryDTO {
        return $this->service->getByIdForManagement($categoryId, $deletedState);
    }

    public function getByCode(
        string $code,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryDTO {
        return $this->service->getByCode($code, $deletedState);
    }

    public function listForManagement(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->service->listForManagement($criteria);
    }

    public function listRootCategoriesForManagement(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->service->listRootCategoriesForManagement($criteria);
    }

    public function listChildrenForManagement(int $parentId, CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->service->listChildrenForManagement($parentId, $criteria);
    }

    public function paginateForManagement(CategoryListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult
    {
        return $this->service->paginateForManagement($criteria, $pageRequest);
    }

    public function paginateRootCategoriesForManagement(
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->service->paginateRootCategoriesForManagement($criteria, $pageRequest);
    }

    public function paginateChildrenForManagement(
        int $parentId,
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->service->paginateChildrenForManagement($parentId, $criteria, $pageRequest);
    }
}
