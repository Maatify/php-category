<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\DTO;

use DateTimeImmutable;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;

/** Immutable Category-owned reference to a host Media Asset. */
final readonly class CategoryImageAssignmentDTO implements \JsonSerializable
{
    public ?string $languageCode;
    public ?string $platform;

    public function __construct(
        public int $id,
        public int $categoryId,
        public int $mediaAssetId,
        ?string $languageCode,
        ?string $platform,
        public int $displayOrder,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $deletedAt,
        public ?int $roleId = null,
        public bool $isDefault = false,
    ) {
        if ($id < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('id');
        }

        if ($categoryId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('categoryId');
        }

        if ($mediaAssetId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('mediaAssetId');
        }

        if ($roleId !== null && $roleId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('roleId');
        }

        if ($displayOrder < 1) {
            throw CategoryInvalidArgumentException::invalidDisplayOrder($displayOrder);
        }

        $scope = new CategoryImageAssignmentScopeDTO($languageCode, $platform);
        $this->languageCode = $scope->languageCode;
        $this->platform = $scope->platform;
    }

    /** @return array{id: int, categoryId: int, mediaAssetId: int, roleId: ?int, languageCode: ?string, platform: ?string, displayOrder: int, createdAt: string, updatedAt: string, deletedAt: ?string, isDefault: bool} */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->categoryId,
            'mediaAssetId' => $this->mediaAssetId,
            'roleId' => $this->roleId,
            'languageCode' => $this->languageCode,
            'platform' => $this->platform,
            'displayOrder' => $this->displayOrder,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'deletedAt' => $this->deletedAt?->format(DATE_ATOM),
            'isDefault' => $this->isDefault,
        ];
    }
}
