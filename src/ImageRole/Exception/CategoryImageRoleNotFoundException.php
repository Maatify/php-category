<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;

final class CategoryImageRoleNotFoundException extends ResourceNotFoundMaatifyException
    implements CategoryExceptionInterface
{
    public static function withId(int $roleId): self
    {
        return new self(sprintf('Category Image Role with id %d was not found.', $roleId));
    }

    public static function withKey(string $roleKey): self
    {
        return new self(sprintf('Category Image Role with key "%s" was not found.', $roleKey));
    }
}
