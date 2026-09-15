<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Assignment\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;

/** Validated command for assigning a host Media Asset to a Category scope. */
final readonly class CreateCategoryImageAssignmentCommand implements \JsonSerializable
{
    public int $categoryId;
    public int $mediaAssetId;
    public CategoryImageAssignmentScopeDTO $scope;
    public ?string $languageCode;
    public ?string $platform;
    public ?int $roleId;

    public function __construct(
        int|string $categoryId,
        int|string $mediaAssetId,
        ?CategoryImageAssignmentScopeDTO $scope = null,
    ) {
        $this->categoryId = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
        $this->mediaAssetId = (new CategoryIdDTO($mediaAssetId, 'mediaAssetId'))->value;
        $this->scope = $scope ?? new CategoryImageAssignmentScopeDTO();
        $this->languageCode = $this->scope->languageCode;
        $this->platform = $this->scope->platform;
        $this->roleId = $this->scope->roleId;
    }

    /** @return array{categoryId: int, mediaAssetId: int, languageCode: ?string, platform: ?string, roleId: ?int} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'mediaAssetId' => $this->mediaAssetId,
            'languageCode' => $this->languageCode,
            'platform' => $this->platform,
            'roleId' => $this->roleId,
        ];
    }
}
