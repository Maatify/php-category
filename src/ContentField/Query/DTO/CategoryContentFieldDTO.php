<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Query\DTO;

use DateTimeImmutable;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;

/** Host-defined extensible content stored for one exact Category scope. */
final readonly class CategoryContentFieldDTO implements \JsonSerializable
{
    public ?string $languageCode;
    public ?string $platform;

    public function __construct(
        public int $id,
        public int $categoryId,
        public string $fieldKey,
        ?string $languageCode,
        ?string $platform,
        public CategoryContentFieldFormatEnum $format,
        public string $value,
        public int $displayOrder,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
        if ($id < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('id');
        }

        if ($categoryId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('categoryId');
        }

        if (trim($fieldKey) === '') {
            throw CategoryInvalidArgumentException::emptyField('fieldKey');
        }

        if (mb_strlen($fieldKey) > 100) {
            throw CategoryInvalidArgumentException::fieldTooLong('fieldKey', 100);
        }

        $scope = new CategoryContentFieldScopeDTO($languageCode, $platform);
        $this->languageCode = $scope->languageCode;
        $this->platform = $scope->platform;

        if ($displayOrder < 1) {
            throw CategoryInvalidArgumentException::invalidDisplayOrder($displayOrder);
        }

        $this->format->assertValue($this->value);
    }

    /** @return array{id: int, categoryId: int, fieldKey: string, languageCode: ?string, platform: ?string, format: string, value: string, displayOrder: int, createdAt: string, updatedAt: string, deletedAt: ?string} */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->categoryId,
            'fieldKey' => $this->fieldKey,
            'languageCode' => $this->languageCode,
            'platform' => $this->platform,
            'format' => $this->format->value,
            'value' => $this->value,
            'displayOrder' => $this->displayOrder,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'deletedAt' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }
}
