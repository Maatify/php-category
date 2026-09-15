<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Throwable;

/** @internal Test-only transaction port. */
final class InMemoryCategoryTransaction implements TransactionRunnerInterface
{
    public int $runs = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    public function run(callable $operation): mixed
    {
        $this->runs++;

        try {
            $result = $operation();
            $this->commits++;

            return $result;
        } catch (Throwable $exception) {
            $this->rollbacks++;

            throw $exception;
        }
    }
}
