<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\SharedCommon\Contracts\ClockInterface;

/** @internal Test-only deterministic application clock. */
final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new DateTimeImmutable('2026-01-03 00:00:00 Africa/Cairo');
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
