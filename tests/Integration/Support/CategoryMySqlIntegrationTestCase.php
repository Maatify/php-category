<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration\Support;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class CategoryMySqlIntegrationTestCase extends TestCase
{
    private const CATEGORY_TABLE = 'maa_category_categories';
    private const CONTENT_TABLE = 'maa_category_category_contents';
    private const IMAGE_ASSIGNMENT_TABLE = 'maa_category_category_image_assignments';
    private const IMAGE_ROLE_TABLE = 'maa_category_category_image_roles';
    private const CONTENT_FIELD_TABLE = 'maa_category_category_content_fields';
    private const INSERT_TRIGGER = 'trg_maa_category_categories_parent_not_self_ai';
    private const UPDATE_TRIGGER = 'trg_maa_category_categories_parent_not_self_bu';

    private static ?PDO $connection = null;

    public static function setUpBeforeClass(): void
    {
        self::$connection = self::createConnection();
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection = null;
    }

    protected function setUp(): void
    {
        $this->dropSchema();
        $this->installSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
    }

    protected function connection(): PDO
    {
        if (self::$connection === null) {
            throw new RuntimeException('Category MySQL integration connection is not initialized.');
        }

        return self::$connection;
    }

    protected function newConnection(): PDO
    {
        return self::createConnection();
    }

    private static function createConnection(): PDO
    {
        $dsn = self::requiredEnvironmentVariable('CATEGORY_TEST_DSN');
        $username = self::requiredEnvironmentVariable('CATEGORY_TEST_DB_USER');
        $password = self::requiredEnvironmentVariable('CATEGORY_TEST_DB_PASSWORD');

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Category MySQL integration connection failed.', 0, $exception);
        }
    }

    private static function requiredEnvironmentVariable(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf(
                '%s must be configured for Category integration tests; copy env.testing.example to env.testing or provide it through the environment.',
                $name,
            ));
        }

        return $value;
    }

    private function installSchema(): void
    {
        $schemaPath = dirname(__DIR__, 3) . '/schema/category.sql';
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Unable to read the canonical Category schema.');
        }

        $schema = preg_replace(
            [
                '/^[ \t]*--[^\r\n]*(?:\r\n|\n|$)/m',
                '/^[ \t]*DELIMITER[ \t]+\S+[ \t]*$/mi',
            ],
            '',
            $schema,
        );
        if ($schema === null) {
            throw new RuntimeException('Unable to normalize the canonical Category schema.');
        }

        $statements = preg_split(
            '/;\s*(?=CREATE\s+(?:TABLE|TRIGGER)\b)/i',
            str_replace('$$', ';', trim($schema)),
            -1,
            PREG_SPLIT_NO_EMPTY,
        );
        if (!is_array($statements) || count($statements) !== 7) {
            throw new RuntimeException('The canonical Category schema must contain five tables and two triggers.');
        }

        foreach ($statements as $statement) {
            $this->connection()->exec($statement);
        }
    }

    private function dropSchema(): void
    {
        $connection = $this->connection();
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        $connection->exec('DROP TRIGGER IF EXISTS `' . self::INSERT_TRIGGER . '`');
        $connection->exec('DROP TRIGGER IF EXISTS `' . self::UPDATE_TRIGGER . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::CONTENT_FIELD_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::IMAGE_ASSIGNMENT_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::IMAGE_ROLE_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::CONTENT_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::CATEGORY_TABLE . '`');
    }
}
