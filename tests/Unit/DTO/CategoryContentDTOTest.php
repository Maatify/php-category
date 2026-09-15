<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\DTO;

use DateTimeImmutable;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CategoryContentDTOTest extends TestCase
{
    public function testItRepresentsContentIdentityAndFields(): void
    {
        $content = new CategoryContentDTO(
            id: 11,
            categoryId: 7,
            languageCode: 'ar-EG',
            name: 'قمصان',
            description: 'وصف الفئة',
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            updatedAt: new DateTimeImmutable('2026-01-02 00:00:00 Africa/Cairo'),
            deletedAt: null,
        );

        self::assertSame(11, $content->id);
        self::assertSame(7, $content->categoryId);
        self::assertSame('ar-EG', $content->languageCode);
        self::assertSame('قمصان', $content->name);
        self::assertSame('وصف الفئة', $content->description);
        self::assertNull($content->deletedAt);
    }

    public function testItRejectsANonPositiveCategoryId(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new CategoryContentDTO(
            id: 11,
            categoryId: 0,
            languageCode: 'en',
            name: 'Shirts',
            description: null,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            updatedAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            deletedAt: null,
        );
    }

    public function testItRepresentsUnlocalizedContentWithANullLanguageCode(): void
    {
        $content = new CategoryContentDTO(
            id: 12,
            categoryId: 7,
            languageCode: null,
            name: 'Shirts',
            description: null,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            updatedAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            deletedAt: null,
        );

        self::assertNull($content->languageCode);
    }

    public function testItRejectsAnEmptyLanguageCodeButAllowsNull(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new CategoryContentDTO(
            id: 12,
            categoryId: 7,
            languageCode: '',
            name: 'Shirts',
            description: null,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            updatedAt: new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo'),
            deletedAt: null,
        );
    }
}
