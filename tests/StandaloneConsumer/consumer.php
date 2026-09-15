<?php

declare(strict_types=1);

use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\Factory\CategoryFactory;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\SharedCommon\Infrastructure\SystemClock;

/** @return never */
function standalone_consumer_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function standalone_consumer_require(bool $condition, string $message): void
{
    if (!$condition) {
        standalone_consumer_fail($message);
    }
}

/**
 * Build the public type inventory from the installed package source itself.
 *
 * This intentionally does not maintain a second, hand-curated list in the
 * consumer: every PHP source file must expose a discoverable package type,
 * and every discovered class, interface, or enum must then autoload from the
 * installed package.
 *
 * @return list<array{kind: 'class'|'interface'|'enum', name: string}>
 */
function standalone_consumer_public_types(string $packageSourceRoot): array
{
    $types = [];
    $seen = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($packageSourceRoot, \FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($fileInfo->getPathname());
        if (!is_string($source)) {
            standalone_consumer_fail('Unable to read installed package source: ' . $fileInfo->getPathname());
        }

        $namespaceMatches = [];
        if (preg_match(
            '/^\s*namespace\s+([A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)\s*;/m',
            $source,
            $namespaceMatches,
        ) !== 1) {
            standalone_consumer_fail('Package source file has no discoverable namespace: ' . $fileInfo->getPathname());
        }
        $namespace = $namespaceMatches[1];

        $fileTypeCount = 0;
        $tokens = token_get_all($source);
        $previousMeaningfulToken = null;
        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                if (trim($token) !== '') {
                    $previousMeaningfulToken = $token;
                }
                continue;
            }

            $tokenId = $token[0];
            if (in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (in_array($tokenId, [T_CLASS, T_INTERFACE, T_ENUM], true)) {
                $nextIndex = $index + 1;
                while ($nextIndex < $tokenCount) {
                    $nextToken = $tokens[$nextIndex];
                    if (is_array($nextToken) && in_array($nextToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $nextIndex++;
                        continue;
                    }
                    if (is_string($nextToken) && trim($nextToken) === '') {
                        $nextIndex++;
                        continue;
                    }
                    break;
                }

                $nextToken = $tokens[$nextIndex] ?? null;
                if (
                    is_array($nextToken)
                    && $nextToken[0] === T_STRING
                    && !($tokenId === T_CLASS && $previousMeaningfulToken === T_NEW)
                ) {
                    $name = $nextToken[1];

                    $type = $namespace . '\\' . $name;
                    if (isset($seen[$type])) {
                        standalone_consumer_fail('Package source declares a duplicate public type: ' . $type);
                    }
                    $seen[$type] = true;
                    $types[] = [
                        'kind' => match ($tokenId) {
                            T_CLASS => 'class',
                            T_INTERFACE => 'interface',
                            T_ENUM => 'enum',
                        },
                        'name' => $type,
                    ];
                    $fileTypeCount++;
                }
            }

            $previousMeaningfulToken = $tokenId;
        }

        standalone_consumer_require(
            $fileTypeCount === 1,
            'Package source file must declare exactly one public class, interface, or enum: ' . $fileInfo->getPathname(),
        );
    }

    standalone_consumer_require($types !== [], 'Installed package source contains no public types.');
    usort($types, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

    return $types;
}

/**
 * @phpstan-assert class-string $type
 */
function standalone_consumer_assert_type_loaded(string $type, string $kind): void
{
    $loaded = match ($kind) {
        'class' => class_exists($type),
        'interface' => interface_exists($type),
        'enum' => enum_exists($type),
        default => false,
    };

    standalone_consumer_require($loaded, sprintf('Public %s did not autoload: %s', $kind, $type));
}

/**
 * @param class-string $type
 */
function standalone_consumer_assert_installed_source(string $type, string $packageSourceRoot): void
{
    if (enum_exists($type)) {
        $reflection = new \ReflectionEnum($type);
    } else {
        $reflection = new \ReflectionClass($type);
    }

    $file = $reflection->getFileName();
    standalone_consumer_require(
        is_string($file) && str_starts_with($file, $packageSourceRoot . DIRECTORY_SEPARATOR),
        sprintf('Public type was not loaded from the installed package source: %s', $type),
    );
}

function standalone_consumer_install_schema(\PDO $pdo, string $schemaPath): void
{
    $schema = file_get_contents($schemaPath);
    if (!is_string($schema)) {
        standalone_consumer_fail('Unable to read the installed Category schema.');
    }

    $schema = preg_replace(
        [
            '/^[ \t]*--[^\r\n]*(?:\r\n|\n|$)/m',
            '/^[ \t]*DELIMITER[ \t]+\S+[ \t]*$/mi',
        ],
        '',
        $schema,
    );
    if (!is_string($schema)) {
        standalone_consumer_fail('Unable to normalize the installed Category schema.');
    }

    $statements = preg_split(
        '/;\s*(?=CREATE\s+(?:TABLE|TRIGGER)\b)/i',
        str_replace('$$', ';', trim($schema)),
        -1,
        PREG_SPLIT_NO_EMPTY,
    );
    if (!is_array($statements) || count($statements) !== 7) {
        standalone_consumer_fail('The installed Category schema must contain five tables and two triggers.');
    }

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function standalone_consumer_drop_schema(\PDO $pdo): void
{
    foreach ([
        'DROP TRIGGER IF EXISTS `trg_maa_category_categories_parent_not_self_ai`',
        'DROP TRIGGER IF EXISTS `trg_maa_category_categories_parent_not_self_bu`',
        'DROP TABLE IF EXISTS `maa_category_category_content_fields`',
        'DROP TABLE IF EXISTS `maa_category_category_image_assignments`',
        'DROP TABLE IF EXISTS `maa_category_category_image_roles`',
        'DROP TABLE IF EXISTS `maa_category_category_contents`',
        'DROP TABLE IF EXISTS `maa_category_categories`',
    ] as $statement) {
        $pdo->exec($statement);
    }
}

/** @return list<string> */
function standalone_consumer_database_objects(\PDO $pdo, string $objectType): array
{
    $statement = $pdo->query(
        'SELECT ' . ($objectType === 'tables' ? 'TABLE_NAME' : 'TRIGGER_NAME') . ' '
        . 'FROM information_schema.' . ($objectType === 'tables' ? 'TABLES' : 'TRIGGERS') . ' '
        . 'WHERE ' . ($objectType === 'tables' ? 'TABLE_SCHEMA' : 'TRIGGER_SCHEMA') . ' = DATABASE() '
        . 'ORDER BY BINARY ' . ($objectType === 'tables' ? 'TABLE_NAME' : 'TRIGGER_NAME'),
    );
    if ($statement === false) {
        standalone_consumer_fail('Unable to inspect standalone database objects.');
    }

    $values = $statement->fetchAll(\PDO::FETCH_COLUMN);
    $objects = [];
    foreach ($values as $value) {
        if (!is_string($value)) {
            standalone_consumer_fail('Database metadata contains an invalid object name.');
        }
        $objects[] = $value;
    }

    return $objects;
}

$packageSourceRoot = realpath(__DIR__ . '/vendor/maatify/php-category/src');
if (!is_string($packageSourceRoot)) {
    standalone_consumer_fail('The installed Category source directory is missing from the clean consumer.');
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    standalone_consumer_fail('The clean consumer Composer autoload file is missing.');
}
require $autoloadPath;

$publicTypes = standalone_consumer_public_types($packageSourceRoot);
$publicTypeNames = [];
foreach ($publicTypes as $definition) {
    standalone_consumer_assert_type_loaded($definition['name'], $definition['kind']);
    standalone_consumer_assert_installed_source($definition['name'], $packageSourceRoot);
    $publicTypeNames[] = $definition['name'];
}
fwrite(STDOUT, sprintf("Installed package public inventory (%d): %s\n", count($publicTypes), implode(', ', $publicTypeNames)));

$dsn = getenv('CATEGORY_STANDALONE_DSN');
$username = getenv('CATEGORY_STANDALONE_DB_USER');
$password = getenv('CATEGORY_STANDALONE_DB_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($username) || !is_string($password)) {
    standalone_consumer_fail('Standalone consumer database environment is incomplete.');
}

try {
    $pdo = new \PDO($dsn, $username, $password, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (\PDOException $exception) {
    standalone_consumer_fail('Standalone consumer could not connect to Real MySQL: ' . $exception->getMessage());
}

$schemaPath = __DIR__ . '/vendor/maatify/php-category/schema/category.sql';
standalone_consumer_require(is_file($schemaPath), 'The installed Category schema file is missing.');

try {
    standalone_consumer_drop_schema($pdo);
    standalone_consumer_install_schema($pdo, $schemaPath);
    standalone_consumer_require(
        standalone_consumer_database_objects($pdo, 'tables') === [
            'maa_category_categories',
            'maa_category_category_content_fields',
            'maa_category_category_contents',
            'maa_category_category_image_assignments',
            'maa_category_category_image_roles',
        ],
        'The standalone schema did not create exactly its five package-owned tables.',
    );
    standalone_consumer_require(
        standalone_consumer_database_objects($pdo, 'triggers') === [
            'trg_maa_category_categories_parent_not_self_ai',
            'trg_maa_category_categories_parent_not_self_bu',
        ],
        'The standalone schema did not create exactly its two package-owned triggers.',
    );

    $clock = new SystemClock(new \DateTimeZone('Africa/Cairo'));
    $category = CategoryFactory::create($pdo, $clock);

    $categoryId = $category->categories()->create(new CreateCategoryCommand('standalone-consumer-category'));
    $contentId = $category->contents()->create(
        new CreateCategoryContentCommand($categoryId, null, 'Standalone Category', null),
    );
    $localizedContentId = $category->contents()->create(
        new CreateCategoryContentCommand($categoryId, 'en-US', 'Standalone Category English', null),
    );
    $genericScope = new CategoryImageAssignmentScopeDTO();
    $localizedScope = new CategoryImageAssignmentScopeDTO('en-US', 'web');
    $imageAssignmentId = $category->images()->assign(
        new CreateCategoryImageAssignmentCommand($categoryId, 700, $genericScope),
    );
    $secondImageAssignmentId = $category->images()->assign(
        new CreateCategoryImageAssignmentCommand($categoryId, 702, $genericScope),
    );
    $localizedImageAssignmentId = $category->images()->assign(
        new CreateCategoryImageAssignmentCommand($categoryId, 700, $localizedScope),
    );
    $imageRoleId = $category->imageRoles()->create(
        new CreateCategoryImageRoleCommand('gallery'),
    );
    $roleScope = new CategoryImageAssignmentScopeDTO('en-US', 'web', $imageRoleId);
    $roleImageAssignmentId = $category->images()->assign(
        new CreateCategoryImageAssignmentCommand($categoryId, 701, $roleScope),
    );
    $contentFieldId = $category->contentFields()->create(
        new CreateCategoryContentFieldCommand(
            $categoryId,
            'badge_config',
            'en-US',
            'web',
            CategoryContentFieldFormatEnum::JSON,
            '{"enabled":true}',
        ),
    );
    standalone_consumer_require(
        $categoryId > 0
        && $contentId > 0
        && $localizedContentId > 0
        && $imageAssignmentId > 0
        && $secondImageAssignmentId > 0
        && $localizedImageAssignmentId > 0
        && $imageRoleId > 0
        && $roleImageAssignmentId > 0
        && $contentFieldId > 0,
        'Standalone mutation returned invalid IDs.',
    );

    $temporaryCategoryId = $category->categories()->create(
        new CreateCategoryCommand('standalone-consumer-temporary'),
    );
    $category->categories()->move(new MoveCategoryCommand($temporaryCategoryId, $categoryId));
    $category->categories()->updateDisplayOrder(
        new UpdateCategoryDisplayOrderCommand($temporaryCategoryId, 2),
    );
    $category->categories()->softDelete(new SoftDeleteCategoryCommand($temporaryCategoryId));
    $category->categories()->restore(new RestoreCategoryCommand($temporaryCategoryId));
    $category->categories()->softDelete(new SoftDeleteCategoryCommand($temporaryCategoryId));

    $category->categories()->updateStatus(
        new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::INACTIVE),
    );
    $category->categories()->updateStatus(
        new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::ACTIVE),
    );
    $category->contents()->update(
        new UpdateCategoryContentCommand($localizedContentId, 'Standalone Category English Updated', null),
    );
    $category->contents()->updateName(
        new UpdateCategoryContentNameCommand($localizedContentId, 'Standalone Category English Inline'),
    );
    $category->contents()->updateDescription(
        new UpdateCategoryContentDescriptionCommand($localizedContentId, 'Standalone inline description'),
    );
    $category->contents()->softDelete(new SoftDeleteCategoryContentCommand($localizedContentId));
    $category->contents()->restore(new RestoreCategoryContentCommand($localizedContentId));
    $category->images()->reorder(
        new UpdateCategoryImageAssignmentDisplayOrderCommand($secondImageAssignmentId, 1),
    );
    $category->images()->setDefault(
        new SetCategoryImageAssignmentDefaultCommand($imageAssignmentId),
    );
    $category->images()->remove(new SoftDeleteCategoryImageAssignmentCommand($imageAssignmentId));
    standalone_consumer_require(
        $category->images()->listVisibleForCategory($categoryId, $genericScope)->count() === 1,
        'Standalone remove did not hide the removed Image Assignment from exact consumer reads.',
    );
    $removedImageAssignment = $category->images()->getByIdForManagement(
        $imageAssignmentId,
        CategoryDeletedStateEnum::DELETED_ONLY,
    );
    standalone_consumer_require(
        $removedImageAssignment->deletedAt !== null && !$removedImageAssignment->isDefault,
        'Standalone remove did not clear the default or preserve deleted lifecycle state.',
    );
    $removedStateStatement = $pdo->prepare(
        'SELECT media_asset_id, is_default, deleted_at '
        . 'FROM maa_category_category_image_assignments WHERE id = :image_assignment_id',
    );
    $removedStateStatement->execute(['image_assignment_id' => $imageAssignmentId]);
    /** @var array<string, mixed>|false $removedState */
    $removedState = $removedStateStatement->fetch(\PDO::FETCH_ASSOC);
    standalone_consumer_require(
        is_array($removedState)
        && in_array($removedState['media_asset_id'] ?? null, [700, '700'], true)
        && in_array($removedState['is_default'] ?? null, [0, '0'], true)
        && is_string($removedState['deleted_at'] ?? null),
        'Standalone PDO observation did not match the removed Image Assignment state.',
    );
    $category->images()->restore(new RestoreCategoryImageAssignmentCommand($imageAssignmentId));
    standalone_consumer_require(
        $category->images()->listVisibleForCategory($categoryId, $genericScope)->count() === 2
        && !$category->images()->getByIdForManagement($imageAssignmentId)->isDefault,
        'Standalone restore did not expose the assignment as non-default.',
    );
    $category->imageRoles()->updateStatus(
        new UpdateCategoryImageRoleStatusCommand($imageRoleId, CategoryImageRoleStatusEnum::INACTIVE),
    );
    $category->imageRoles()->updateStatus(
        new UpdateCategoryImageRoleStatusCommand($imageRoleId, CategoryImageRoleStatusEnum::ACTIVE),
    );
    $category->imageRoles()->softDelete(new SoftDeleteCategoryImageRoleCommand($imageRoleId));
    $category->imageRoles()->restore(new RestoreCategoryImageRoleCommand($imageRoleId));
    $category->contentFields()->update(
        new UpdateCategoryContentFieldCommand(
            $contentFieldId,
            CategoryContentFieldFormatEnum::JSON,
            '{"enabled":false}',
        ),
    );
    $category->contentFields()->updateValue(
        new UpdateCategoryContentFieldValueCommand($contentFieldId, '{"enabled":true}'),
    );
    $category->contentFields()->updateDisplayOrder(
        new UpdateCategoryContentFieldDisplayOrderCommand($contentFieldId, 2),
    );
    $category->contentFields()->softDelete(new SoftDeleteCategoryContentFieldCommand($contentFieldId));
    $category->contentFields()->restore(new RestoreCategoryContentFieldCommand($contentFieldId));

    $category->images()->setDefault(
        new SetCategoryImageAssignmentDefaultCommand($imageAssignmentId),
    );
    $category->images()->setDefault(
        new SetCategoryImageAssignmentDefaultCommand($secondImageAssignmentId),
    );
    standalone_consumer_require(
        !$category->images()->getByIdForManagement($imageAssignmentId)->isDefault
        && $category->images()->getByIdForManagement($secondImageAssignmentId)->isDefault,
        'Standalone default assignment switch did not clear the previous default.',
    );
    $category->images()->clearDefault(
        new ClearCategoryImageAssignmentDefaultCommand($secondImageAssignmentId),
    );
    standalone_consumer_require(
        !$category->images()->getByIdForManagement($secondImageAssignmentId)->isDefault,
        'Standalone default clear did not remove the explicit default.',
    );
    $category->images()->setDefault(
        new SetCategoryImageAssignmentDefaultCommand($imageAssignmentId),
    );

    $categoryDto = $category->categories()->getById($categoryId);
    standalone_consumer_require($categoryDto->id === $categoryId, 'Standalone query returned the wrong Category.');
    standalone_consumer_require($categoryDto->code === 'standalone-consumer-category', 'Standalone Category code mismatch.');
    standalone_consumer_require(
        $categoryDto->createdAt->getTimezone()->getName() === 'Africa/Cairo',
        'Standalone hydration did not use the Host Clock timezone.',
    );
    $createdAtStatement = $pdo->prepare(
        'SELECT `created_at` FROM `maa_category_categories` WHERE `id` = :id',
    );
    $createdAtStatement->execute(['id' => $categoryId]);
    $storedCreatedAt = $createdAtStatement->fetchColumn();
    standalone_consumer_require(
        is_string($storedCreatedAt)
        && $storedCreatedAt === $categoryDto->createdAt->format('Y-m-d H:i:s'),
        'Standalone persistence did not preserve the Host timestamp value.',
    );
    standalone_consumer_require(
        $category->categories()->listRootCategories(new CategoryVisibleListCriteriaDTO(maxResults: 10))->count() === 1,
        'Standalone visible root query did not return the stored Category.',
    );
    standalone_consumer_require(
        $category->categories()->listChildren($categoryId, new CategoryVisibleListCriteriaDTO(maxResults: 10))->count() === 0,
        'Standalone visible child query returned an unexpected Category.',
    );
    standalone_consumer_require(
        $category->contents()->listVisibleForCategory($categoryId, new CategoryVisibleListCriteriaDTO(maxResults: 10))->count() === 2,
        'Standalone query did not return both unlocalized and localized Content.',
    );
    standalone_consumer_require(
        $category->images()->listVisibleForCategory($categoryId, $genericScope)->count() === 2,
        'Standalone exact unlocalized Image Assignment query returned the wrong rows.',
    );
    standalone_consumer_require(
        $category->images()->listVisibleForCategory(
            $categoryId,
            $localizedScope,
        )->count() === 1,
        'Standalone exact localized/platform Image Assignment query returned the wrong rows.',
    );
    standalone_consumer_require(
        $category->images()->listVisibleForCategory(
            $categoryId,
            new CategoryImageAssignmentScopeDTO('en-US'),
        )->isEmpty(),
        'Standalone exact scope read must not fall back across a missing platform.',
    );
    standalone_consumer_require(
        $category->images()->listVisibleForCategory(
            $categoryId,
            $roleScope,
        )->count() === 1,
        'Standalone exact Role-scoped Image Assignment query returned the wrong rows.',
    );
    $visibleContentFields = $category->contentFields()->listVisibleForCategory(
        $categoryId,
        new CategoryContentFieldScopeDTO('en-US', 'web'),
        new CategoryVisibleListCriteriaDTO(maxResults: 10),
    );
    $visibleContentFieldId = null;
    foreach ($visibleContentFields as $visibleContentField) {
        $visibleContentFieldId = $visibleContentField->id;
    }
    standalone_consumer_require(
        $visibleContentFields->count() === 1 && $visibleContentFieldId === $contentFieldId,
        'Standalone exact Content Field consumer query returned the wrong rows.',
    );

    $managementCategory = $category->categories()->getByIdForManagement(
        $categoryId,
        CategoryDeletedStateEnum::NON_DELETED,
    );
    standalone_consumer_require(
        $managementCategory->id === $categoryId,
        'Standalone management read service returned the wrong Category.',
    );
    standalone_consumer_require(
        $category->categories()->getByCode('standalone-consumer-category')->id === $categoryId,
        'Standalone management code lookup returned the wrong Category.',
    );
    $managementCategoryPage = $category->categories()->paginateForManagement(
        new CategoryListCriteriaDTO(
            deletedState: CategoryDeletedStateEnum::NON_DELETED,
            search: 'standalone-consumer-category',
        ),
        new PageRequest(page: 1, perPage: 1, sortBy: 'code', sortDirection: 'ASC'),
    );
    standalone_consumer_require(
        $managementCategoryPage->total === 1
        && $managementCategoryPage->filtered === 1
        && count($managementCategoryPage->data) === 1
        && $managementCategoryPage->data[0]->id === $categoryId,
        'Standalone management Category pagination/search returned the wrong result.',
    );
    $managementCategories = $category->categories()->listForManagement(
        new CategoryListCriteriaDTO(
            status: CategoryStatusEnum::ACTIVE,
            deletedState: CategoryDeletedStateEnum::NON_DELETED,
            maxResults: 10,
        ),
    );
    standalone_consumer_require(
        $managementCategories->count() === 1,
        'Standalone management Category list did not return the stored Category.',
    );
    standalone_consumer_require(
        $category->categories()->listRootCategoriesForManagement(new CategoryListCriteriaDTO(maxResults: 10))->count() === 1,
        'Standalone management root list did not return the stored Category.',
    );
    standalone_consumer_require(
        $category->categories()->listChildrenForManagement($categoryId, new CategoryListCriteriaDTO(maxResults: 10))->count() === 0,
        'Standalone management child list returned an unexpected Category.',
    );
    $managementRootPage = $category->categories()->paginateRootCategoriesForManagement(
        new CategoryListCriteriaDTO(
            status: CategoryStatusEnum::ACTIVE,
            deletedState: CategoryDeletedStateEnum::NON_DELETED,
        ),
        new PageRequest(page: 1, perPage: 1),
    );
    standalone_consumer_require(
        $managementRootPage->total === 1
        && $managementRootPage->filtered === 1
        && count($managementRootPage->data) === 1
        && $managementRootPage->data[0]->id === $categoryId,
        'Standalone management root pagination returned the wrong result.',
    );
    $managementDeletedChildPage = $category->categories()->paginateChildrenForManagement(
        $categoryId,
        new CategoryListCriteriaDTO(deletedState: CategoryDeletedStateEnum::DELETED_ONLY),
        new PageRequest(page: 1, perPage: 1),
    );
    standalone_consumer_require(
        $managementDeletedChildPage->total === 1
        && $managementDeletedChildPage->filtered === 1
        && count($managementDeletedChildPage->data) === 1
        && $managementDeletedChildPage->data[0]->id === $temporaryCategoryId,
        'Standalone management child pagination returned the wrong result.',
    );
    $managementContent = $category->contents()->getByIdForManagement(
        $contentId,
        CategoryDeletedStateEnum::NON_DELETED,
    );
    standalone_consumer_require(
        $managementContent->id === $contentId,
        'Standalone management read service returned the wrong Content.',
    );
    standalone_consumer_require(
        $managementContent->languageCode === null,
        'Standalone management read did not preserve the unlocalized NULL language identity.',
    );
    standalone_consumer_require(
        $category->contents()->listForManagement(
            new CategoryContentListCriteriaDTO(
                categoryId: $categoryId,
                deletedState: CategoryDeletedStateEnum::NON_DELETED,
                maxResults: 10,
            ),
        )->count() === 2,
        'Standalone management Content list did not return both Content records.',
    );
    $managementContentPage = $category->contents()->paginateForManagement(
        new CategoryContentListCriteriaDTO(categoryId: $categoryId),
        new PageRequest(page: 1, perPage: 1, sortBy: 'language_code', sortDirection: 'ASC'),
    );
    standalone_consumer_require(
        $managementContentPage->total === 2
        && $managementContentPage->filtered === 2
        && count($managementContentPage->data) === 1
        && $managementContentPage->data[0]->id === $contentId,
        'Standalone management Content pagination returned the wrong result.',
    );
    standalone_consumer_require(
        $category->images()->getByIdForManagement($imageAssignmentId)->id === $imageAssignmentId,
        'Standalone management read service returned the wrong Image Assignment.',
    );
    standalone_consumer_require(
        $category->images()->getByIdForManagement($imageAssignmentId)->isDefault
        && !$category->images()->getByIdForManagement($secondImageAssignmentId)->isDefault
        && !$category->images()->getByIdForManagement($localizedImageAssignmentId)->isDefault
        && !$category->images()->getByIdForManagement($roleImageAssignmentId)->isDefault,
        'Standalone management hydration returned the wrong Image Assignment default state.',
    );
    standalone_consumer_require(
        $category->images()->listForManagement(
            new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
        )->count() === 4,
        'Standalone management Image Assignment list did not return all scopes.',
    );
    $managementImagePage = $category->images()->paginateForManagement(
        new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
        new PageRequest(page: 1, perPage: 4),
    );
    standalone_consumer_require(
        $managementImagePage->total === 4
        && $managementImagePage->filtered === 4
        && count($managementImagePage->data) === 4
        && $managementImagePage->data[0]->id === $localizedImageAssignmentId
        && $managementImagePage->data[1]->id === $roleImageAssignmentId
        && $managementImagePage->data[2]->id === $secondImageAssignmentId
        && $managementImagePage->data[3]->id === $imageAssignmentId,
        'Standalone management Image Assignment pagination returned the wrong result.',
    );
    $managementImageCategoryPage = $category->images()->paginateForManagement(
        new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
        new PageRequest(page: 1, perPage: 4, sortBy: 'category_id'),
    );
    standalone_consumer_require(
        $managementImageCategoryPage->sortBy === 'category_id'
        && $managementImageCategoryPage->total === 4
        && $managementImageCategoryPage->filtered === 4
        && count($managementImageCategoryPage->data) === 4
        && $managementImageCategoryPage->data[0]->id === $imageAssignmentId
        && $managementImageCategoryPage->data[1]->id === $secondImageAssignmentId
        && $managementImageCategoryPage->data[2]->id === $localizedImageAssignmentId
        && $managementImageCategoryPage->data[3]->id === $roleImageAssignmentId,
        'Standalone category_id Image Assignment pagination returned the wrong result.',
    );
    standalone_consumer_require(
        $category->imageRoles()->getByIdForManagement($imageRoleId)->roleKey === 'gallery',
        'Standalone management Role read returned the wrong Role.',
    );
    standalone_consumer_require(
        $category->imageRoles()->getByKeyForManagement('gallery')->id === $imageRoleId,
        'Standalone management Role resolve returned the wrong Role.',
    );
    standalone_consumer_require(
        $category->imageRoles()->listForManagement(
            new CategoryImageRoleListCriteriaDTO(
                status: CategoryImageRoleStatusEnum::ACTIVE,
                maxResults: 10,
            ),
        )->count() === 1,
        'Standalone management Role list did not return the stored Role.',
    );
    $managementRolePage = $category->imageRoles()->paginateForManagement(
        new CategoryImageRoleListCriteriaDTO(status: CategoryImageRoleStatusEnum::ACTIVE),
        new PageRequest(page: 1, perPage: 1),
    );
    standalone_consumer_require(
        $managementRolePage->total === 1
        && $managementRolePage->filtered === 1
        && count($managementRolePage->data) === 1
        && $managementRolePage->data[0]->id === $imageRoleId,
        'Standalone management Role pagination returned the wrong result.',
    );
    $managementContentField = $category->contentFields()->getByIdForManagement($contentFieldId);
    standalone_consumer_require(
        $managementContentField->id === $contentFieldId
        && $managementContentField->fieldKey === 'badge_config'
        && $managementContentField->format === CategoryContentFieldFormatEnum::JSON,
        'Standalone management read service returned the wrong Content Field.',
    );
    standalone_consumer_require(
        $managementContentField->value === '{"enabled":true}',
        'Standalone Content Field inline value mutation returned the wrong value.',
    );
    standalone_consumer_require(
        $category->contentFields()->listForManagement(
            new CategoryContentFieldListCriteriaDTO(
                categoryId: $categoryId,
                scope: new CategoryContentFieldScopeDTO('en-US', 'web'),
                maxResults: 10,
            ),
        )->count() === 1,
        'Standalone management Content Field list did not return the exact scope.',
    );
    $managementContentFieldPage = $category->contentFields()->paginateForManagement(
        new CategoryContentFieldListCriteriaDTO(
            categoryId: $categoryId,
            scope: new CategoryContentFieldScopeDTO('en-US', 'web'),
        ),
        new PageRequest(page: 1, perPage: 1),
    );
    standalone_consumer_require(
        $managementContentFieldPage->total === 1
        && $managementContentFieldPage->filtered === 1
        && count($managementContentFieldPage->data) === 1
        && $managementContentFieldPage->data[0]->id === $contentFieldId,
        'Standalone management Content Field pagination returned the wrong result.',
    );
    $category->categories()->updateStatus(
        new UpdateCategoryStatusCommand($categoryId, CategoryStatusEnum::INACTIVE),
    );
    $updatedCategory = $category->categories()->getByIdForManagement($categoryId, CategoryDeletedStateEnum::NON_DELETED);
    $updatedAtStatement = $pdo->prepare(
        'SELECT `updated_at` FROM `maa_category_categories` WHERE `id` = :id',
    );
    $updatedAtStatement->execute(['id' => $categoryId]);
    $storedUpdatedAt = $updatedAtStatement->fetchColumn();
    standalone_consumer_require(
        $updatedCategory->updatedAt->getTimezone()->getName() === 'Africa/Cairo'
        && is_string($storedUpdatedAt)
        && $storedUpdatedAt === $updatedCategory->updatedAt->format('Y-m-d H:i:s'),
        'Standalone update did not preserve Host timezone semantics.',
    );
} finally {
    standalone_consumer_drop_schema($pdo);
}

fwrite(
    STDOUT,
    sprintf("Standalone external consumer verification passed for %d public package types.\n", count($publicTypes)),
);
