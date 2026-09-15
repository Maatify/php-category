<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Contract;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleCollectionDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public application contract for ImageRole business mutations and management reads. */
interface ImageRoleServiceInterface
{
    public function create(CreateCategoryImageRoleCommand $command): int;

    public function updateStatus(UpdateCategoryImageRoleStatusCommand $command): void;

    public function softDelete(SoftDeleteCategoryImageRoleCommand $command): void;

    public function restore(RestoreCategoryImageRoleCommand $command): void;

    /** @throws \Maatify\Category\ImageRole\Exception\CategoryImageRoleNotFoundException */
    public function getByIdForManagement(
        int $roleId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageRoleDTO;

    /** @throws \Maatify\Category\ImageRole\Exception\CategoryImageRoleNotFoundException */
    public function getByKeyForManagement(
        string $roleKey,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryImageRoleDTO;

    public function listForManagement(CategoryImageRoleListCriteriaDTO $criteria): CategoryImageRoleCollectionDTO;

    /** @return PageResult<CategoryImageRoleDTO> */
    public function paginateForManagement(CategoryImageRoleListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult;
}
