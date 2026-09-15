<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Query\Contract;

use Maatify\Category\ImageRole\CategoryImageRoleDTO;

/** Read port consumed by Image Role mutation orchestration. */
interface CategoryImageRoleQueryReaderInterface
{
    public function findImageRoleByIdForUpdate(int $roleId): ?CategoryImageRoleDTO;
}
