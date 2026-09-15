<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration\Support;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\SharedCommon\Contracts\ClockInterface;

final class FixedCategoryClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $value = '2026-01-03 00:00:00 Africa/Cairo')
    {
        $this->now = new DateTimeImmutable($value);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->now->getTimezone();
    }
}
