<?php

declare(strict_types=1);

namespace Maatify\Category\Common\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Throwable;

/** Represents Category-owned persistence and storage-shape failures. */
final class CategoryPersistenceException extends SystemMaatifyException
    implements CategoryExceptionInterface
{
    protected function defaultErrorCode(): ErrorCodeEnum
    {
        return ErrorCodeEnum::MAATIFY_ERROR;
    }

    public static function queryFailed(string $resource): self
    {
        return new self(sprintf('Category query failed for %s.', $resource));
    }

    public static function invalidAutoIncrementIdentity(): self
    {
        return new self('Category AUTO_INCREMENT did not return a valid identity.');
    }

    public static function invalidContentAutoIncrementIdentity(): self
    {
        return new self('Category Content AUTO_INCREMENT did not return a valid identity.');
    }

    public static function invalidImageAssignmentAutoIncrementIdentity(): self
    {
        return new self('Category Image Assignment AUTO_INCREMENT did not return a valid identity.');
    }

    public static function invalidImageRoleAutoIncrementIdentity(): self
    {
        return new self('Category Image Role AUTO_INCREMENT did not return a valid identity.');
    }

    public static function imageRoleRepositoryNotConfigured(): self
    {
        return new self('Category Image Role command repository is not configured.');
    }

    public static function invalidContentFieldAutoIncrementIdentity(): self
    {
        return new self('Category Content Field AUTO_INCREMENT did not return a valid identity.');
    }

    public static function invalidStorageValue(string $column, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Category storage column "%s" contains an invalid value.', $column),
            0,
            $previous,
        );
    }

    public static function unexpectedColumnType(string $column): self
    {
        return new self(sprintf('Category storage column "%s" has an unexpected type.', $column));
    }
}
