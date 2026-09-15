<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Api;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ContentField\Api\Contract\ContentFieldApiInterface;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\Contract\ContentFieldServiceInterface;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Public Category Content Field domain API. */
final readonly class ContentFieldApi implements ContentFieldApiInterface
{
    public function __construct(private ContentFieldServiceInterface $service) {}

    public function create(CreateCategoryContentFieldCommand $command): int
    {
        return $this->service->create($command);
    }

    public function update(UpdateCategoryContentFieldCommand $command): void
    {
        $this->service->update($command);
    }

    public function updateValue(UpdateCategoryContentFieldValueCommand $command): void
    {
        $this->service->updateValue($command);
    }

    public function updateDisplayOrder(UpdateCategoryContentFieldDisplayOrderCommand $command): void
    {
        $this->service->updateDisplayOrder($command);
    }

    public function softDelete(SoftDeleteCategoryContentFieldCommand $command): void
    {
        $this->service->softDelete($command);
    }

    public function restore(RestoreCategoryContentFieldCommand $command): void
    {
        $this->service->restore($command);
    }

    public function listVisibleForCategory(
        int $categoryId,
        CategoryContentFieldScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentFieldCollectionDTO {
        return $this->service->listVisibleForCategory($categoryId, $scope, $criteria);
    }

    public function getByIdForManagement(
        int $fieldId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryContentFieldDTO {
        return $this->service->getByIdForManagement($fieldId, $deletedState);
    }

    public function listForManagement(CategoryContentFieldListCriteriaDTO $criteria): CategoryContentFieldCollectionDTO
    {
        return $this->service->listForManagement($criteria);
    }

    public function paginateForManagement(
        CategoryContentFieldListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->service->paginateForManagement($criteria, $pageRequest);
    }
}
