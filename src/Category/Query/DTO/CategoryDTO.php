<?php

declare(strict_types=1);

namespace Maatify\Category\Query\DTO;

use DateTimeImmutable;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

final readonly class CategoryDTO implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $code,
        public CategoryStatusEnum $status,
        public int $displayOrder,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
        if ($id < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('id');
        }

        if ($parentId !== null && $parentId < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('parentId');
        }

        if ($parentId === $id) {
            throw CategoryInvalidArgumentException::selfParent($id);
        }

        if ($displayOrder < 1) {
            throw CategoryInvalidArgumentException::invalidDisplayOrder($displayOrder);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     parentId: ?int,
     *     code: string,
     *     status: string,
     *     displayOrder: int,
     *     createdAt: string,
     *     updatedAt: string,
     *     deletedAt: ?string
     * }
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parentId,
            'code' => $this->code,
            'status' => $this->status->value,
            'displayOrder' => $this->displayOrder,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'deletedAt' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }
}
