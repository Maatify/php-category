<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/**
 * Validated command for changing Content fields.
 *
 * The Category Content logical identity is deliberately absent: neither
 * category_id nor language_code can be changed through this contract.
 */
final readonly class UpdateCategoryContentCommand implements \JsonSerializable
{
    public int $contentId;
    public string $name;
    public ?string $description;

    public function __construct(int|string $contentId, string $name, ?string $description)
    {
        if (trim($name) === '') {
            throw CategoryInvalidArgumentException::emptyField('name');
        }

        if (mb_strlen($name) > 255) {
            throw CategoryInvalidArgumentException::fieldTooLong('name', 255);
        }

        $this->contentId = (new CategoryIdDTO($contentId, 'contentId'))->value;
        $this->name = $name;
        $this->description = $description;
    }

    /** @return array{contentId: int, name: string, description: ?string} */
    public function jsonSerialize(): mixed
    {
        return [
            'contentId' => $this->contentId,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
