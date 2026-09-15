<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated restore operation for a Category Content Field. */
final readonly class RestoreCategoryContentFieldCommand implements \JsonSerializable
{
    public int $fieldId;

    public function __construct(int|string $fieldId)
    {
        $this->fieldId = (new CategoryIdDTO($fieldId, 'fieldId'))->value;
    }

    /** @return array{fieldId: int} */
    public function jsonSerialize(): mixed
    {
        return ['fieldId' => $this->fieldId];
    }
}
