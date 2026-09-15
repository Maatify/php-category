<?php

declare(strict_types=1);

namespace Maatify\Category\Query\DTO;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Typed, bounded criteria for management Category list reads. */
final readonly class CategoryListCriteriaDTO implements \JsonSerializable
{
    public const DEFAULT_MAX_RESULTS = 100;
    public const MAX_MAX_RESULTS = 100;

    public function __construct(
        public ?CategoryStatusEnum $status = null,
        public CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
        public int $maxResults = self::DEFAULT_MAX_RESULTS,
        public ?string $search = null,
    ) {
        if ($maxResults < 1 || $maxResults > self::MAX_MAX_RESULTS) {
            throw CategoryInvalidArgumentException::invalidListLimit($maxResults, self::MAX_MAX_RESULTS);
        }
        if ($search !== null && trim($search) === '') {
            throw CategoryInvalidArgumentException::emptyField('search');
        }
        if ($search !== null && mb_strlen($search) > 100) {
            throw CategoryInvalidArgumentException::fieldTooLong('search', 100);
        }
    }

    /** @return array{status: ?string, deletedState: string, maxResults: int, search: ?string} */
    public function jsonSerialize(): mixed
    {
        return [
            'status' => $this->status?->value,
            'deletedState' => $this->deletedState->value,
            'maxResults' => $this->maxResults,
            'search' => $this->search,
        ];
    }
}
