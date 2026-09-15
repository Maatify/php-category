<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Api;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Contract\ImageRoleServiceInterface;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleCollectionDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public Category Image Role domain API; reads are management-only by contract. */
final readonly class ImageRoleApi implements ImageRoleApiInterface
{
    public function __construct(private ImageRoleServiceInterface $service) {}

    public function create(CreateCategoryImageRoleCommand $command): int
    {
        return $this->service->create($command);
    }

    public function updateStatus(UpdateCategoryImageRoleStatusCommand $command): void
    {
        $this->service->updateStatus($command);
    }

    public function softDelete(SoftDeleteCategoryImageRoleCommand $command): void
    {
        $this->service->softDelete($command);
    }

    public function restore(RestoreCategoryImageRoleCommand $command): void
    {
        $this->service->restore($command);
    }

    public function getByIdForManagement(
        int $roleId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageRoleDTO {
        return $this->service->getByIdForManagement($roleId, $deletedState);
    }

    public function getByKeyForManagement(
        string $roleKey,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageRoleDTO {
        return $this->service->getByKeyForManagement($roleKey, $deletedState);
    }

    public function listForManagement(CategoryImageRoleListCriteriaDTO $criteria): CategoryImageRoleCollectionDTO
    {
        return $this->service->listForManagement($criteria);
    }

    public function paginateForManagement(CategoryImageRoleListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult
    {
        return $this->service->paginateForManagement($criteria, $pageRequest);
    }
}
