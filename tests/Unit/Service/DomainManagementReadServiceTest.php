<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service;

use DateTimeImmutable;
use Maatify\Category\Query\Contract\CategoryManagementReadQueryInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentManagementReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentManagementReadQueryInterface;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Content\Exception\CategoryContentNotFoundException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;

final class DomainManagementReadServiceTest extends DomainReadServiceTestCase
{
    public function testGetByIdPassesTheExplicitDeletedStateAndReturnsTheCategory(): void
    {
        $category = $this->category(7);
        $reader = $this->createMock(CategoryManagementReadQueryInterface::class);
        $reader->expects(self::once())
            ->method('findById')
            ->with(7, CategoryDeletedStateEnum::DELETED_ONLY)
            ->willReturn($category);

        self::assertSame(
            $category,
            ($this->categoryService(management: $reader))->getByIdForManagement(7, CategoryDeletedStateEnum::DELETED_ONLY),
        );
    }

    public function testGetByCodePassesTheExplicitDeletedStateAndReturnsTheCategory(): void
    {
        $category = $this->category(7);
        $reader = $this->createMock(CategoryManagementReadQueryInterface::class);
        $reader->expects(self::once())
            ->method('findByCode')
            ->with('category-7', CategoryDeletedStateEnum::DELETED_ONLY)
            ->willReturn($category);

        self::assertSame(
            $category,
            ($this->categoryService(management: $reader))->getByCode(
                'category-7',
                CategoryDeletedStateEnum::DELETED_ONLY,
            ),
        );
    }

    public function testMissingCategoryAndContentUseTheirSpecificNotFoundExceptions(): void
    {
        $categoryReader = $this->createStub(CategoryManagementReadQueryInterface::class);
        $categoryReader->method('findById')->willReturn(null);
        $contentReader = $this->createStub(CategoryContentManagementReadQueryInterface::class);
        $contentReader->method('findContentById')->willReturn(null);
        $service = $this->categoryService(management: $categoryReader);

        try {
            $service->getByIdForManagement(7);
            self::fail('A missing Category must throw its not-found exception.');
        } catch (CategoryNotFoundException) {
            // Expected.
        }

        $this->expectException(CategoryContentNotFoundException::class);
        $this->contentService(management: $contentReader)->getByIdForManagement(9);
    }

    public function testListCriteriaArePassedToTheDedicatedReader(): void
    {
        $categories = new CategoryCollectionDTO([$this->category(1)]);
        $contents = new CategoryContentCollectionDTO([$this->content(2)]);
        $imageAssignments = new CategoryImageAssignmentCollectionDTO([$this->imageAssignment(3)]);
        $categoryCriteria = new CategoryListCriteriaDTO(
            status: CategoryStatusEnum::INACTIVE,
            deletedState: CategoryDeletedStateEnum::INCLUDE_DELETED,
            maxResults: 2,
        );
        $contentCriteria = new CategoryContentListCriteriaDTO(
            categoryId: 1,
            deletedState: CategoryDeletedStateEnum::DELETED_ONLY,
            maxResults: 3,
        );
        $imageCriteria = new CategoryImageAssignmentListCriteriaDTO(
            categoryId: 1,
            maxResults: 3,
        );
        $categoryReader = $this->createMock(CategoryManagementReadQueryInterface::class);
        $categoryReader->expects(self::once())->method('listCategories')->with($categoryCriteria)->willReturn($categories);
        $categoryReader->expects(self::once())->method('listRootCategories')->with($categoryCriteria)->willReturn($categories);
        $categoryReader->expects(self::once())->method('listChildren')->with(1, $categoryCriteria)->willReturn($categories);
        $contentReader = $this->createMock(CategoryContentManagementReadQueryInterface::class);
        $contentReader->expects(self::once())->method('listContents')->with($contentCriteria)->willReturn($contents);
        $assignmentReader = $this->createMock(CategoryImageAssignmentManagementReadQueryInterface::class);
        $assignmentReader->expects(self::once())
            ->method('listImageAssignments')
            ->with($imageCriteria)
            ->willReturn($imageAssignments);
        $service = $this->categoryService(management: $categoryReader);

        self::assertSame($categories, $service->listForManagement($categoryCriteria));
        self::assertSame($categories, $service->listRootCategoriesForManagement($categoryCriteria));
        self::assertSame($categories, $service->listChildrenForManagement(1, $categoryCriteria));
        self::assertSame($contents, $this->contentService(management: $contentReader)->listForManagement($contentCriteria));
        self::assertSame($imageAssignments, $this->imageAssignmentService(management: $assignmentReader)->listForManagement($imageCriteria));
    }

    public function testCategoryPaginationRequestIsPassedToTheDedicatedReader(): void
    {
        $criteria = new CategoryListCriteriaDTO(search: 'category');
        $pageRequest = new PageRequest(page: 2, perPage: 1, sortBy: 'code', sortDirection: 'DESC');
        $page = new PageResult(
            data: [$this->category(2)],
            page: 2,
            perPage: 1,
            total: 2,
            filtered: 2,
            totalPages: 2,
            hasNext: false,
            hasPrevious: true,
            sortBy: 'code',
            sortDirection: SortDirectionEnum::DESC,
        );
        $reader = $this->createMock(CategoryManagementReadQueryInterface::class);
        $reader->expects(self::once())
            ->method('paginateCategories')
            ->with($criteria, $pageRequest)
            ->willReturn($page);

        self::assertSame(
            $page,
            ($this->categoryService(management: $reader))->paginateForManagement($criteria, $pageRequest),
        );
    }

    public function testCriteriaRejectNonPositiveCategoryIds(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new CategoryContentListCriteriaDTO(categoryId: 0);
    }

    public function testCriteriaRejectOutOfBoundsMaximums(): void
    {
        $this->expectException(CategoryInvalidArgumentException::class);

        new CategoryListCriteriaDTO(maxResults: CategoryListCriteriaDTO::MAX_MAX_RESULTS + 1);
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
