<?php

declare(strict_types=1);

namespace Maatify\Category\Lifecycle\Enum;

enum CategoryStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
