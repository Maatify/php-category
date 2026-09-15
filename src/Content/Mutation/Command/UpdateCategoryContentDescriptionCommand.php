<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated inline mutation for changing only a Category Content description. */
final readonly class UpdateCategoryContentDescriptionCommand implements \JsonSerializable
{
    public int $contentId;

    public function __construct(int|string $contentId, public ?string $description)
    {
        $this->contentId = (new CategoryIdDTO($contentId, 'contentId'))->value;
    }

    /** @return array{contentId: int, description: ?string} */
    public function jsonSerialize(): mixed
    {
        return [
            'contentId' => $this->contentId,
            'description' => $this->description,
        ];
    }
}
