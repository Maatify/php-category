<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service;

use DateTimeImmutable;
use Maatify\Category\Query\Contract\CategoryReadQueryInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentReadQueryInterface;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\Exception\CategoryNotFoundException;

final class DomainVisibleReadServiceTest extends DomainReadServiceTestCase
{
    public function testGetByIdReturnsOnlyTheVisibleCategoryFromTheReader(): void
    {
        $category = $this->category(7);
        $reader = $this->createMock(CategoryReadQueryInterface::class);
        $reader->expects(self::once())
            ->method('findVisibleById')
            ->with(7)
            ->willReturn($category);

        $result = ($this->categoryService(visible: $reader))->getById(7);

        self::assertSame($category, $result);
    }

    public function testGetByIdRejectsAnUnavailableCategory(): void
    {
        $reader = $this->createStub(CategoryReadQueryInterface::class);
        $reader->method('findVisibleById')->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);

        ($this->categoryService(visible: $reader))->getById(7);
    }

    public function testGetByIdRejectsANonPositiveIdentity(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        ($this->categoryService())->getById(0);
    }

    public function testVisibleListCriteriaRejectsOutOfBoundsMaximums(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new CategoryVisibleListCriteriaDTO(CategoryVisibleListCriteriaDTO::MAX_MAX_RESULTS + 1);
    }

    public function testListOperationsReturnTypedCollectionsFromTheReader(): void
    {
        $categories = new CategoryCollectionDTO([$this->category(1)]);
        $contents = new CategoryContentCollectionDTO([$this->content(2)]);
        $imageAssignments = new CategoryImageAssignmentCollectionDTO([$this->imageAssignment(3)]);
        $scope = new CategoryImageAssignmentScopeDTO('en-US', 'web');
        $criteria = new CategoryVisibleListCriteriaDTO(2);
        $categoryReader = $this->createMock(CategoryReadQueryInterface::class);
        $categoryReader->expects(self::once())->method('listVisibleRootCategories')->with($criteria)->willReturn($categories);
        $categoryReader->expects(self::once())->method('listVisibleChildren')->with(1, $criteria)->willReturn($categories);
        $contentReader = $this->createMock(CategoryContentReadQueryInterface::class);
        $contentReader->expects(self::once())->method('listVisibleContents')->with(1, $criteria)->willReturn($contents);
        $assignmentReader = $this->createMock(CategoryImageAssignmentReadQueryInterface::class);
        $assignmentReader->expects(self::once())
            ->method('listVisibleImageAssignments')
            ->with(1, $scope, $criteria)
            ->willReturn($imageAssignments);
        $service = $this->categoryService(visible: $categoryReader);

        self::assertSame($categories, $service->listRootCategories($criteria));
        self::assertSame($categories, $service->listChildren(1, $criteria));
        self::assertSame($contents, $this->contentService(visible: $contentReader)->listVisibleForCategory(1, $criteria));
        self::assertSame($imageAssignments, $this->imageAssignmentService(visible: $assignmentReader)->listVisibleForCategory(1, $scope, $criteria));
    }

    private function category(int $id): CategoryDTO
    {
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');

        return new CategoryDTO(
            id: $id,
            parentId: null,
            code: 'category-' . $id,
            status: CategoryStatusEnum::ACTIVE,
            displayOrder: 1,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
    }

    private function content(int $id): CategoryContentDTO
    {
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');

        return new CategoryContentDTO(
            id: $id,
            categoryId: 1,
            languageCode: 'en-US',
            name: 'Category',
            description: null,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
    }

    private function imageAssignment(int $id): CategoryImageAssignmentDTO
    {
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');

        return new CategoryImageAssignmentDTO(
            id: $id,
            categoryId: 1,
            mediaAssetId: 900,
            languageCode: 'en-US',
            platform: 'web',
            displayOrder: 1,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
    }
}
