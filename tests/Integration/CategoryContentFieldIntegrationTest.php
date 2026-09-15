<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\ContentField\Mutation\Exception\CategoryContentFieldAlreadyExistsException;
use Maatify\Category\ContentField\Exception\CategoryContentFieldNotFoundException;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\ContentField\Api\Contract\ContentFieldApiInterface;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use PDO;

final class CategoryContentFieldIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testAllFourScopesFormatsAndIndependentOrderingAreSupported(): void
    {
        $service = $this->commandService($this->connection());
        $fieldService = $this->fieldService($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('field-scopes-category'));

        $neutralFirst = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'alpha_information', null, null, CategoryContentFieldFormatEnum::TEXT, 'Use gently.'),
        );
        $neutralSecond = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'zeta_instructions', null, null, CategoryContentFieldFormatEnum::HTML, '<p>Details</p>'),
        );
        $languageOnly = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'targeting', 'en-US', null, CategoryContentFieldFormatEnum::JSON, '{"audience":["adult"]}'),
        );
        $platformOnly = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'custom_information', null, 'web', CategoryContentFieldFormatEnum::TEXT, 'Web only'),
        );
        $languageAndPlatform = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'targeting', 'en-US', 'web', CategoryContentFieldFormatEnum::HTML, '<strong>Web</strong>'),
        );

        self::assertSame(
            [$neutralFirst, $neutralSecond],
            $this->ids($fieldService->listVisibleForCategory($categoryId, new CategoryContentFieldScopeDTO())),
        );
        self::assertSame(
            [$languageOnly],
            $this->ids($fieldService->listVisibleForCategory($categoryId, new CategoryContentFieldScopeDTO('en-US'))),
        );
        self::assertSame(
            [$platformOnly],
            $this->ids($fieldService->listVisibleForCategory($categoryId, new CategoryContentFieldScopeDTO(null, 'web'))),
        );
        self::assertSame(
            [$languageAndPlatform],
            $this->ids($fieldService->listVisibleForCategory($categoryId, new CategoryContentFieldScopeDTO('en-US', 'web'))),
        );

        $neutral = $fieldService->listVisibleForCategory($categoryId, new CategoryContentFieldScopeDTO());
        self::assertSame(
            [CategoryContentFieldFormatEnum::TEXT, CategoryContentFieldFormatEnum::HTML],
            $this->formats($neutral),
        );

        $fieldService->updateDisplayOrder(
            new UpdateCategoryContentFieldDisplayOrderCommand($neutralFirst, 2),
        );
        self::assertSame(
            [$neutralSecond, $neutralFirst],
            $this->ids($fieldService->listVisibleForCategory($categoryId, new CategoryContentFieldScopeDTO())),
        );
        self::assertSame(
            [$neutralSecond, $neutralFirst],
            $this->ids($fieldService->listForManagement(new CategoryContentFieldListCriteriaDTO(
                categoryId: $categoryId,
                scope: new CategoryContentFieldScopeDTO(),
            ))),
        );
    }

    public function testJsonSyntaxIsValidatedAtRuntimeBoundary(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new CreateCategoryContentFieldCommand(
            1,
            'targeting',
            null,
            null,
            CategoryContentFieldFormatEnum::JSON,
            '{invalid',
        );
    }

    public function testIdentityIsExactAndReservedAcrossSoftDeletion(): void
    {
        $service = $this->commandService($this->connection());
        $fieldService = $this->fieldService($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('field-identity-category'));
        $fieldId = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'targeting', null, null, CategoryContentFieldFormatEnum::TEXT, 'neutral'),
        );

        $fieldService->softDelete(new SoftDeleteCategoryContentFieldCommand($fieldId));

        try {
            $fieldService->create(
                new CreateCategoryContentFieldCommand($categoryId, 'targeting', null, null, CategoryContentFieldFormatEnum::HTML, '<p>duplicate</p>'),
            );
            self::fail('A soft-deleted field must continue reserving its exact identity.');
        } catch (CategoryContentFieldAlreadyExistsException) {
        }

        $fieldService->restore(new RestoreCategoryContentFieldCommand($fieldId));
        $restored = $fieldService->getByIdForManagement($fieldId);
        self::assertSame($fieldId, $restored->id);
        self::assertSame($categoryId, $restored->categoryId);
        self::assertSame('targeting', $restored->fieldKey);
        self::assertNull($restored->languageCode);
        self::assertNull($restored->platform);
        self::assertNull($restored->deletedAt);
    }

    public function testSameKeyIsAllowedInAnotherScopeAndManagementScopeFilterIsExact(): void
    {
        $service = $this->commandService($this->connection());
        $fieldService = $this->fieldService($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('field-management-category'));
        $neutral = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'targeting', null, null, CategoryContentFieldFormatEnum::TEXT, 'neutral'),
        );
        $language = $fieldService->create(
            new CreateCategoryContentFieldCommand($categoryId, 'targeting', 'en-US', null, CategoryContentFieldFormatEnum::TEXT, 'localized'),
        );

        self::assertEqualsCanonicalizing(
            [$neutral, $language],
            $this->ids($fieldService->listForManagement(new CategoryContentFieldListCriteriaDTO(categoryId: $categoryId))),
        );
        self::assertSame(
            [$neutral],
            $this->ids($fieldService->listForManagement(new CategoryContentFieldListCriteriaDTO(
                categoryId: $categoryId,
                scope: new CategoryContentFieldScopeDTO(),
            ))),
        );
    }

    public function testUpdateDeleteRestoreAndExactConsumerVisibilityFollowLifecycleContract(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $fieldService = $this->fieldService($connection);
        $rootId = $service->create(new CreateCategoryCommand('field-visibility-root'));
        $childId = $service->create(new CreateCategoryCommand('field-visibility-child', $rootId));
        $fieldId = $fieldService->create(
            new CreateCategoryContentFieldCommand($childId, 'custom_information', 'en-US', 'web', CategoryContentFieldFormatEnum::TEXT, 'before'),
        );

        $fieldService->update(new UpdateCategoryContentFieldCommand(
            $fieldId,
            CategoryContentFieldFormatEnum::JSON,
            '{"enabled":true}',
        ));
        $updated = $fieldService->getByIdForManagement($fieldId);
        self::assertSame(CategoryContentFieldFormatEnum::JSON, $updated->format);
        self::assertSame('{"enabled":true}', $updated->value);

        self::assertSame(
            [$fieldId],
            $this->ids($fieldService->listVisibleForCategory($childId, new CategoryContentFieldScopeDTO('en-US', 'web'))),
        );
        self::assertTrue(
            $fieldService->listVisibleForCategory($childId, new CategoryContentFieldScopeDTO('en-GB', 'web'))->isEmpty(),
        );

        $fieldService->softDelete(new SoftDeleteCategoryContentFieldCommand($fieldId));
        self::assertTrue(
            $fieldService->listVisibleForCategory($childId, new CategoryContentFieldScopeDTO('en-US', 'web'))->isEmpty(),
        );
        self::assertNotNull($fieldService->getByIdForManagement($fieldId, CategoryDeletedStateEnum::DELETED_ONLY)->deletedAt);

        $fieldService->restore(new RestoreCategoryContentFieldCommand($fieldId));
        self::assertSame(
            [$fieldId],
            $this->ids($fieldService->listVisibleForCategory($childId, new CategoryContentFieldScopeDTO('en-US', 'web'))),
        );

        $service->updateStatus(new UpdateCategoryStatusCommand($rootId, CategoryStatusEnum::INACTIVE));
        self::assertTrue(
            $fieldService->listVisibleForCategory($childId, new CategoryContentFieldScopeDTO('en-US', 'web'))->isEmpty(),
        );
    }

    public function testManagementPaginationPreservesExactScopeOrderingByDefault(): void
    {
        $service = $this->commandService($this->connection());
        $fieldService = $this->fieldService($this->connection());
        $categoryId = $service->create(new CreateCategoryCommand('field-pagination-category'));
        $neutralId = $fieldService->create(
            new CreateCategoryContentFieldCommand(
                $categoryId,
                'neutral',
                null,
                null,
                CategoryContentFieldFormatEnum::TEXT,
                'neutral',
            ),
        );
        $localizedId = $fieldService->create(
            new CreateCategoryContentFieldCommand(
                $categoryId,
                'localized',
                'en-US',
                null,
                CategoryContentFieldFormatEnum::TEXT,
                'localized',
            ),
        );

        $page = $fieldService->paginateForManagement(
            new CategoryContentFieldListCriteriaDTO(categoryId: $categoryId),
            new PageRequest(perPage: 2),
        );
        $ids = [];
        foreach ($page->data as $field) {
            $ids[] = $field->id;
        }

        self::assertSame('business_order', $page->sortBy);
        self::assertSame([$localizedId, $neutralId], $ids);

        $categorySortedPage = $fieldService->paginateForManagement(
            new CategoryContentFieldListCriteriaDTO(categoryId: $categoryId),
            new PageRequest(perPage: 2, sortBy: 'category_id'),
        );
        $categorySortedIds = [];
        foreach ($categorySortedPage->data as $field) {
            $categorySortedIds[] = $field->id;
        }

        self::assertSame('category_id', $categorySortedPage->sortBy);
        self::assertSame([$neutralId, $localizedId], $categorySortedIds);
    }

    public function testContentFieldInlineValueMutationPreservesFormatAtomically(): void
    {
        $categoryService = $this->commandService($this->connection());
        $fieldService = $this->fieldService($this->connection());
        $categoryId = $categoryService->create(new CreateCategoryCommand('field-inline-category'));
        $fieldId = $fieldService->create(
            new CreateCategoryContentFieldCommand(
                $categoryId,
                'badge',
                'en-US',
                'web',
                CategoryContentFieldFormatEnum::JSON,
                '{"enabled":true}',
            ),
        );

        $fieldService->updateValue(new UpdateCategoryContentFieldValueCommand($fieldId, '{"enabled":false}'));
        $updated = $fieldService->getByIdForManagement($fieldId);
        self::assertSame(CategoryContentFieldFormatEnum::JSON, $updated->format);
        self::assertSame('{"enabled":false}', $updated->value);

        try {
            $fieldService->updateValue(new UpdateCategoryContentFieldValueCommand($fieldId, '{invalid'));
            self::fail('The inline value operation must validate against the stored JSON format.');
        } catch (CategoryInvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $unchanged = $fieldService->getByIdForManagement($fieldId);
        self::assertSame(CategoryContentFieldFormatEnum::JSON, $unchanged->format);
        self::assertSame('{"enabled":false}', $unchanged->value);

        $fieldService->softDelete(new SoftDeleteCategoryContentFieldCommand($fieldId));
        $this->expectException(CategoryContentFieldNotFoundException::class);
        $fieldService->updateValue(new UpdateCategoryContentFieldValueCommand($fieldId, '{"enabled":true}'));
    }

    /** @return list<int> */
    private function ids(\Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO $fields): array
    {
        $ids = [];
        foreach ($fields as $field) {
            $ids[] = $field->id;
        }

        return $ids;
    }

    /** @return list<CategoryContentFieldFormatEnum> */
    private function formats(\Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO $fields): array
    {
        $formats = [];
        foreach ($fields as $field) {
            $formats[] = $field->format;
        }

        return $formats;
    }

    private function commandService(PDO $connection): CategoryApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        )->categories();
    }

    private function fieldService(PDO $connection): ContentFieldApiInterface
    {
        return CategoryFactory::create(
            $connection,
            new FixedCategoryClock('2026-01-01 00:00:00 Africa/Cairo'),
        )->contentFields();
    }
}
