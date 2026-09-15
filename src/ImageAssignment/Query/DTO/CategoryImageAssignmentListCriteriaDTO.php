<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\DTO;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;

/** Bounded management criteria; scope controls language/platform and roleFilter controls Role matching. */
final readonly class CategoryImageAssignmentListCriteriaDTO implements \JsonSerializable
{
    public const DEFAULT_MAX_RESULTS = 100;
    public const MAX_MAX_RESULTS = 100;

    public CategoryImageAssignmentRoleFilterDTO $roleFilter;

    public function __construct(
        public ?int $categoryId = null,
        public ?CategoryImageAssignmentScopeDTO $scope = null,
        public CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
        public int $maxResults = self::DEFAULT_MAX_RESULTS,
        ?CategoryImageAssignmentRoleFilterDTO $roleFilter = null,
    ) {
        if ($categoryId !== null && $categoryId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('categoryId');
        }

        if ($maxResults < 1 || $maxResults > self::MAX_MAX_RESULTS) {
            throw CategoryInvalidArgumentException::invalidListLimit($maxResults, self::MAX_MAX_RESULTS);
        }

        $this->roleFilter = $roleFilter
            ?? ($scope === null
                ? CategoryImageAssignmentRoleFilterDTO::omitted()
                : ($scope->roleId === null
                    ? CategoryImageAssignmentRoleFilterDTO::exactNull()
                    : CategoryImageAssignmentRoleFilterDTO::forRole($scope->roleId)));
    }

    /** @return array{categoryId: ?int, scope: ?array{languageCode: ?string, platform: ?string, roleId: ?int}, deletedState: string, maxResults: int, roleFilter: array{mode: string, roleId: ?int}} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'scope' => $this->scope?->jsonSerialize(),
            'deletedState' => $this->deletedState->value,
            'maxResults' => $this->maxResults,
            'roleFilter' => $this->roleFilter->jsonSerialize(),
        ];
    }
}
