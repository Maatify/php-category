<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\DTO;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Typed, bounded criteria for management Category Content list reads. */
final readonly class CategoryContentListCriteriaDTO implements \JsonSerializable
{
    public const DEFAULT_MAX_RESULTS = 100;
    public const MAX_MAX_RESULTS = 100;

    public function __construct(
        public ?int $categoryId = null,
        public CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
        public int $maxResults = self::DEFAULT_MAX_RESULTS,
    ) {
        if ($categoryId !== null && $categoryId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('categoryId');
        }

        if ($maxResults < 1 || $maxResults > self::MAX_MAX_RESULTS) {
            throw CategoryInvalidArgumentException::invalidListLimit($maxResults, self::MAX_MAX_RESULTS);
        }
    }

    /** @return array{categoryId: ?int, deletedState: string, maxResults: int} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'deletedState' => $this->deletedState->value,
            'maxResults' => $this->maxResults,
        ];
    }
}
