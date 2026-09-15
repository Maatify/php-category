<?php

declare(strict_types=1);

namespace Maatify\Category\Lifecycle\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\BusinessRule\BusinessRuleMaatifyException;

final class CategoryHasNonDeletedChildrenException extends BusinessRuleMaatifyException
    implements CategoryExceptionInterface
{
    public static function withId(int $categoryId): self
    {
        return new self(sprintf(
            'Category %d cannot be soft-deleted while it has non-deleted children.',
            $categoryId,
        ));
    }
}
