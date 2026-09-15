<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Service;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Contract\CategoryImageAssignmentCommandRepositoryInterface;
use Maatify\Category\ImageAssignment\Contract\ImageAssignmentServiceInterface;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Exception\CategoryImageAssignmentNotFoundException;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentManagementReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentQueryReaderInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentReadQueryInterface;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Exception\CategoryImageRoleNotFoundException;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\ImageRole\Lifecycle\Exception\CategoryImageRoleUnavailableException;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleQueryReaderInterface;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\SharedCommon\Contracts\ClockInterface;

/** Owns Category Image Assignment lifecycle, defaults, and exact-scope ordering only. */
final readonly class ImageAssignmentService implements ImageAssignmentServiceInterface
{
    public function __construct(
        private CategoryImageAssignmentCommandRepositoryInterface $commandRepository,
        private CategoryQueryReaderInterface $categoryQueryReader,
        private CategoryImageRoleQueryReaderInterface $roleQueryReader,
        private CategoryImageAssignmentQueryReaderInterface $queryReader,
        private CategoryImageAssignmentReadQueryInterface $visibleReader,
        private CategoryImageAssignmentManagementReadQueryInterface $managementReader,
        private TransactionRunnerInterface $transaction,
        private ClockInterface $clock,
    ) {}

    public function assign(CreateCategoryImageAssignmentCommand $command): int
    {
        return $this->transaction->run(function () use ($command): int {
            $this->requireActiveCategoryForUpdate($command->categoryId);
            $this->requireActiveImageRoleForUpdate($command->roleId);

            return $this->commandRepository->create($command, $this->clock->now());
        });
    }

    public function reorder(UpdateCategoryImageAssignmentDisplayOrderCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveAssignmentForUpdate($command->assignmentId);

            if (!$this->commandRepository->updateDisplayOrder($command, $this->clock->now())) {
                throw CategoryImageAssignmentNotFoundException::withId($command->assignmentId);
            }
        });
    }

    public function setDefault(SetCategoryImageAssignmentDefaultCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            if (!$this->commandRepository->setDefault($command, $this->clock->now())) {
                throw CategoryImageAssignmentNotFoundException::withId($command->assignmentId);
            }
        });
    }

    public function clearDefault(ClearCategoryImageAssignmentDefaultCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            if (!$this->commandRepository->clearDefault($command, $this->clock->now())) {
                throw CategoryImageAssignmentNotFoundException::withId($command->assignmentId);
            }
        });
    }

    public function remove(SoftDeleteCategoryImageAssignmentCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveAssignmentForUpdate($command->assignmentId);

            if (!$this->commandRepository->softDelete($command, $this->clock->now())) {
                throw CategoryImageAssignmentNotFoundException::withId($command->assignmentId);
            }
        });
    }

    public function restore(RestoreCategoryImageAssignmentCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireAssignmentForUpdate($command->assignmentId);

            if (!$this->commandRepository->restore($command, $this->clock->now())) {
                throw CategoryImageAssignmentNotFoundException::withId($command->assignmentId);
            }
        });
    }

    public function listVisibleForCategory(
        int $categoryId,
        CategoryImageAssignmentScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryImageAssignmentCollectionDTO {
        $id = (new CategoryIdDTO($categoryId, 'categoryId'))->value;

        return $this->visibleReader->listVisibleImageAssignments($id, $scope, $criteria);
    }

    public function getByIdForManagement(
        int $assignmentId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageAssignmentDTO {
        $id = (new CategoryIdDTO($assignmentId, 'assignmentId'))->value;
        $assignment = $this->managementReader->findImageAssignmentById($id, $deletedState);

        if ($assignment === null) {
            throw CategoryImageAssignmentNotFoundException::withId($id);
        }

        return $assignment;
    }

    public function listForManagement(
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): CategoryImageAssignmentCollectionDTO {
        return $this->managementReader->listImageAssignments($criteria);
    }

    public function paginateForManagement(
        CategoryImageAssignmentListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->managementReader->paginateImageAssignments($criteria, $pageRequest);
    }

    private function requireActiveCategoryForUpdate(int $categoryId): void
    {
        if ($this->categoryQueryReader->findActiveByIdForUpdate($categoryId) === null) {
            throw CategoryNotFoundException::withId($categoryId);
        }
    }

    private function requireActiveImageRoleForUpdate(?int $roleId): void
    {
        if ($roleId === null) {
            return;
        }

        $role = $this->requireImageRoleForUpdate($roleId);
        if ($role->deletedAt !== null || $role->status !== CategoryImageRoleStatusEnum::ACTIVE) {
            throw CategoryImageRoleUnavailableException::withId($roleId);
        }
    }

    private function requireImageRoleForUpdate(int $roleId): CategoryImageRoleDTO
    {
        $role = $this->roleQueryReader->findImageRoleByIdForUpdate($roleId);

        if ($role === null) {
            throw CategoryImageRoleNotFoundException::withId($roleId);
        }

        return $role;
    }

    private function requireActiveAssignmentForUpdate(int $assignmentId): CategoryImageAssignmentDTO
    {
        $assignment = $this->requireAssignmentForUpdate($assignmentId);

        if ($assignment->deletedAt !== null) {
            throw CategoryImageAssignmentNotFoundException::withId($assignmentId);
        }

        return $assignment;
    }

    private function requireAssignmentForUpdate(int $assignmentId): CategoryImageAssignmentDTO
    {
        $assignment = $this->queryReader->findImageAssignmentByIdForUpdate($assignmentId);

        if ($assignment === null) {
            throw CategoryImageAssignmentNotFoundException::withId($assignmentId);
        }

        return $assignment;
    }
}
