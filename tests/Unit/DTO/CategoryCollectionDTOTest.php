<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\DTO;

use DateTimeImmutable;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use PHPUnit\Framework\TestCase;

final class CategoryCollectionDTOTest extends TestCase
{
    public function testItExposesTypedCategoriesWithoutAnAssociativeArrayContract(): void
    {
        $first = $this->category(1, 'first');
        $second = $this->category(2, 'second');
        $collection = new CategoryCollectionDTO([$first, $second]);

        self::assertCount(2, $collection);
        self::assertFalse($collection->isEmpty());
        self::assertSame([$first, $second], iterator_to_array($collection));
    }

    public function testItRepresentsAnEmptyQueryResult(): void
    {
        $collection = new CategoryCollectionDTO([]);

        self::assertCount(0, $collection);
        self::assertTrue($collection->isEmpty());
        self::assertSame([], iterator_to_array($collection));
    }

    private function category(int $id, string $code): CategoryDTO
    {
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');

        return new CategoryDTO(
            id: $id,
            parentId: null,
            code: $code,
            status: CategoryStatusEnum::ACTIVE,
            displayOrder: $id,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
    }
}
