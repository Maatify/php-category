<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Validated inline mutation for changing only a Category Content name. */
final readonly class UpdateCategoryContentNameCommand implements \JsonSerializable
{
    public int $contentId;

    public function __construct(int|string $contentId, public string $name)
    {
        if (trim($name) === '') {
            throw CategoryInvalidArgumentException::emptyField('name');
        }

        if (mb_strlen($name) > 255) {
            throw CategoryInvalidArgumentException::fieldTooLong('name', 255);
        }

        $this->contentId = (new CategoryIdDTO($contentId, 'contentId'))->value;
    }

    /** @return array{contentId: int, name: string} */
    public function jsonSerialize(): mixed
    {
        return [
            'contentId' => $this->contentId,
            'name' => $this->name,
        ];
    }
}
