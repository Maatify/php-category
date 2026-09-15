<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\DTO;

use DateTimeImmutable;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

final readonly class CategoryContentDTO implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public int $categoryId,
        public ?string $languageCode,
        public string $name,
        public ?string $description,
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

        if ($languageCode !== null && trim($languageCode) === '') {
            throw CategoryInvalidArgumentException::emptyField('languageCode');
        }

        if ($languageCode !== null && mb_strlen($languageCode) > 16) {
            throw CategoryInvalidArgumentException::fieldTooLong('languageCode', 16);
        }

        if (trim($name) === '') {
            throw CategoryInvalidArgumentException::emptyField('name');
        }

        if (mb_strlen($name) > 255) {
            throw CategoryInvalidArgumentException::fieldTooLong('name', 255);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     categoryId: int,
     *     languageCode: ?string,
     *     name: string,
     *     description: ?string,
     *     createdAt: string,
     *     updatedAt: string,
     *     deletedAt: ?string
     * }
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->categoryId,
            'languageCode' => $this->languageCode,
            'name' => $this->name,
            'description' => $this->description,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'deletedAt' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }
}
