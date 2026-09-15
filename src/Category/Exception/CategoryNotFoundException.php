<?php

declare(strict_types=1);

namespace Maatify\Category\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;

final class CategoryNotFoundException extends ResourceNotFoundMaatifyException
    implements CategoryExceptionInterface
{
    public static function withId(int $categoryId): self
    {
        return new self(sprintf('Category with id %d was not found.', $categoryId));
    }

    public static function withCode(string $code): self
    {
        return new self(sprintf('Category with code "%s" was not found.', $code));
    }
}
