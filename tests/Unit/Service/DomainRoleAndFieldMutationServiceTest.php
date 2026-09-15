<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service;

use DateTimeImmutable;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\ContentField\Service\ContentFieldService;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldManagementReadQueryInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldReadQueryInterface;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\ImageRole\Service\ImageRoleService;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleManagementReadQueryInterface;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Tests\Unit\Service\Support\FixedClock;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryContentFieldCommandRepository;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryImageRoleCommandRepository;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryQueryReader;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryTransaction;
use PHPUnit\Framework\TestCase;

final class DomainRoleAndFieldMutationServiceTest extends TestCase
{
    public function testContentFieldServiceCoversItsCompleteMutationLifecycle(): void
    {
        $field = new CategoryContentFieldDTO(
            id: 88,
            categoryId: 5,
            fieldKey: 'badge',
            languageCode: null,
            platform: null,
            format: CategoryContentFieldFormatEnum::TEXT,
            value: 'old',
            displayOrder: 1,
            createdAt: $this->timestamp(),
            updatedAt: $this->timestamp(),
            deletedAt: null,
        );
        $repository = new InMemoryCategoryContentFieldCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = new ContentFieldService(
            $repository,
            $reader = new InMemoryCategoryQueryReader([$this->category(5)], fields: [$field]),
            $reader,
            $this->createStub(CategoryContentFieldReadQueryInterface::class),
            $this->createStub(CategoryContentFieldManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $fieldId = $service->create(new CreateCategoryContentFieldCommand(
            5,
            'badge',
            null,
            null,
            CategoryContentFieldFormatEnum::TEXT,
            'new',
        ));
        $service->update(new UpdateCategoryContentFieldCommand(
            $fieldId,
            CategoryContentFieldFormatEnum::TEXT,
            'updated',
        ));
        $service->updateDisplayOrder(new UpdateCategoryContentFieldDisplayOrderCommand($fieldId, 2));
        $service->softDelete(new SoftDeleteCategoryContentFieldCommand($fieldId));
        $service->restore(new RestoreCategoryContentFieldCommand($fieldId));

        self::assertSame(88, $fieldId);
        self::assertNotNull($repository->created);
        self::assertNotNull($repository->updated);
        self::assertSame(5, $repository->created->categoryId);
        self::assertSame('badge', $repository->created->fieldKey);
        self::assertSame($fieldId, $repository->updated->fieldId);
        self::assertSame('updated', $repository->updated->value);
        self::assertSame(2, $repository->displayOrderUpdated?->displayOrder);
        self::assertSame($fieldId, $repository->softDeleted?->fieldId);
        self::assertSame($fieldId, $repository->restored?->fieldId);
        self::assertSame(5, $transaction->runs);
    }

    public function testImageRoleServiceCoversItsCompleteMutationLifecycle(): void
    {
        $role = new CategoryImageRoleDTO(
            id: 66,
            roleKey: 'hero',
            status: CategoryImageRoleStatusEnum::ACTIVE,
            createdAt: $this->timestamp(),
            updatedAt: $this->timestamp(),
            deletedAt: null,
        );
        $repository = new InMemoryCategoryImageRoleCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = new ImageRoleService(
            $repository,
            $reader = new InMemoryCategoryQueryReader([], roles: [$role]),
            $this->createStub(CategoryImageRoleManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $roleId = $service->create(new CreateCategoryImageRoleCommand('hero'));
        $service->updateStatus(new UpdateCategoryImageRoleStatusCommand(
            $roleId,
            CategoryImageRoleStatusEnum::INACTIVE,
        ));
        $service->softDelete(new SoftDeleteCategoryImageRoleCommand($roleId));
        $service->restore(new RestoreCategoryImageRoleCommand($roleId));

        self::assertSame(66, $roleId);
        self::assertSame('hero', $repository->created?->roleKey);
        self::assertSame(CategoryImageRoleStatusEnum::INACTIVE, $repository->statusUpdated?->status);
        self::assertSame($roleId, $repository->softDeleted?->roleId);
        self::assertSame($roleId, $repository->restored?->roleId);
        self::assertSame(4, $transaction->runs);
    }

    private function category(int $id): CategoryDTO
    {
        return new CategoryDTO(
            id: $id,
            parentId: null,
            code: 'category-' . $id,
            status: CategoryStatusEnum::ACTIVE,
            displayOrder: 1,
            createdAt: $this->timestamp(),
            updatedAt: $this->timestamp(),
            deletedAt: null,
        );
    }

    private function timestamp(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');
    }
}
