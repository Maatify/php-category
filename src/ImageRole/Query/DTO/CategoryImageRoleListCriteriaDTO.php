<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Query\DTO;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Bounded management criteria for the package-owned Image Role registry. */
final readonly class CategoryImageRoleListCriteriaDTO implements \JsonSerializable
{
    public const DEFAULT_MAX_RESULTS = 100;
    public const MAX_MAX_RESULTS = 100;

    public function __construct(
        public ?CategoryImageRoleStatusEnum $status = null,
        public CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
        public int $maxResults = self::DEFAULT_MAX_RESULTS,
    ) {
        if ($maxResults < 1 || $maxResults > self::MAX_MAX_RESULTS) {
            throw CategoryInvalidArgumentException::invalidListLimit($maxResults, self::MAX_MAX_RESULTS);
        }
    }

    /** @return array{status: ?string, deletedState: string, maxResults: int} */
    public function jsonSerialize(): mixed
    {
        return [
            'status' => $this->status?->value,
            'deletedState' => $this->deletedState->value,
            'maxResults' => $this->maxResults,
        ];
    }
}
