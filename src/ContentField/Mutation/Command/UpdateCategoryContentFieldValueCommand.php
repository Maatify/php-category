<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/**
 * Validated inline mutation for changing a Content Field value.
 *
 * The service preserves the stored format and delegates format/value together
 * to the existing atomic full update operation.
 */
final readonly class UpdateCategoryContentFieldValueCommand implements \JsonSerializable
{
    public int $fieldId;

    public function __construct(int|string $fieldId, public string $value)
    {
        $this->fieldId = (new CategoryIdDTO($fieldId, 'fieldId'))->value;
    }

    /** @return array{fieldId: int, value: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'fieldId' => $this->fieldId,
            'value' => $this->value,
        ];
    }
}
