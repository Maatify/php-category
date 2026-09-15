<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Service;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Content\Contract\CategoryContentCommandRepositoryInterface;
use Maatify\Category\Content\Contract\ContentServiceInterface;
use Maatify\Category\Content\Exception\CategoryContentNotFoundException;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\Content\Query\Contract\CategoryContentManagementReadQueryInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentQueryReaderInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentReadQueryInterface;
use Maatify\Category\Exception\CategoryNotFoundException;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/** Owns Category Content lifecycle orchestration only. */
final readonly class ContentService implements ContentServiceInterface
{
    public function __construct(
        private CategoryContentCommandRepositoryInterface $commandRepository,
        private CategoryQueryReaderInterface $categoryQueryReader,
        private CategoryContentQueryReaderInterface $queryReader,
        private CategoryContentReadQueryInterface $visibleReader,
        private CategoryContentManagementReadQueryInterface $managementReader,
        private TransactionRunnerInterface $transaction,
        private ClockInterface $clock,
    ) {}

    public function create(CreateCategoryContentCommand $command): int
    {
        return $this->transaction->run(function () use ($command): int {
            $this->requireActiveCategoryForUpdate($command->categoryId);

            return $this->commandRepository->create($command, $this->clock->now());
        });
    }

    public function update(UpdateCategoryContentCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveContentForUpdate($command->contentId);

            if (!$this->commandRepository->update($command, $this->clock->now())) {
                throw CategoryContentNotFoundException::withId($command->contentId);
            }
        });
    }

    public function updateName(UpdateCategoryContentNameCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $content = $this->requireActiveContentForUpdate($command->contentId);
            $updated = $this->commandRepository->update(
                new UpdateCategoryContentCommand($content->id, $command->name, $content->description),
                $this->clock->now(),
            );

            if (!$updated) {
                throw CategoryContentNotFoundException::withId($command->contentId);
            }
        });
    }

    public function updateDescription(UpdateCategoryContentDescriptionCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $content = $this->requireActiveContentForUpdate($command->contentId);
            $updated = $this->commandRepository->update(
                new UpdateCategoryContentCommand($content->id, $content->name, $command->description),
                $this->clock->now(),
            );

            if (!$updated) {
                throw CategoryContentNotFoundException::withId($command->contentId);
            }
        });
    }

    public function softDelete(SoftDeleteCategoryContentCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireActiveContentForUpdate($command->contentId);

            if (!$this->commandRepository->softDelete($command, $this->clock->now())) {
                throw CategoryContentNotFoundException::withId($command->contentId);
            }
        });
    }

    public function restore(RestoreCategoryContentCommand $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->requireContentForUpdate($command->contentId);

            if (!$this->commandRepository->restore($command, $this->clock->now())) {
                throw CategoryContentNotFoundException::withId($command->contentId);
            }
        });
    }

    public function listVisibleForCategory(
        int $categoryId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentCollectionDTO {
        $id = (new CategoryIdDTO($categoryId, 'categoryId'))->value;

        return $this->visibleReader->listVisibleContents($id, $criteria);
    }

    public function getByIdForManagement(
        int $contentId,
        CategoryDeletedStateEnum $deletedState = CategoryDeletedStateEnum::NON_DELETED,
    ): CategoryContentDTO {
        $id = (new CategoryIdDTO($contentId, 'contentId'))->value;
        $content = $this->managementReader->findContentById($id, $deletedState);

        if ($content === null) {
            throw CategoryContentNotFoundException::withId($id);
        }

        return $content;
    }

    public function listForManagement(CategoryContentListCriteriaDTO $criteria): CategoryContentCollectionDTO
    {
        return $this->managementReader->listContents($criteria);
    }

    public function paginateForManagement(
        CategoryContentListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->managementReader->paginateContents($criteria, $pageRequest);
    }

    private function requireActiveCategoryForUpdate(int $categoryId): void
    {
        if ($this->categoryQueryReader->findActiveByIdForUpdate($categoryId) === null) {
            throw CategoryNotFoundException::withId($categoryId);
        }
    }

    private function requireActiveContentForUpdate(int $contentId): CategoryContentDTO
    {
        $content = $this->requireContentForUpdate($contentId);

        if ($content->deletedAt !== null) {
            throw CategoryContentNotFoundException::withId($contentId);
        }

        return $content;
    }

    private function requireContentForUpdate(int $contentId): CategoryContentDTO
    {
        $content = $this->queryReader->findContentByIdForUpdate($contentId);

        if ($content === null) {
            throw CategoryContentNotFoundException::withId($contentId);
        }

        return $content;
    }
}
