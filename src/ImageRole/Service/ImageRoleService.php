<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Service;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Contract\CategoryImageRoleCommandRepositoryInterface;
use Maatify\Category\ImageRole\Contract\ImageRoleServiceInterface;
use Maatify\Category\ImageRole\Exception\CategoryImageRoleNotFoundException;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleCollectionDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleManagementReadQueryInterface;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleQueryReaderInterface;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\SharedCommon\Contracts\ClockInterface;

/** Owns Image Role lifecycle orchestration only. */
final readonly class ImageRoleService implements ImageRoleServiceInterface
{
    public function __construct(
        private CategoryImageRoleCommandRepositoryInterface $commandRepository,
        private CategoryImageRoleQueryReaderInterface $queryReader,
        private CategoryImageRoleManagementReadQueryInterface $managementReader,
        private TransactionRunnerInterface $transaction,
        private ClockInterface $clock,
    ) {}

    public function create(CreateCategoryImageRoleCommand $command): int
    {
        return $this->transaction->run(
            fn (): int => $this->commandRepository->create($command, $this->clock->now()),
        );
    }

    public function updateStatus(UpdateCategoryImageRoleStatusCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $role = $this->requireImageRoleForUpdate($command->roleId);
            if ($role->deletedAt !== null) {
                throw CategoryImageRoleNotFoundException::withId($command->roleId);
            }

            if (!$this->commandRepository->updateStatus($command, $this->clock->now())) {
                throw CategoryImageRoleNotFoundException::withId($command->roleId);
            }
        });
    }

    public function softDelete(SoftDeleteCategoryImageRoleCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $role = $this->requireImageRoleForUpdate($command->roleId);
            if ($role->deletedAt !== null) {
                throw CategoryImageRoleNotFoundException::withId($command->roleId);
            }

            if (!$this->commandRepository->softDelete($command, $this->clock->now())) {
                throw CategoryImageRoleNotFoundException::withId($command->roleId);
            }
        });
    }

    public function restore(RestoreCategoryImageRoleCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireImageRoleForUpdate($command->roleId);

            if (!$this->commandRepository->restore($command, $this->clock->now())) {
                throw CategoryImageRoleNotFoundException::withId($command->roleId);
            }
        });
    }

    public function getByIdForManagement(
        int $roleId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageRoleDTO {
        $id = (new CategoryIdDTO($roleId, 'roleId'))->value;
        $role = $this->managementReader->findImageRoleById($id, $deletedState);

        if ($role === null) {
            throw CategoryImageRoleNotFoundException::withId($id);
        }

        return $role;
    }

    public function getByKeyForManagement(
        string $roleKey,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageRoleDTO {
        CategoryImageRoleDTO::assertValidRoleKey($roleKey);
        $role = $this->managementReader->findImageRoleByKey($roleKey, $deletedState);

        if ($role === null) {
            throw CategoryImageRoleNotFoundException::withKey($roleKey);
        }

        return $role;
    }

    public function listForManagement(CategoryImageRoleListCriteriaDTO $criteria): CategoryImageRoleCollectionDTO
    {
        return $this->managementReader->listImageRoles($criteria);
    }

    public function paginateForManagement(
        CategoryImageRoleListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->managementReader->paginateImageRoles($criteria, $pageRequest);
    }

    private function requireImageRoleForUpdate(int $roleId): CategoryImageRoleDTO
    {
        $role = $this->queryReader->findImageRoleByIdForUpdate($roleId);

        if ($role === null) {
            throw CategoryImageRoleNotFoundException::withId($roleId);
        }

        return $role;
    }
}
