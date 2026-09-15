<?php

declare(strict_types=1);

namespace Maatify\Category\Common\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use Throwable;

final class CategoryInvalidArgumentException extends InvalidArgumentMaatifyException
    implements CategoryExceptionInterface
{
    public static function emptyField(string $field): self
    {
        return new self(sprintf('Field "%s" must not be empty.', $field));
    }

    public static function fieldTooLong(string $field, int $maxLength): self
    {
        return new self(sprintf('Field "%s" must not exceed %d characters.', $field, $maxLength));
    }

    public static function invalidId(string $field): self
    {
        return new self(sprintf('Field "%s" must be a canonical positive integer.', $field));
    }

    public static function nonPositiveId(string $field): self
    {
        return new self(sprintf('Field "%s" must be a positive integer.', $field));
    }

    public static function invalidDisplayOrder(int $displayOrder): self
    {
        return new self(sprintf('Display order must be a positive integer, got %d.', $displayOrder));
    }

    public static function selfParent(int $categoryId): self
    {
        return new self(sprintf('Category [%d] cannot be its own parent.', $categoryId));
    }

    public static function invalidListLimit(int $limit, int $maximum): self
    {
        return new self(sprintf(
            'List maxResults must be between 1 and %d, got %d.',
            $maximum,
            $limit,
        ));
    }

    public static function invalidJsonValue(?Throwable $previous = null): self
    {
        return new self('A Category Content Field with format "json" must contain valid JSON.', 0, $previous);
    }
}
