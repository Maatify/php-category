<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\Enum;

/** Describes the three supported management Role-filter states. */
enum CategoryImageAssignmentRoleFilterModeEnum: string
{
    case OMITTED = 'omitted';
    case EXACT_NULL = 'exact_null';
    case CONCRETE = 'concrete';
}
