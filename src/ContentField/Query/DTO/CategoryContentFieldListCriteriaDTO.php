<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Query\DTO;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;

/** Bounded management criteria for Category Content Field reads. */
final readonly class CategoryContentFieldListCriteriaDTO implements \JsonSerializable
{
    public const DEFAULT_MAX_RESULTS = 100;
    public const MAX_MAX_RESULTS = 100;

    public function __construct(
        public ?int $categoryId = null,
        public ?string $fieldKey = null,
        public ?CategoryContentFieldScopeDTO $scope = null,
        public CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
        public int $maxResults = self::DEFAULT_MAX_RESULTS,
    ) {
        if ($categoryId !== null && $categoryId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('categoryId');
        }

        if ($fieldKey !== null && trim($fieldKey) === '') {
            throw CategoryInvalidArgumentException::emptyField('fieldKey');
        }

        if ($fieldKey !== null && mb_strlen($fieldKey) > 100) {
            throw CategoryInvalidArgumentException::fieldTooLong('fieldKey', 100);
        }

        if ($maxResults < 1 || $maxResults > self::MAX_MAX_RESULTS) {
            throw CategoryInvalidArgumentException::invalidListLimit($maxResults, self::MAX_MAX_RESULTS);
        }
    }

    /** @return array{categoryId: ?int, fieldKey: ?string, scope: ?array{languageCode: ?string, platform: ?string}, deletedState: string, maxResults: int} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'fieldKey' => $this->fieldKey,
            'scope' => $this->scope?->jsonSerialize(),
            'deletedState' => $this->deletedState->value,
            'maxResults' => $this->maxResults,
        ];
    }
}
