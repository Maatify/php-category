<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Query\Contract;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleCollectionDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Dedicated management read port for Image Roles. */
interface CategoryImageRoleManagementReadQueryInterface
{
    public function findImageRoleById(
        int $roleId,
        CategoryDeletedStateEnum $deletedState,
    ): ?CategoryImageRoleDTO;

    public function findImageRoleByKey(
        string $roleKey,
        CategoryDeletedStateEnum $deletedState,
    ): ?CategoryImageRoleDTO;

    public function listImageRoles(CategoryImageRoleListCriteriaDTO $criteria): CategoryImageRoleCollectionDTO;

    /** @return PageResult<CategoryImageRoleDTO> */
    public function paginateImageRoles(CategoryImageRoleListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult;
}
