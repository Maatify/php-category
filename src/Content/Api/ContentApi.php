<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Api;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\Content\Contract\ContentServiceInterface;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public Category Content domain API. */
final readonly class ContentApi implements ContentApiInterface
{
    public function __construct(private ContentServiceInterface $service) {}

    public function create(CreateCategoryContentCommand $command): int
    {
        return $this->service->create($command);
    }

    public function update(UpdateCategoryContentCommand $command): void
    {
        $this->service->update($command);
    }

    public function updateName(UpdateCategoryContentNameCommand $command): void
    {
        $this->service->updateName($command);
    }

    public function updateDescription(UpdateCategoryContentDescriptionCommand $command): void
    {
        $this->service->updateDescription($command);
    }

    public function softDelete(SoftDeleteCategoryContentCommand $command): void
    {
        $this->service->softDelete($command);
    }

    public function restore(RestoreCategoryContentCommand $command): void
    {
        $this->service->restore($command);
    }

    public function listVisibleForCategory(
        int $categoryId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentCollectionDTO {
        return $this->service->listVisibleForCategory($categoryId, $criteria);
    }

    public function getByIdForManagement(
        int $contentId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryContentDTO {
        return $this->service->getByIdForManagement($contentId, $deletedState);
    }

    public function listForManagement(CategoryContentListCriteriaDTO $criteria): CategoryContentCollectionDTO
    {
        return $this->service->listForManagement($criteria);
    }

    public function paginateForManagement(CategoryContentListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult
    {
        return $this->service->paginateForManagement($criteria, $pageRequest);
    }
}
