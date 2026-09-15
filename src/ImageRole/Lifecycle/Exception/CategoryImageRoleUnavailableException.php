<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Lifecycle\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;

final class CategoryImageRoleUnavailableException extends GenericConflictMaatifyException
    implements CategoryExceptionInterface
{
    public static function withId(int $roleId): self
    {
        return new self(sprintf(
            'Category Image Role with id %d is inactive or soft-deleted and cannot accept new assignments.',
            $roleId,
        ));
    }
}
