<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Api;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Contract\ImageAssignmentServiceInterface;
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

/** Public Category Image Assignment API; images() on the facade means assignments only. */
final readonly class ImageAssignmentApi implements ImageAssignmentApiInterface
{
    public function __construct(private ImageAssignmentServiceInterface $service) {}

    public function assign(CreateCategoryImageAssignmentCommand $command): int
    {
        return $this->service->assign($command);
    }

    public function reorder(UpdateCategoryImageAssignmentDisplayOrderCommand $command): void
    {
        $this->service->reorder($command);
    }

    public function setDefault(SetCategoryImageAssignmentDefaultCommand $command): void
    {
        $this->service->setDefault($command);
    }

    public function clearDefault(ClearCategoryImageAssignmentDefaultCommand $command): void
    {
        $this->service->clearDefault($command);
    }

    public function remove(SoftDeleteCategoryImageAssignmentCommand $command): void
    {
        $this->service->remove($command);
    }

    public function restore(RestoreCategoryImageAssignmentCommand $command): void
    {
        $this->service->restore($command);
    }

    public function listVisibleForCategory(
        int $categoryId,
        CategoryImageAssignmentScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryImageAssignmentCollectionDTO {
        return $this->service->listVisibleForCategory($categoryId, $scope, $criteria);
    }

    public function getByIdForManagement(
        int $assignmentId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageAssignmentDTO {
        return $this->service->getByIdForManagement($assignmentId, $deletedState);
    }

    public function listForManagement(
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): CategoryImageAssignmentCollectionDTO {
        return $this->service->listForManagement($criteria);
    }

    public function paginateForManagement(
        CategoryImageAssignmentListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->service->paginateForManagement($criteria, $pageRequest);
    }
}
