<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration\Schema;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CategorySchemaIntegrationTest extends TestCase
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
        $dsn = self::requiredEnvironmentVariable('CATEGORY_TEST_DSN');
        $username = self::requiredEnvironmentVariable('CATEGORY_TEST_DB_USER');
        $password = self::requiredEnvironmentVariable('CATEGORY_TEST_DB_PASSWORD');

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Category MySQL integration connection failed.', 0, $exception);
        }
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
        self::assertSame([], $this->tableNames());
        self::assertSame([], $this->triggerNames());
    }

    public function testSchemaCanBeInstalledAndCleanedUpRepeatedly(): void
    {
        self::assertSame([
            self::CATEGORY_TABLE,
            self::CONTENT_FIELD_TABLE,
            self::CONTENT_TABLE,
            self::IMAGE_ASSIGNMENT_TABLE,
            self::IMAGE_ROLE_TABLE,
        ], $this->tableNames());
        self::assertSame([
            self::INSERT_TRIGGER,
            self::UPDATE_TRIGGER,
        ], $this->triggerNames());
        $this->assertTrigger(self::INSERT_TRIGGER, 'AFTER', 'INSERT');
        $this->assertTrigger(self::UPDATE_TRIGGER, 'BEFORE', 'UPDATE');
        $this->assertTableStorage(self::CATEGORY_TABLE);
        $this->assertTableStorage(self::CONTENT_TABLE);
        $this->assertTableStorage(self::CONTENT_FIELD_TABLE);
        $this->assertTableStorage(self::IMAGE_ASSIGNMENT_TABLE);
        $this->assertTableStorage(self::IMAGE_ROLE_TABLE);

        $this->dropSchema();
        self::assertSame([], $this->tableNames());
        self::assertSame([], $this->triggerNames());

        $this->installSchema();
        self::assertSame([
            self::CATEGORY_TABLE,
            self::CONTENT_FIELD_TABLE,
            self::CONTENT_TABLE,
            self::IMAGE_ASSIGNMENT_TABLE,
            self::IMAGE_ROLE_TABLE,
        ], $this->tableNames());
        self::assertSame([
            self::INSERT_TRIGGER,
            self::UPDATE_TRIGGER,
        ], $this->triggerNames());
        $this->assertTableStorage(self::CATEGORY_TABLE);
        $this->assertTableStorage(self::CONTENT_TABLE);
        $this->assertTableStorage(self::CONTENT_FIELD_TABLE);
        $this->assertTableStorage(self::IMAGE_ASSIGNMENT_TABLE);
        $this->assertTableStorage(self::IMAGE_ROLE_TABLE);
    }

    public function testValidCategoryHierarchyAndContentCanBeStored(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertCategory(2, 1, 'shirts', 'inactive');
        $this->insertContent(1, 2, 'en-US');
        $this->insertContent(2, 2, null);
        $this->insertImageAssignment(1, 2, 100, null, null);
        $this->insertImageAssignment(2, 2, 100, 'en-US', 'web');

        self::assertSame(2, $this->rowCount(self::CATEGORY_TABLE));
        self::assertSame(2, $this->rowCount(self::CONTENT_TABLE));
        self::assertSame(2, $this->rowCount(self::IMAGE_ASSIGNMENT_TABLE));
    }

    public function testImageRoleIdentityConstraintIsDatabaseEnforced(): void
    {
        $this->insertRole(1, 'gallery', 'active');

        $this->expectException(PDOException::class);
        $this->insertRole(2, 'gallery', 'inactive');
    }

    public function testImageRoleStatusRequiresExactLowercaseValues(): void
    {
        $collationStatement = $this->connection()->prepare(
            'SELECT COLLATION_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
        );
        $collationStatement->execute([
            'table' => self::IMAGE_ROLE_TABLE,
            'column' => 'status',
        ]);
        self::assertSame('utf8mb4_bin', $collationStatement->fetchColumn());

        /** @var list<array{string, string}> $variants */
        $variants = [
            ['uppercase-active', 'ACTIVE'],
            ['uppercase-inactive', 'INACTIVE'],
            ['trailing-space-active', 'active '],
            ['trailing-space-inactive', 'inactive '],
        ];
        foreach ($variants as [$roleKey, $status]) {
            $this->assertImageRoleInsertRejected(
                $roleKey,
                $status,
                sprintf('The database must reject non-exact Image Role status %s.', $status),
            );
        }
    }

    public function testImageAssignmentIdentityIncludesNullableRoleAndRoleScopes(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertRole(1, 'gallery', 'active');
        $this->insertImageAssignment(1, 1, 100, null, null);
        $this->insertImageAssignment(2, 1, 100, null, null, 1);

        $this->expectException(PDOException::class);
        $this->insertImageAssignment(3, 1, 100, null, null, 1);
    }

    public function testImageRoleForeignKeyIsRestrictive(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertRole(1, 'gallery', 'active');
        $this->insertImageAssignment(1, 1, 100, null, null, 1);

        try {
            $this->connection()->exec('DELETE FROM `' . self::IMAGE_ROLE_TABLE . '` WHERE `id` = 1');
            self::fail('A referenced Image Role must not be physically deleted.');
        } catch (PDOException) {
        }

        try {
            $this->connection()->exec('UPDATE `' . self::IMAGE_ROLE_TABLE . '` SET `id` = 2 WHERE `id` = 1');
            self::fail('A referenced Image Role identity must not be physically updated.');
        } catch (PDOException) {
        }
    }

    public function testCategoryCodeMustBeUnique(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');

        $this->expectException(PDOException::class);
        $this->insertCategory(2, null, 'clothing', 'active');
    }

    public function testContentIdentityMustBeUnique(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertContent(1, 1, 'en-US');

        $this->expectException(PDOException::class);
        $this->insertContent(2, 1, 'en-US');
    }

    public function testUnlocalizedContentIdentityMustBeUnique(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertContent(1, 1, null);

        $this->expectException(PDOException::class);
        $this->insertContent(2, 1, null);
    }

    public function testImageAssignmentIdentityMustBeUniqueIncludingNullScopeDimensions(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertImageAssignment(1, 1, 100, null, null);

        $this->expectException(PDOException::class);
        $this->insertImageAssignment(2, 1, 100, null, null);
    }

    public function testImageAssignmentAllowsTheSameMediaAssetInAnotherExactScope(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertImageAssignment(1, 1, 100, null, null);
        $this->insertImageAssignment(2, 1, 100, 'en-US', null);
        $this->insertImageAssignment(3, 1, 100, null, 'web');
        $this->insertImageAssignment(4, 1, 100, 'en-US', 'web');

        self::assertSame(4, $this->rowCount(self::IMAGE_ASSIGNMENT_TABLE));
    }

    public function testImageAssignmentDefaultIsUniquePerExactScopeAndUsesConditionalIdentity(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertImageAssignment(1, 1, 100, null, null, null, true);

        try {
            $this->insertImageAssignment(2, 1, 101, null, null, null, true);
            self::fail('MySQL must reject two active defaults in one exact scope.');
        } catch (PDOException) {
        }

        $this->connection()->exec(
            "UPDATE `" . self::IMAGE_ASSIGNMENT_TABLE . "` SET `deleted_at` = '2026-01-02 00:00:00' WHERE `id` = 1",
        );
        $deletedIdentityStatement = $this->connection()->query(
            'SELECT `default_scope_identity` FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` WHERE `id` = 1',
        );
        self::assertNotFalse($deletedIdentityStatement);
        $deletedIdentity = $deletedIdentityStatement->fetchColumn();
        self::assertNull($deletedIdentity);

        $this->insertImageAssignment(2, 1, 101, null, null, null, true);
        $activeIdentityStatement = $this->connection()->query(
            'SELECT `default_scope_identity` FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` WHERE `id` = 2',
        );
        self::assertNotFalse($activeIdentityStatement);
        $activeIdentity = $activeIdentityStatement->fetchColumn();
        self::assertSame('C1|N:|N:|N:', $activeIdentity);

        try {
            $this->connection()->exec(
                'UPDATE `' . self::IMAGE_ASSIGNMENT_TABLE . '` SET `is_default` = 2 WHERE `id` = 2',
            );
            self::fail('MySQL must reject values outside the is_default 0/1 domain.');
        } catch (PDOException) {
        }
    }

    public function testContentFieldFormatCheckRequiresExactLowercaseValues(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');

        $collationStatement = $this->connection()->prepare(
            'SELECT COLLATION_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
        );
        $collationStatement->execute([
            'table' => self::CONTENT_FIELD_TABLE,
            'column' => 'format',
        ]);
        self::assertSame('utf8mb4_bin', $collationStatement->fetchColumn());

        /** @var list<array{string, string, string}> $variants */
        $variants = [
            ['json-uppercase', 'JSON', '{}'],
            ['text-uppercase', 'TEXT', 'plain'],
            ['html-uppercase', 'HTML', '<p>plain</p>'],
            ['json-trailing-space', 'json ', '{}'],
            ['text-trailing-space', 'text ', 'plain'],
        ];
        foreach ($variants as [$fieldKey, $format, $value]) {
            $this->assertContentFieldInsertRejected(
                $fieldKey,
                $format,
                $value,
                sprintf('The database must reject uppercase Content Field format %s.', $format),
            );
        }
    }

    public function testContentFieldJsonCheckRejectsInvalidJson(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');

        $this->assertContentFieldInsertRejected(
            'invalid-json',
            'json',
            '{invalid',
            'The database must reject invalid JSON for the json Content Field format.',
        );
    }

    public function testEmptyImageAssignmentScopeValuesAreRejected(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');

        $this->expectException(PDOException::class);
        $this->insertImageAssignment(1, 1, 100, '', null);
    }

    public function testEmptyLanguageCodeIsNotAnAlternativeToNull(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');

        $this->expectException(PDOException::class);
        $this->insertContent(1, 1, '');
    }

    public function testStatusCheckRejectsUnknownValues(): void
    {
        $this->expectException(PDOException::class);
        $this->insertCategory(1, null, 'clothing', 'archived');
    }

    public function testSelfParentIsRejectedOnInsertWithExplicitIdentity(): void
    {
        $this->expectException(PDOException::class);
        $this->insertCategory(1, 1, 'clothing', 'active');
    }

    public function testSelfParentIsRejectedOnInsertWithGeneratedIdentity(): void
    {
        $generatedId = $this->insertGeneratedCategory(null, 'clothing', 'active');
        self::assertSame(1, $generatedId);

        $nextId = $this->nextAutoIncrementId();
        self::assertSame(2, $nextId);

        try {
            $this->insertGeneratedCategory($nextId, 'self-parent', 'active');
            self::fail('The AFTER INSERT trigger must reject a generated self-parent identity.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('Category parent_id cannot equal id', $exception->getMessage());
        }
    }

    public function testSelfParentIsRejectedOnUpdate(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');

        $this->expectException(PDOException::class);
        $this->connection()->exec(
            'UPDATE `' . self::CATEGORY_TABLE . '` SET `parent_id` = `id` WHERE `id` = 1',
        );
    }

    public function testContentForeignKeyRejectsMissingCategory(): void
    {
        $this->expectException(PDOException::class);
        $this->insertContent(1, 999, 'en-US');
    }

    public function testImageAssignmentForeignKeyRejectsMissingCategory(): void
    {
        $this->expectException(PDOException::class);
        $this->insertImageAssignment(1, 999, 100, null, null);
    }

    public function testParentAndCategoryCannotBeDeletedWhileDependentsExist(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertCategory(2, 1, 'shirts', 'active');
        $this->insertContent(1, 1, 'en-US');
        $this->insertImageAssignment(1, 1, 100, null, null);

        $this->expectException(PDOException::class);
        $this->connection()->exec('DELETE FROM `' . self::CATEGORY_TABLE . '` WHERE `id` = 1');
    }

    public function testCategoryIdCannotBeUpdatedWhileChildReferencesIt(): void
    {
        $this->insertCategory(1, null, 'clothing', 'active');
        $this->insertCategory(2, 1, 'shirts', 'active');

        $this->expectException(PDOException::class);
        $this->connection()->exec('UPDATE `' . self::CATEGORY_TABLE . '` SET `id` = 10 WHERE `id` = 1');
    }

    private static function requiredEnvironmentVariable(string $name): string
    {
        $value = getenv($name);

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf('%s must be configured for Category integration tests.', $name));
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
            throw new RuntimeException('The canonical Category schema must contain exactly five tables and two triggers.');
        }

        $tableStatements = 0;
        $triggerStatements = 0;

        foreach ($statements as $statement) {
            if (trim($statement) === '') {
                throw new RuntimeException('The canonical Category schema contains an empty statement.');
            }

            if (preg_match('/(?:^|\n)[ \t]*CREATE\s+TABLE\b/i', $statement) === 1) {
                $tableStatements++;
            } elseif (preg_match('/(?:^|\n)[ \t]*CREATE\s+TRIGGER\b/i', $statement) === 1) {
                $triggerStatements++;
            } else {
                throw new RuntimeException('The canonical Category schema contains an unexpected statement.');
            }

            $this->connection()->exec($statement);
        }

        if ($tableStatements !== 5 || $triggerStatements !== 2) {
            throw new RuntimeException('The canonical Category schema must contain exactly five tables and two triggers.');
        }
    }

    private function dropSchema(): void
    {
        $connection = $this->connection();
        $connection->exec('DROP TRIGGER IF EXISTS `' . self::INSERT_TRIGGER . '`');
        $connection->exec('DROP TRIGGER IF EXISTS `' . self::UPDATE_TRIGGER . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::CONTENT_FIELD_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::IMAGE_ASSIGNMENT_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::IMAGE_ROLE_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::CONTENT_TABLE . '`');
        $connection->exec('DROP TABLE IF EXISTS `' . self::CATEGORY_TABLE . '`');
    }

    private function insertCategory(int $id, ?int $parentId, string $code, string $status): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO `' . self::CATEGORY_TABLE . '` '
            . '(`id`, `parent_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:id, :parent_id, :code, :status, :display_order, :created_at, :updated_at, :deleted_at)',
        );
        $statement->execute([
            'id' => $id,
            'parent_id' => $parentId,
            'code' => $code,
            'status' => $status,
            'display_order' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
        ]);
    }

    private function insertGeneratedCategory(?int $parentId, string $code, string $status): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO `' . self::CATEGORY_TABLE . '` '
            . '(`parent_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:parent_id, :code, :status, :display_order, :created_at, :updated_at, :deleted_at)',
        );
        $statement->execute([
            'parent_id' => $parentId,
            'code' => $code,
            'status' => $status,
            'display_order' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
        ]);

        $generatedId = $this->connection()->lastInsertId();
        if (!is_string($generatedId) || $generatedId === '' || !ctype_digit($generatedId)) {
            throw new RuntimeException('Category AUTO_INCREMENT did not return a numeric generated identity.');
        }

        $id = (int) $generatedId;
        if ($id < 1) {
            throw new RuntimeException('Category AUTO_INCREMENT returned an invalid generated identity.');
        }

        return $id;
    }

    private function nextAutoIncrementId(): int
    {
        $statement = $this->connection()->prepare(
            'SELECT AUTO_INCREMENT FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        );
        $statement->execute(['table' => self::CATEGORY_TABLE]);
        $value = $statement->fetchColumn();

        if (!is_int($value) && !is_string($value)) {
            throw new RuntimeException('Category AUTO_INCREMENT metadata is unavailable.');
        }

        $nextId = (int) $value;
        if ($nextId < 1) {
            throw new RuntimeException('Category AUTO_INCREMENT metadata is invalid.');
        }

        return $nextId;
    }

    private function insertContent(int $id, int $categoryId, ?string $languageCode): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO `' . self::CONTENT_TABLE . '` '
            . '(`id`, `category_id`, `language_code`, `name`, `description`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:id, :category_id, :language_code, :name, :description, :created_at, :updated_at, :deleted_at)',
        );
        $statement->execute([
            'id' => $id,
            'category_id' => $categoryId,
            'language_code' => $languageCode,
            'name' => 'Clothing',
            'description' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
        ]);
    }

    private function insertRole(int $id, string $roleKey, string $status): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO `' . self::IMAGE_ROLE_TABLE . '` '
            . '(`id`, `role_key`, `status`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:id, :role_key, :status, :created_at, :updated_at, :deleted_at)',
        );
        $statement->execute([
            'id' => $id,
            'role_key' => $roleKey,
            'status' => $status,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
        ]);
    }

    private function insertImageAssignment(
        int $id,
        int $categoryId,
        int $mediaAssetId,
        ?string $languageCode,
        ?string $platform,
        ?int $roleId = null,
        bool $isDefault = false,
    ): void {
        $statement = $this->connection()->prepare(
            'INSERT INTO `' . self::IMAGE_ASSIGNMENT_TABLE . '` '
            . '(`id`, `category_id`, `media_asset_id`, `role_id`, `language_code`, `platform`, '
            . '`is_default`, `display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:id, :category_id, :media_asset_id, :role_id, :language_code, :platform, '
            . ':is_default, :display_order, :created_at, :updated_at, :deleted_at)',
        );
        $statement->execute([
            'id' => $id,
            'category_id' => $categoryId,
            'media_asset_id' => $mediaAssetId,
            'role_id' => $roleId,
            'language_code' => $languageCode,
            'platform' => $platform,
            'is_default' => $isDefault ? 1 : 0,
            'display_order' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
        ]);
    }

    private function assertContentFieldInsertRejected(
        string $fieldKey,
        string $format,
        string $value,
        string $message,
    ): void {
        try {
            $this->insertContentField($fieldKey, $format, $value);
        } catch (PDOException) {
            return;
        }

        self::fail($message);
    }

    private function assertImageRoleInsertRejected(string $roleKey, string $status, string $message): void
    {
        try {
            $this->insertRole(1, $roleKey, $status);
        } catch (PDOException) {
            return;
        }

        self::fail($message);
    }

    private function insertContentField(string $fieldKey, string $format, string $value): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO `' . self::CONTENT_FIELD_TABLE . '` '
            . '(`category_id`, `field_key`, `language_code`, `platform`, `format`, `value`, '
            . '`display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:category_id, :field_key, :language_code, :platform, :format, :value, '
            . ':display_order, :created_at, :updated_at, :deleted_at)',
        );
        $statement->execute([
            'category_id' => 1,
            'field_key' => $fieldKey,
            'language_code' => null,
            'platform' => null,
            'format' => $format,
            'value' => $value,
            'display_order' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
        ]);
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        $statement = $this->connection()->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME IN ('
            . "'" . self::CATEGORY_TABLE . "', '" . self::CONTENT_TABLE . "', '" . self::CONTENT_FIELD_TABLE . "', '" . self::IMAGE_ROLE_TABLE . "', '" . self::IMAGE_ASSIGNMENT_TABLE . "')"
            . ' ORDER BY BINARY TABLE_NAME',
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to inspect the Category schema tables.');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $names = [];

        foreach ($rows as $row) {
            if (!isset($row['TABLE_NAME']) || !is_string($row['TABLE_NAME'])) {
                throw new RuntimeException('Category table metadata is incomplete.');
            }

            $names[] = $row['TABLE_NAME'];
        }

        return $names;
    }

    /** @return list<string> */
    private function triggerNames(): array
    {
        $statement = $this->connection()->query(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS '
            . 'WHERE TRIGGER_SCHEMA = DATABASE() '
            . 'AND TRIGGER_NAME IN ('
            . "'" . self::INSERT_TRIGGER . "', '" . self::UPDATE_TRIGGER . "')"
            . ' ORDER BY BINARY TRIGGER_NAME',
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to inspect the Category schema triggers.');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $names = [];

        foreach ($rows as $row) {
            if (!isset($row['TRIGGER_NAME']) || !is_string($row['TRIGGER_NAME'])) {
                throw new RuntimeException('Category trigger metadata is incomplete.');
            }

            $names[] = $row['TRIGGER_NAME'];
        }

        return $names;
    }

    private function rowCount(string $table): int
    {
        $statement = $this->connection()->query('SELECT COUNT(*) FROM `' . $table . '`');

        if ($statement === false) {
            throw new RuntimeException(sprintf('Unable to count rows in %s.', $table));
        }

        return (int) $statement->fetchColumn();
    }

    private function assertTableStorage(string $table): void
    {
        $statement = $this->connection()->prepare(
            'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        );
        $statement->execute(['table' => $table]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Table %s was not created.', $table));
        }

        if (!isset($row['ENGINE'], $row['TABLE_COLLATION'])
            || !is_string($row['ENGINE'])
            || !is_string($row['TABLE_COLLATION'])) {
            throw new RuntimeException(sprintf('Storage metadata for %s is incomplete.', $table));
        }

        self::assertSame('InnoDB', $row['ENGINE']);
        self::assertSame('utf8mb4_unicode_ci', $row['TABLE_COLLATION']);
    }

    private function assertTrigger(string $name, string $timing, string $event): void
    {
        $statement = $this->connection()->prepare(
            'SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE '
            . 'FROM information_schema.TRIGGERS '
            . 'WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :trigger',
        );
        $statement->execute(['trigger' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Trigger %s was not created.', $name));
        }

        foreach (['TRIGGER_NAME', 'ACTION_TIMING', 'EVENT_MANIPULATION', 'EVENT_OBJECT_TABLE'] as $column) {
            if (!isset($row[$column]) || !is_string($row[$column])) {
                throw new RuntimeException(sprintf('Trigger metadata for %s is incomplete.', $name));
            }
        }

        self::assertSame($name, $row['TRIGGER_NAME']);
        self::assertSame($timing, $row['ACTION_TIMING']);
        self::assertSame($event, $row['EVENT_MANIPULATION']);
        self::assertSame(self::CATEGORY_TABLE, $row['EVENT_OBJECT_TABLE']);
    }

    private function connection(): PDO
    {
        if (self::$connection === null) {
            throw new RuntimeException('Category MySQL integration connection is not initialized.');
        }

        return self::$connection;
    }
}
