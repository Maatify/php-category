<?php

declare(strict_types=1);

namespace Maatify\Category\Lifecycle\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;
use Throwable;

final class CategoryCodeAlreadyExistsException extends GenericConflictMaatifyException
    implements CategoryExceptionInterface
{
    public static function withCode(string $code, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Category code "%s" already exists.', $code),
            0,
            $previous,
        );
    }
}
