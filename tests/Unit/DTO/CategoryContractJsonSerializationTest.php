<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentRoleFilterDTO;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleCollectionDTO;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use PHPUnit\Framework\TestCase;

final class CategoryContractJsonSerializationTest extends TestCase
{
    public function testEveryPublicDtoAndCommandIsJsonSerializableWithStableScalarShape(): void
    {
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');
        $category = new CategoryDTO(
            id: 7,
            parentId: 3,
            code: 'shirts',
            status: CategoryStatusEnum::INACTIVE,
            displayOrder: 4,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
        $content = new CategoryContentDTO(
            id: 11,
            categoryId: 7,
            languageCode: null,
            name: 'قمصان',
            description: 'وصف',
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
        $imageAssignment = new CategoryImageAssignmentDTO(
            id: 13,
            categoryId: 7,
            mediaAssetId: 900,
            languageCode: null,
            platform: 'web',
            displayOrder: 1,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
            isDefault: true,
        );
        $contentField = new CategoryContentFieldDTO(
            id: 17,
            categoryId: 7,
            fieldKey: 'badge',
            languageCode: 'ar',
            platform: 'web',
            format: CategoryContentFieldFormatEnum::JSON,
            value: '{"enabled":true}',
            displayOrder: 2,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
        $imageRole = new CategoryImageRoleDTO(
            id: 19,
            roleKey: 'gallery',
            status: CategoryImageRoleStatusEnum::ACTIVE,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );

        $dtos = [
            new CategoryIdDTO(7),
            $category,
            $content,
            new CreateCategoryCommand('shirts', 3, CategoryStatusEnum::INACTIVE),
            new CreateCategoryContentCommand(7, null, 'قمصان', 'وصف'),
            new CreateCategoryContentFieldCommand(7, 'badge', 'ar', 'web', CategoryContentFieldFormatEnum::JSON, '{"enabled":true}'),
            new CreateCategoryImageRoleCommand('gallery'),
            new UpdateCategoryImageRoleStatusCommand(19, CategoryImageRoleStatusEnum::INACTIVE),
            new CreateCategoryImageAssignmentCommand(
                7,
                900,
                new CategoryImageAssignmentScopeDTO(null, 'web'),
            ),
            new MoveCategoryCommand(7, 3),
            new RestoreCategoryCommand(7),
            new RestoreCategoryContentCommand(11),
            new RestoreCategoryContentFieldCommand(17),
            new RestoreCategoryImageAssignmentCommand(13),
            new RestoreCategoryImageRoleCommand(19),
            new SoftDeleteCategoryCommand(7),
            new SoftDeleteCategoryContentCommand(11),
            new SoftDeleteCategoryContentFieldCommand(17),
            new SoftDeleteCategoryImageAssignmentCommand(13),
            new SoftDeleteCategoryImageRoleCommand(19),
            new UpdateCategoryDisplayOrderCommand(7, 4),
            new UpdateCategoryStatusCommand(7, CategoryStatusEnum::INACTIVE),
            new UpdateCategoryContentCommand(11, 'قمصان', 'وصف'),
            new UpdateCategoryContentNameCommand(11, 'قمصان مختصرة'),
            new UpdateCategoryContentDescriptionCommand(11, 'وصف مختصر'),
            new UpdateCategoryContentFieldCommand(17, CategoryContentFieldFormatEnum::TEXT, 'new badge'),
            new UpdateCategoryContentFieldValueCommand(17, 'new badge value'),
            new UpdateCategoryContentFieldDisplayOrderCommand(17, 3),
            new UpdateCategoryImageAssignmentDisplayOrderCommand(13, 2),
            new CategoryImageAssignmentScopeDTO(null, 'web', 19),
            new CategoryImageAssignmentListCriteriaDTO(
                scope: new CategoryImageAssignmentScopeDTO('ar', 'web'),
                roleFilter: CategoryImageAssignmentRoleFilterDTO::omitted(),
            ),
            $imageAssignment,
            $imageRole,
            new CategoryContentFieldScopeDTO('ar', 'web'),
            $contentField,
            new CategoryContentFieldListCriteriaDTO(7, 'badge', new CategoryContentFieldScopeDTO('ar', 'web')),
            new CategoryImageRoleListCriteriaDTO(CategoryImageRoleStatusEnum::ACTIVE),
        ];

        foreach ($dtos as $dto) {
            self::assertInstanceOf(JsonSerializable::class, $dto);
            self::assertSame(
                $dto->jsonSerialize(),
                json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            );
        }

        self::assertInstanceOf(JsonSerializable::class, new CategoryCollectionDTO([$category]));
        self::assertInstanceOf(JsonSerializable::class, new CategoryContentCollectionDTO([$content]));
        self::assertInstanceOf(JsonSerializable::class, new CategoryImageAssignmentCollectionDTO([$imageAssignment]));
        self::assertInstanceOf(JsonSerializable::class, new CategoryImageRoleCollectionDTO([$imageRole]));
        self::assertInstanceOf(JsonSerializable::class, new CategoryContentFieldCollectionDTO([$contentField]));

        self::assertSame([
            'id' => 7,
            'parentId' => 3,
            'code' => 'shirts',
            'status' => 'inactive',
            'displayOrder' => 4,
            'createdAt' => '2026-01-01T00:00:00+02:00',
            'updatedAt' => '2026-01-01T00:00:00+02:00',
            'deletedAt' => null,
        ], $category->jsonSerialize());
        self::assertSame([
            [
                'id' => 7,
                'parentId' => 3,
                'code' => 'shirts',
                'status' => 'inactive',
                'displayOrder' => 4,
                'createdAt' => '2026-01-01T00:00:00+02:00',
                'updatedAt' => '2026-01-01T00:00:00+02:00',
                'deletedAt' => null,
            ],
        ], json_decode(
            json_encode(new CategoryCollectionDTO([$category]), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame([
            [
                'id' => 11,
                'categoryId' => 7,
                'languageCode' => null,
                'name' => 'قمصان',
                'description' => 'وصف',
                'createdAt' => '2026-01-01T00:00:00+02:00',
                'updatedAt' => '2026-01-01T00:00:00+02:00',
                'deletedAt' => null,
            ],
        ], json_decode(
            json_encode(new CategoryContentCollectionDTO([$content]), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
    }
}
