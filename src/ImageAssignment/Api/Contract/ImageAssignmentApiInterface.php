<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Api\Contract;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public application contract for ImageAssignment business mutations and visible/management reads and lists. */
interface ImageAssignmentApiInterface
{
    public function assign(CreateCategoryImageAssignmentCommand $command): int;

    public function reorder(UpdateCategoryImageAssignmentDisplayOrderCommand $command): void;

    public function setDefault(SetCategoryImageAssignmentDefaultCommand $command): void;

    public function clearDefault(ClearCategoryImageAssignmentDefaultCommand $command): void;

    /** Removes the assignment reversibly; use restore() to make it visible again. */
    public function remove(SoftDeleteCategoryImageAssignmentCommand $command): void;

    public function restore(RestoreCategoryImageAssignmentCommand $command): void;

    public function listVisibleForCategory(
        int $categoryId,
        CategoryImageAssignmentScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryImageAssignmentCollectionDTO;

    /** @throws \Maatify\Category\ImageAssignment\Exception\CategoryImageAssignmentNotFoundException */
    public function getByIdForManagement(
        int $assignmentId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageAssignmentDTO;

    public function listForManagement(
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): CategoryImageAssignmentCollectionDTO;

    /** @return PageResult<CategoryImageAssignmentDTO> */
    public function paginateForManagement(
        CategoryImageAssignmentListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult;
}
