<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Exception;

use Maatify\Category\Exception\CategoryExceptionInterface;
use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;

final class CategoryContentNotFoundException extends ResourceNotFoundMaatifyException
    implements CategoryExceptionInterface
{
    public static function withId(int $contentId): self
    {
        return new self(sprintf('Category Content with id %d was not found.', $contentId));
    }
}
