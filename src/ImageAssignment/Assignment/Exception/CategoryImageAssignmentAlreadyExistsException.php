<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Assignment\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;
use Throwable;

final class CategoryImageAssignmentAlreadyExistsException extends GenericConflictMaatifyException
    implements CategoryExceptionInterface
{
    public static function withIdentity(
        int $categoryId,
        int $mediaAssetId,
        ?string $languageCode,
        ?string $platform,
        ?Throwable $previous = null,
        ?int $roleId = null,
    ): self {
        $language = $languageCode === null ? 'NULL language' : sprintf('language "%s"', $languageCode);
        $platformValue = $platform === null ? 'NULL platform' : sprintf('platform "%s"', $platform);
        $roleValue = $roleId === null ? 'NULL role' : sprintf('Role %d', $roleId);

        return new self(
            sprintf(
                'Category Image Assignment for Category %d, Media Asset %d, %s, %s, and %s already exists.',
                $categoryId,
                $mediaAssetId,
                $language,
                $platformValue,
                $roleValue,
            ),
            0,
            $previous,
        );
    }
}
