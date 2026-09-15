<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Mutation\Command;

use Maatify\Category\Common\DTO\CategoryIdDTO;

/** Validated command for restoring a Category Content with its identity. */
final readonly class RestoreCategoryContentCommand implements \JsonSerializable
{
    public int $contentId;

    public function __construct(int|string $contentId)
    {
        $this->contentId = (new CategoryIdDTO($contentId, 'contentId'))->value;
    }

    /** @return array{contentId: int} */
    public function jsonSerialize(): mixed
    {
        return ['contentId' => $this->contentId];
    }
}
