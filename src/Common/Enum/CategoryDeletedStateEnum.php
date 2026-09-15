<?php

declare(strict_types=1);

namespace Maatify\Category\Common\Enum;

/** Explicit soft-deletion visibility for management reads. */
enum CategoryDeletedStateEnum: string
{
    case NON_DELETED = 'non_deleted';
    case INCLUDE_DELETED = 'include_deleted';
    case DELETED_ONLY = 'deleted_only';
}
