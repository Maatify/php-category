<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;

/** Value-only mutation; Category Content Field identity is immutable. */
final readonly class UpdateCategoryContentFieldCommand implements \JsonSerializable
{
    public int $fieldId;

    public function __construct(
        int|string $fieldId,
        public CategoryContentFieldFormatEnum $format,
        public string $value,
    ) {
        $this->fieldId = (new CategoryIdDTO($fieldId, 'fieldId'))->value;
        $this->format->assertValue($this->value);
    }

    /** @return array{fieldId: int, format: string, value: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'fieldId' => $this->fieldId,
            'format' => $this->format->value,
            'value' => $this->value,
        ];
    }
}
