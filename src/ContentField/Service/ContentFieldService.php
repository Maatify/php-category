<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Service;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\Contract\CategoryContentFieldCommandRepositoryInterface;
use Maatify\Category\ContentField\Contract\ContentFieldServiceInterface;
use Maatify\Category\ContentField\Exception\CategoryContentFieldNotFoundException;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldManagementReadQueryInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldQueryReaderInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldReadQueryInterface;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\SharedCommon\Contracts\ClockInterface;

/** Owns Category Content Field lifecycle and exact-scope ordering only. */
final readonly class ContentFieldService implements ContentFieldServiceInterface
{
    public function __construct(
        private CategoryContentFieldCommandRepositoryInterface $commandRepository,
        private CategoryQueryReaderInterface $categoryQueryReader,
        private CategoryContentFieldQueryReaderInterface $queryReader,
        private CategoryContentFieldReadQueryInterface $visibleReader,
        private CategoryContentFieldManagementReadQueryInterface $managementReader,
        private TransactionRunnerInterface $transaction,
        private ClockInterface $clock,
    ) {}

    public function create(CreateCategoryContentFieldCommand $command): int
    {
        return $this->transaction->run(function () use ($command): int {
            $this->requireActiveCategoryForUpdate($command->categoryId);

            return $this->commandRepository->create($command, $this->clock->now());
        });
    }

    public function update(UpdateCategoryContentFieldCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveFieldForUpdate($command->fieldId);

            if (!$this->commandRepository->update($command, $this->clock->now())) {
                throw CategoryContentFieldNotFoundException::withId($command->fieldId);
            }
        });
    }

    public function updateValue(UpdateCategoryContentFieldValueCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $field = $this->requireActiveFieldForUpdate($command->fieldId);
            $updated = $this->commandRepository->update(
                new UpdateCategoryContentFieldCommand($field->id, $field->format, $command->value),
                $this->clock->now(),
            );

            if (!$updated) {
                throw CategoryContentFieldNotFoundException::withId($command->fieldId);
            }
        });
    }

    public function updateDisplayOrder(UpdateCategoryContentFieldDisplayOrderCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveFieldForUpdate($command->fieldId);

            if (!$this->commandRepository->updateDisplayOrder($command, $this->clock->now())) {
                throw CategoryContentFieldNotFoundException::withId($command->fieldId);
            }
        });
    }

    public function softDelete(SoftDeleteCategoryContentFieldCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveFieldForUpdate($command->fieldId);

            if (!$this->commandRepository->softDelete($command, $this->clock->now())) {
                throw CategoryContentFieldNotFoundException::withId($command->fieldId);
            }
        });
    }

    public function restore(RestoreCategoryContentFieldCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireFieldForUpdate($command->fieldId);

            if (!$this->commandRepository->restore($command, $this->clock->now())) {
                throw CategoryContentFieldNotFoundException::withId($command->fieldId);
            }
        });
    }

    public function listVisibleForCategory(
        int $categoryId,
        CategoryContentFieldScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentFieldCollectionDTO {
        $id = (new CategoryIdDTO($categoryId, 'categoryId'))->value;

        return $this->visibleReader->listVisibleContentFields($id, $scope, $criteria);
    }

    public function getByIdForManagement(
        int $fieldId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryContentFieldDTO {
        $id = (new CategoryIdDTO($fieldId, 'fieldId'))->value;
        $field = $this->managementReader->findContentFieldById($id, $deletedState);

        if ($field === null) {
            throw CategoryContentFieldNotFoundException::withId($id);
        }

        return $field;
    }

    public function listForManagement(CategoryContentFieldListCriteriaDTO $criteria): CategoryContentFieldCollectionDTO
    {
        return $this->managementReader->listContentFields($criteria);
    }

    public function paginateForManagement(
        CategoryContentFieldListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->managementReader->paginateContentFields($criteria, $pageRequest);
    }

    private function requireActiveCategoryForUpdate(int $categoryId): void
    {
        if ($this->categoryQueryReader->findActiveByIdForUpdate($categoryId) === null) {
            throw CategoryNotFoundException::withId($categoryId);
        }
    }

    private function requireActiveFieldForUpdate(int $fieldId): CategoryContentFieldDTO
    {
        $field = $this->requireFieldForUpdate($fieldId);

        if ($field->deletedAt !== null) {
            throw CategoryContentFieldNotFoundException::withId($fieldId);
        }

        return $field;
    }

    private function requireFieldForUpdate(int $fieldId): CategoryContentFieldDTO
    {
        $field = $this->queryReader->findContentFieldByIdForUpdate($fieldId);

        if ($field === null) {
            throw CategoryContentFieldNotFoundException::withId($fieldId);
        }

        return $field;
    }
}
