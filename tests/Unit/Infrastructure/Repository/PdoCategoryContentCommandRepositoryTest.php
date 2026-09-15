<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Infrastructure\Repository;

use DateTimeImmutable;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Infrastructure\PdoCategoryContentCommandRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoCategoryContentCommandRepositoryTest extends TestCase
{
    public function testCreatePersistsTheHostWallClockValueWithoutUtcConversion(): void
    {
        $statement = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();
        $statement->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (array $parameters): bool {
                return $parameters['created_at'] === '2026-03-01 14:30:45'
                    && $parameters['updated_at'] === '2026-03-01 14:30:45';
            }))
            ->willReturn(true);

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'lastInsertId'])
            ->getMock();
        $pdo->expects(self::once())
            ->method('prepare')
            ->willReturn($statement);
        $pdo->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('17');

        $repository = new PdoCategoryContentCommandRepository($pdo);
        $id = $repository->create(
            new CreateCategoryContentCommand(5, null, 'Host clock content', null),
            new DateTimeImmutable('2026-03-01 14:30:45 Africa/Cairo'),
        );

        self::assertSame(17, $id);
    }
}
