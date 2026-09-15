<?php

declare(strict_types=1);

namespace Maatify\Category\Hierarchy\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\BusinessRule\BusinessRuleMaatifyException;

final class CategoryCycleException extends BusinessRuleMaatifyException
    implements CategoryExceptionInterface
{
    public static function forMove(int $categoryId, int $newParentId): self
    {
        return new self(sprintf(
            'Moving Category %d under Category %d would create a cycle.',
            $categoryId,
            $newParentId,
        ));
    }
}
