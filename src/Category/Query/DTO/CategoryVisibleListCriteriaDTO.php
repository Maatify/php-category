<?php

declare(strict_types=1);

namespace Maatify\Category\Query\DTO;

use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Typed, bounded criteria for consumer visibility list reads. */
final readonly class CategoryVisibleListCriteriaDTO implements \JsonSerializable
{
    public const DEFAULT_MAX_RESULTS = 100;
    public const MAX_MAX_RESULTS = 100;

    public function __construct(public int $maxResults = self::DEFAULT_MAX_RESULTS)
    {
        if ($maxResults < 1 || $maxResults > self::MAX_MAX_RESULTS) {
            throw CategoryInvalidArgumentException::invalidListLimit($maxResults, self::MAX_MAX_RESULTS);
        }
    }

    /** @return array{maxResults: int} */
    public function jsonSerialize(): mixed
    {
        return ['maxResults' => $this->maxResults];
    }
}
