<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Mutation\Command;

use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Validated command for creating a Host-defined Category Content Field. */
final readonly class CreateCategoryContentFieldCommand implements \JsonSerializable
{
    public int $categoryId;
    public string $fieldKey;
    public CategoryContentFieldScopeDTO $scope;
    public ?string $languageCode;
    public ?string $platform;

    public function __construct(
        int|string $categoryId,
        string $fieldKey,
        ?string $languageCode,
        ?string $platform,
        public CategoryContentFieldFormatEnum $format,
        public string $value,
    ) {
        if (trim($fieldKey) === '') {
            throw CategoryInvalidArgumentException::emptyField('fieldKey');
        }

        if (mb_strlen($fieldKey) > 100) {
            throw CategoryInvalidArgumentException::fieldTooLong('fieldKey', 100);
        }

        $this->categoryId = (new CategoryIdDTO($categoryId, 'categoryId'))->value;
        $this->fieldKey = $fieldKey;
        $this->scope = new CategoryContentFieldScopeDTO($languageCode, $platform);
        $this->languageCode = $this->scope->languageCode;
        $this->platform = $this->scope->platform;
        $this->format->assertValue($this->value);
    }

    /** @return array{categoryId: int, fieldKey: string, languageCode: ?string, platform: ?string, format: string, value: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'categoryId' => $this->categoryId,
            'fieldKey' => $this->fieldKey,
            'languageCode' => $this->languageCode,
            'platform' => $this->platform,
            'format' => $this->format->value,
            'value' => $this->value,
        ];
    }
}
