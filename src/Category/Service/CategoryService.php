<?php

declare(strict_types=1);

namespace Maatify\Category\Service;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\Contract\CategoryCommandRepositoryInterface;
use Maatify\Category\Contract\CategoryServiceInterface;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Hierarchy\Exception\CategoryCycleException;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Lifecycle\Exception\CategoryCodeAlreadyExistsException;
use Maatify\Category\Lifecycle\Exception\CategoryHasNonDeletedChildrenException;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Query\Contract\CategoryManagementReadQueryInterface;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Query\Contract\CategoryReadQueryInterface;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Coordinates Category business rules and consumes Host-provided mutation time.
 * Coordinates the public visible Category read contract.
 * Coordinates the public management Category read contract.
 * Owns Category lifecycle, hierarchy, and ordering orchestration only.
 */
final readonly class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private CategoryCommandRepositoryInterface $commandRepository,
        private CategoryQueryReaderInterface $queryReader,
        private CategoryReadQueryInterface $visibleReader,
        private CategoryManagementReadQueryInterface $managementReader,
        private TransactionRunnerInterface $transaction,
        private ClockInterface $clock,
    ) {}

    public function create(CreateCategoryCommand $command): int
    {
        return $this->transaction->run(function () use ($command): int {
            if ($this->queryReader->findByCode($command->code) !== null) {
                throw CategoryCodeAlreadyExistsException::withCode($command->code);
            }

            if ($command->parentId !== null) {
                // Serialize parent validation with soft-delete and other
                // hierarchy mutations before inserting the child.
                $this->requireActiveCategoryForUpdate($command->parentId);
            }

            return $this->commandRepository->create($command, $this->clock->now());
        });
    }

    public function move(MoveCategoryCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $category = $this->requireActiveCategoryForUpdate($command->categoryId);

            if ($command->parentId !== null) {
                $this->assertMoveDoesNotCreateCycle($category->id, $command->parentId);
            }

            if (!$this->commandRepository->move($command, $this->clock->now())) {
                throw CategoryNotFoundException::withId($command->categoryId);
            }
        });
    }

    public function softDelete(SoftDeleteCategoryCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveCategoryForUpdate($command->categoryId);

            if ($this->queryReader->hasNonDeletedChildrenForUpdate($command->categoryId)) {
                throw CategoryHasNonDeletedChildrenException::withId($command->categoryId);
            }

            if (!$this->commandRepository->softDelete($command, $this->clock->now())) {
                throw CategoryNotFoundException::withId($command->categoryId);
            }
        });
    }

    public function restore(RestoreCategoryCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireCategoryForUpdate($command->categoryId);

            if (!$this->commandRepository->restore($command, $this->clock->now())) {
                throw CategoryNotFoundException::withId($command->categoryId);
            }
        });
    }

    public function updateStatus(UpdateCategoryStatusCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveCategoryForUpdate($command->categoryId);

            if (!$this->commandRepository->updateStatus($command, $this->clock->now())) {
                throw CategoryNotFoundException::withId($command->categoryId);
            }
        });
    }

    public function updateDisplayOrder(UpdateCategoryDisplayOrderCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveCategoryForUpdate($command->categoryId);

            if (!$this->commandRepository->updateDisplayOrder($command, $this->clock->now())) {
                throw CategoryNotFoundException::withId($command->categoryId);
            }
        });
    }

    public function getById(int $categoryId): CategoryDTO
    {
        $id = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
        $category = $this->visibleReader->findVisibleById($id);

        if ($category === null) {
            throw CategoryNotFoundException::withId($id);
        }

        return $category;
    }

    public function listRootCategories(
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO {
        return $this->visibleReader->listVisibleRootCategories($criteria);
    }

    public function listChildren(
        int $parentId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO {
        $id = (new CategoryIdDTO($parentId, 'parentId'))->value;

        return $this->visibleReader->listVisibleChildren($id, $criteria);
    }

    public function getByIdForManagement(
        int $categoryId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryDTO {
        $id = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
        $category = $this->managementReader->findById($id, $deletedState);

        if ($category === null) {
            throw CategoryNotFoundException::withId($id);
        }

        return $category;
    }

    public function getByCode(
        string $code,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryDTO {
        if (trim($code) === '') {
            throw CategoryInvalidArgumentException::emptyField('code');
        }
        if (mb_strlen($code) > 100) {
            throw CategoryInvalidArgumentException::fieldTooLong('code', 100);
        }

        $category = $this->managementReader->findByCode($code, $deletedState);
        if ($category === null) {
            throw CategoryNotFoundException::withCode($code);
        }

        return $category;
    }

    public function listForManagement(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->managementReader->listCategories($criteria);
    }

    public function listRootCategoriesForManagement(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->managementReader->listRootCategories($criteria);
    }

    public function listChildrenForManagement(int $parentId, CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        $id = (new CategoryIdDTO($parentId, 'parentId'))->value;

        return $this->managementReader->listChildren($id, $criteria);
    }

    public function paginateForManagement(
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->managementReader->paginateCategories($criteria, $pageRequest);
    }

    public function paginateRootCategoriesForManagement(
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->managementReader->paginateRootCategories($criteria, $pageRequest);
    }

    public function paginateChildrenForManagement(
        int $parentId,
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        $id = (new CategoryIdDTO($parentId, 'parentId'))->value;

        return $this->managementReader->paginateChildren($id, $criteria, $pageRequest);
    }

    private function requireActiveCategoryForUpdate(int $categoryId): CategoryDTO
    {
        $category = $this->queryReader->findActiveByIdForUpdate($categoryId);

        if ($category === null) {
            throw CategoryNotFoundException::withId($categoryId);
        }

        return $category;
    }

    private function requireCategoryForUpdate(int $categoryId): CategoryDTO
    {
        $category = $this->queryReader->findByIdForUpdate($categoryId);

        if ($category === null) {
            throw CategoryNotFoundException::withId($categoryId);
        }

        return $category;
    }

    private function assertMoveDoesNotCreateCycle(int $categoryId, int $newParentId): void
    {
        /** @var array<int, true> $visited */
        $visited = [$categoryId => true];
        $currentId = $newParentId;

        while (true) {
            if (isset($visited[$currentId])) {
                throw CategoryCycleException::forMove($categoryId, $newParentId);
            }

            $visited[$currentId] = true;
            $parent = $this->requireActiveCategoryForUpdate($currentId);
            if ($parent->parentId === null) {
                return;
            }

            $currentId = $parent->parentId;
        }
    }
}
