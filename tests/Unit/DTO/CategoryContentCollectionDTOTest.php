<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\DTO;

use DateTimeImmutable;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use PHPUnit\Framework\TestCase;

final class CategoryContentCollectionDTOTest extends TestCase
{
    public function testItExposesTypedContentsWithoutAnAssociativeArrayContract(): void
    {
        $first = $this->content(1, null);
        $second = $this->content(2, 'en-US');
        $collection = new CategoryContentCollectionDTO([$first, $second]);

        self::assertCount(2, $collection);
        self::assertFalse($collection->isEmpty());
        self::assertSame([$first, $second], iterator_to_array($collection));
    }

    private function content(int $id, ?string $languageCode): CategoryContentDTO
    {
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');

        return new CategoryContentDTO(
            id: $id,
            categoryId: 7,
            languageCode: $languageCode,
            name: 'Category ' . $id,
            description: null,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
    }
}
