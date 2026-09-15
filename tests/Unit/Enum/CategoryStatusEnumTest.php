<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Enum;

use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use PHPUnit\Framework\TestCase;

final class CategoryStatusEnumTest extends TestCase
{
    public function testItExposesTheSchemaStatusValues(): void
    {
        self::assertSame(['active', 'inactive'], array_map(
            static fn (CategoryStatusEnum $status): string => $status->value,
            CategoryStatusEnum::cases(),
        ));
    }
}
