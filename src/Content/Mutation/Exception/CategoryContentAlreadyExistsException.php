<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Mutation\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;
use Throwable;

final class CategoryContentAlreadyExistsException extends GenericConflictMaatifyException
    implements CategoryExceptionInterface
{
    public static function withIdentity(
        int $categoryId,
        ?string $languageCode,
        ?Throwable $previous = null,
    ): self
    {
        $identity = $languageCode === null
            ? 'the unlocalized language identity'
            : sprintf('language "%s"', $languageCode);

        return new self(
            sprintf(
                'Category Content for Category %d and %s already exists.',
                $categoryId,
                $identity,
            ),
            0,
            $previous,
        );
    }
}
