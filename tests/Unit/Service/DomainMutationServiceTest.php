<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service;

use DateTimeImmutable;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\Lifecycle\Exception\CategoryCodeAlreadyExistsException;
use Maatify\Category\Hierarchy\Exception\CategoryCycleException;
use Maatify\Category\Lifecycle\Exception\CategoryHasNonDeletedChildrenException;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\Service\CategoryService;
use Maatify\Category\Content\Service\ContentService;
use Maatify\Category\Content\Query\Contract\CategoryContentManagementReadQueryInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentReadQueryInterface;
use Maatify\Category\ContentField\Service\ContentFieldService;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldManagementReadQueryInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldReadQueryInterface;
use Maatify\Category\ImageAssignment\Service\ImageAssignmentService;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentManagementReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentReadQueryInterface;
use Maatify\Category\Query\Contract\CategoryReadQueryInterface;
use Maatify\Category\Query\Contract\CategoryManagementReadQueryInterface;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryQueryReader;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryCommandRepository;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryContentCommandRepository;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryImageAssignmentCommandRepository;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryContentFieldCommandRepository;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryTransaction;
use Maatify\Category\Tests\Unit\Service\Support\FixedClock;
use PHPUnit\Framework\TestCase;
use Throwable;

final class DomainMutationServiceTest extends TestCase
{
    public function testCreateValidatesParentAndKeepsCodeOutsideMutationContracts(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([$this->category(7, null)]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        $createdId = $service->create(new CreateCategoryCommand('shirts', '7'));

        self::assertSame(99, $createdId);
        $created = $commandRepository->created;
        self::assertNotNull($created);
        self::assertSame('shirts', $created->code);
        self::assertSame(7, $created->parentId);
        self::assertSame(1, $transaction->runs);
        self::assertSame([7], $queryReader->lockedIds);
        self::assertSame('2026-01-03 00:00:00', $commandRepository->occurredAt?->format('Y-m-d H:i:s'));
        self::assertFalse(property_exists(UpdateCategoryStatusCommand::class, 'code'));
        self::assertFalse(property_exists(UpdateCategoryDisplayOrderCommand::class, 'code'));
    }

    public function testCreateRejectsAStableCodeThatAlreadyExistsIncludingSoftDeletedRows(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([$this->category(7, null, $this->deletedAt())]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $service = $this->service($commandRepository, $queryReader);

        $this->expectException(CategoryCodeAlreadyExistsException::class);
        $service->create(new CreateCategoryCommand('category-7'));
    }

    public function testMoveToAValidParentIsDelegated(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([
            $this->category(1, null),
            $this->category(2, null),
            $this->category(3, 1),
        ]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        $service->move(new MoveCategoryCommand(3, 2));

        $moved = $commandRepository->moved;
        self::assertNotNull($moved);
        self::assertSame(3, $moved->categoryId);
        self::assertSame(2, $moved->parentId);
        self::assertSame(1, $transaction->runs);
        self::assertSame([3, 2], $queryReader->lockedIds);
        self::assertSame('2026-01-03 00:00:00', $commandRepository->occurredAt?->format('Y-m-d H:i:s'));
    }

    public function testDirectSelfParentIsRejectedByTheInputCommand(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new MoveCategoryCommand('7', '7');
    }

    public function testIndirectCycleIsRejectedAcrossTheWholeAncestorChain(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([
            $this->category(1, null),
            $this->category(2, 1),
            $this->category(3, 2),
        ]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        try {
            $service->move(new MoveCategoryCommand(1, 3));
            self::fail('The complete ancestor chain must reject an indirect cycle.');
        } catch (CategoryCycleException) {
            self::assertSame(1, $transaction->runs);
            self::assertSame(1, $transaction->rollbacks);
            self::assertSame([1, 3, 2], $queryReader->lockedIds);
        }
    }

    public function testSoftDeleteRejectsANonDeletedChild(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([
            $this->category(1, null),
            $this->category(2, 1),
        ]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        try {
            $service->softDelete(new SoftDeleteCategoryCommand(1));
            self::fail('A Category with a non-deleted child must not be soft-deleted.');
        } catch (CategoryHasNonDeletedChildrenException) {
            self::assertSame(1, $transaction->runs);
            self::assertSame(1, $transaction->rollbacks);
            self::assertNull($commandRepository->softDeleted);
        }
    }

    public function testSoftDeleteIsAllowedWhenAllChildrenAreDeleted(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([
            $this->category(1, null),
            $this->category(2, 1, $this->deletedAt()),
        ]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        $service->softDelete(new SoftDeleteCategoryCommand('1'));

        $deleted = $commandRepository->softDeleted;
        self::assertNotNull($deleted);
        self::assertSame(1, $deleted->categoryId);
        self::assertSame(1, $transaction->runs);
    }

    public function testRestoreUsesTheExistingCategoryIdentity(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([$this->category(11, null, $this->deletedAt())]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        $service->restore(new RestoreCategoryCommand('11'));

        $restored = $commandRepository->restored;
        self::assertNotNull($restored);
        self::assertSame(11, $restored->categoryId);
        self::assertSame(1, $transaction->runs);
    }

    public function testStatusAndDisplayOrderMutationsAreDedicatedOperations(): void
    {
        $queryReader = new InMemoryCategoryQueryReader([$this->category(5, null)]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);

        $service->updateStatus(new UpdateCategoryStatusCommand(5, CategoryStatusEnum::INACTIVE));
        $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand(5, 3));

        self::assertSame(CategoryStatusEnum::INACTIVE, $commandRepository->statusUpdated?->status);
        self::assertSame(3, $commandRepository->displayOrderUpdated?->displayOrder);
        self::assertSame([5, 5], $queryReader->lockedIds);
        self::assertSame(2, $transaction->runs);
    }

    public function testAllDisplayOrderMutationsUseTheSharedTransactionRunnerAndLockRows(): void
    {
        $assignment = new CategoryImageAssignmentDTO(
            id: 21,
            categoryId: 5,
            mediaAssetId: 900,
            languageCode: null,
            platform: null,
            displayOrder: 1,
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: null,
        );
        $field = new CategoryContentFieldDTO(
            id: 31,
            categoryId: 5,
            fieldKey: 'badge',
            languageCode: null,
            platform: null,
            format: CategoryContentFieldFormatEnum::TEXT,
            value: 'new',
            displayOrder: 1,
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: null,
        );
        $queryReader = new InMemoryCategoryQueryReader(
            [$this->category(5, null)],
            [],
            [$assignment],
            [$field],
        );
        $commandRepository = new InMemoryCategoryCommandRepository();
        $imageRepository = new InMemoryCategoryImageAssignmentCommandRepository();
        $fieldRepository = new InMemoryCategoryContentFieldCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = $this->service($commandRepository, $queryReader, $transaction);
        $imageService = new ImageAssignmentService(
            $imageRepository,
            $queryReader,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryImageAssignmentReadQueryInterface::class),
            $this->createStub(CategoryImageAssignmentManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );
        $fieldService = new ContentFieldService(
            $fieldRepository,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryContentFieldReadQueryInterface::class),
            $this->createStub(CategoryContentFieldManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $service->updateDisplayOrder(new UpdateCategoryDisplayOrderCommand(5, 2));
        $imageService->reorder(
            new UpdateCategoryImageAssignmentDisplayOrderCommand(21, 2),
        );
        $fieldService->updateDisplayOrder(
            new UpdateCategoryContentFieldDisplayOrderCommand(31, 2),
        );

        self::assertSame(3, $transaction->runs);
        self::assertSame(3, $transaction->commits);
        self::assertSame([5], $queryReader->lockedIds);
        self::assertSame([21], $queryReader->lockedAssignmentIds);
        self::assertSame([31], $queryReader->lockedContentFieldIds);
        self::assertSame(2, $commandRepository->displayOrderUpdated?->displayOrder);
        self::assertSame(2, $imageRepository->displayOrderUpdated?->displayOrder);
        self::assertSame(2, $fieldRepository->displayOrderUpdated?->displayOrder);
    }

    public function testContentMutationCannotChangeItsLogicalIdentity(): void
    {
        $content = new CategoryContentDTO(
            id: 21,
            categoryId: 5,
            languageCode: 'en-US',
            name: 'Shirts',
            description: null,
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: null,
        );
        $queryReader = new InMemoryCategoryQueryReader([$this->category(5, null)], [$content]);
        $commandRepository = new InMemoryCategoryCommandRepository();
        $contentRepository = new InMemoryCategoryContentCommandRepository();
        $service = new ContentService(
            $contentRepository,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryContentReadQueryInterface::class),
            $this->createStub(CategoryContentManagementReadQueryInterface::class),
            new InMemoryCategoryTransaction(),
            new FixedClock(),
        );

        $service->update(new UpdateCategoryContentCommand(21, 'قمصان', 'وصف'));

        $updated = $contentRepository->updated;
        self::assertNotNull($updated);
        self::assertSame(21, $updated->contentId);
        self::assertSame([21], $queryReader->lockedContentIds);
        self::assertFalse(property_exists(UpdateCategoryContentCommand::class, 'categoryId'));
        self::assertFalse(property_exists(UpdateCategoryContentCommand::class, 'languageCode'));
    }

    public function testContentInlineMutationsPreserveTheOtherContentField(): void
    {
        $content = new CategoryContentDTO(
            id: 21,
            categoryId: 5,
            languageCode: 'en-US',
            name: 'Shirts',
            description: 'Original description',
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: null,
        );
        $queryReader = new InMemoryCategoryQueryReader([$this->category(5, null)], [$content]);
        $contentRepository = new InMemoryCategoryContentCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = new ContentService(
            $contentRepository,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryContentReadQueryInterface::class),
            $this->createStub(CategoryContentManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $service->updateName(new UpdateCategoryContentNameCommand(21, 'T-Shirts'));
        $service->updateDescription(new UpdateCategoryContentDescriptionCommand(21, null));

        self::assertCount(2, $contentRepository->updates);
        self::assertSame('T-Shirts', $contentRepository->updates[0]->name);
        self::assertSame('Original description', $contentRepository->updates[0]->description);
        self::assertSame('Shirts', $contentRepository->updates[1]->name);
        self::assertNull($contentRepository->updates[1]->description);
        self::assertSame([21, 21], $queryReader->lockedContentIds);
        self::assertSame(2, $transaction->runs);
    }

    public function testContentFieldInlineValueMutationPreservesFormatAndUsesTheAtomicUpdateCommand(): void
    {
        $field = new CategoryContentFieldDTO(
            id: 31,
            categoryId: 5,
            fieldKey: 'badge',
            languageCode: null,
            platform: null,
            format: CategoryContentFieldFormatEnum::JSON,
            value: '{"enabled":true}',
            displayOrder: 1,
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: null,
        );
        $queryReader = new InMemoryCategoryQueryReader(
            [$this->category(5, null)],
            [],
            [],
            [$field],
        );
        $fieldRepository = new InMemoryCategoryContentFieldCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = new ContentFieldService(
            $fieldRepository,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryContentFieldReadQueryInterface::class),
            $this->createStub(CategoryContentFieldManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $service->updateValue(new UpdateCategoryContentFieldValueCommand(31, '{"enabled":false}'));

        self::assertNotNull($fieldRepository->updated);
        self::assertSame(31, $fieldRepository->updated->fieldId);
        self::assertSame(CategoryContentFieldFormatEnum::JSON, $fieldRepository->updated->format);
        self::assertSame('{"enabled":false}', $fieldRepository->updated->value);
        self::assertSame([31], $queryReader->lockedContentFieldIds);
        self::assertSame(1, $transaction->runs);
    }

    public function testContentLifecycleUsesTypedOperationsAndPreservesIdentity(): void
    {
        $queryReader = new InMemoryCategoryQueryReader(
            [$this->category(5, null)],
            [new CategoryContentDTO(
                id: 77,
                categoryId: 5,
                languageCode: 'en-US',
                name: 'Shirts',
                description: null,
                createdAt: $this->createdAt(),
                updatedAt: $this->createdAt(),
                deletedAt: null,
            )],
        );
        $contentRepository = new InMemoryCategoryContentCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = new ContentService(
            $contentRepository,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryContentReadQueryInterface::class),
            $this->createStub(CategoryContentManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $createdId = $service->create(
            new CreateCategoryContentCommand(5, 'en-US', 'Shirts', null),
        );
        $service->update(new UpdateCategoryContentCommand($createdId, 'قمصان', 'وصف'));
        $service->softDelete(new SoftDeleteCategoryContentCommand($createdId));
        $service->restore(new RestoreCategoryContentCommand($createdId));

        self::assertSame(77, $createdId);
        self::assertNotNull($contentRepository->created);
        self::assertSame(5, $contentRepository->created->categoryId);
        self::assertSame('en-US', $contentRepository->created->languageCode);
        self::assertSame($createdId, $contentRepository->updated?->contentId);
        self::assertSame($createdId, $contentRepository->softDeleted?->contentId);
        self::assertSame($createdId, $contentRepository->restored?->contentId);
        self::assertSame(4, $transaction->runs);
    }

    public function testImageAssignmentLifecycleUsesTypedOperationsAndKeepsItsIdentity(): void
    {
        $assignment = new CategoryImageAssignmentDTO(
            id: 77,
            categoryId: 5,
            mediaAssetId: 900,
            languageCode: 'en-US',
            platform: 'web',
            displayOrder: 1,
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: null,
        );
        $queryReader = new InMemoryCategoryQueryReader(
            [$this->category(5, null)],
            [],
            [$assignment],
        );
        $imageRepository = new InMemoryCategoryImageAssignmentCommandRepository();
        $transaction = new InMemoryCategoryTransaction();
        $service = new ImageAssignmentService(
            $imageRepository,
            $queryReader,
            $queryReader,
            $queryReader,
            $this->createStub(CategoryImageAssignmentReadQueryInterface::class),
            $this->createStub(CategoryImageAssignmentManagementReadQueryInterface::class),
            $transaction,
            new FixedClock(),
        );

        $createdId = $service->assign(
            new CreateCategoryImageAssignmentCommand(
                5,
                900,
                new CategoryImageAssignmentScopeDTO('en-US', 'web'),
            ),
        );
        $service->reorder(
            new UpdateCategoryImageAssignmentDisplayOrderCommand($createdId, 2),
        );
        $service->setDefault(new SetCategoryImageAssignmentDefaultCommand($createdId));
        $service->clearDefault(new ClearCategoryImageAssignmentDefaultCommand($createdId));
        $service->remove(new SoftDeleteCategoryImageAssignmentCommand($createdId));
        $service->restore(new RestoreCategoryImageAssignmentCommand($createdId));

        self::assertSame(77, $createdId);
        self::assertNotNull($imageRepository->created);
        self::assertNotNull($imageRepository->displayOrderUpdated);
        self::assertNotNull($imageRepository->defaultSet);
        self::assertNotNull($imageRepository->defaultCleared);
        self::assertNotNull($imageRepository->softDeleted);
        self::assertNotNull($imageRepository->restored);
        self::assertSame(5, $imageRepository->created->categoryId);
        self::assertSame(900, $imageRepository->created->mediaAssetId);
        self::assertSame(2, $imageRepository->displayOrderUpdated->displayOrder);
        self::assertSame($createdId, $imageRepository->softDeleted->assignmentId);
        self::assertSame($createdId, $imageRepository->restored->assignmentId);
        self::assertSame($createdId, $imageRepository->defaultSet->assignmentId);
        self::assertSame($createdId, $imageRepository->defaultCleared->assignmentId);
        self::assertSame(6, $transaction->runs);
    }

    private function service(
        InMemoryCategoryCommandRepository $commandRepository,
        InMemoryCategoryQueryReader $queryReader,
        ?InMemoryCategoryTransaction $transaction = null,
    ): CategoryService {
        return new CategoryService(
            $commandRepository,
            $queryReader,
            $this->createStub(CategoryReadQueryInterface::class),
            $this->createStub(CategoryManagementReadQueryInterface::class),
            $transaction ?? new InMemoryCategoryTransaction(),
            new FixedClock(),
        );
    }

    private function category(int $id, ?int $parentId, ?DateTimeImmutable $deletedAt = null): CategoryDTO
    {
        return new CategoryDTO(
            id: $id,
            parentId: $parentId,
            code: 'category-' . $id,
            status: CategoryStatusEnum::ACTIVE,
            displayOrder: 1,
            createdAt: $this->createdAt(),
            updatedAt: $this->createdAt(),
            deletedAt: $deletedAt,
        );
    }

    private function createdAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');
    }

    private function deletedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-02 00:00:00 Africa/Cairo');
    }
}
