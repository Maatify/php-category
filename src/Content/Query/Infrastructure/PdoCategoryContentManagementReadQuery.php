<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\Infrastructure;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\Content\Query\Contract\CategoryContentManagementReadQueryInterface;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** Dedicated PDO adapter for Category Content management reads. */
final readonly class PdoCategoryContentManagementReadQuery extends PdoReadQuerySupport implements CategoryContentManagementReadQueryInterface
{
    private const CONTENT_TABLE = 'maa_category_category_contents';

    private PdoPaginator $paginator;

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
        $this->paginator = new PdoPaginator();
    }

    /** Finds a Content using the requested explicit soft-deletion state. */
    public function findContentById(
        int $contentId,
        CategoryDeletedStateEnum $deletedState,
    ): ?CategoryContentDTO {
        $where = ['`id` = :content_id'];
        $params = ['content_id' => $contentId];
        $this->appendDeletedStateFilter($where, $params, $deletedState, 'content');

        $statement = $this->pdo->prepare(
            $this->contentSelect() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
        );
        $statement->execute($params);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateContent($row) : null;
    }

    /** Lists Contents in language-code/id order, bounded by the criteria. */
    public function listContents(CategoryContentListCriteriaDTO $criteria): CategoryContentCollectionDTO
    {
        $where = [];
        /** @var array<string, int|string> $params */
        $params = [];
        if ($criteria->categoryId !== null) {
            $where[] = '`category_id` = :content_category_id';
            $params['content_category_id'] = $criteria->categoryId;
        }
        $this->appendDeletedStateFilter($where, $params, $criteria->deletedState, 'content');

        $statement = $this->pdo->prepare(
            $this->contentSelect()
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY `language_code` ASC, `id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, $params, $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateContent($row);
        }

        /** @var list<CategoryContentDTO> $items */
        return new CategoryContentCollectionDTO($items);
    }

    /** @return PageResult<CategoryContentDTO> */
    public function paginateContents(CategoryContentListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult
    {
        $where = [];
        /** @var array<string, int|string> $params */
        $params = [];
        $this->appendCriteria($where, $params, $criteria);
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) ' . $this->contentFrom() . $whereSql,
            totalParams: $params,
            filteredCountSql: 'SELECT COUNT(*) ' . $this->contentFrom() . $whereSql,
            filteredCountParams: $params,
            dataSql: $this->contentSelect() . $whereSql,
            dataParams: $params,
        );

        return $this->paginator->paginate(
            $this->pdo,
            $descriptor,
            $pageRequest,
            new PaginationConfig(
                sortWhitelist: new SortWhitelist([
                    'language_code' => 'content.language_code',
                    'category_id' => 'content.category_id',
                    'id' => 'content.id',
                    'created_at' => 'content.created_at',
                ]),
                defaultSortBy: 'language_code',
                defaultSortDirection: SortDirectionEnum::ASC,
                tieBreakerSortBy: 'id',
                tieBreakerDirection: SortDirectionEnum::ASC,
                defaultPerPage: 20,
                minPerPage: 1,
                maxPerPage: 100,
            ),
            fn (array $row): CategoryContentDTO => $this->hydrateContent($row),
        );
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendCriteria(
        array &$where,
        array &$params,
        CategoryContentListCriteriaDTO $criteria,
    ): void {
        if ($criteria->categoryId !== null) {
            $where[] = '`content`.`category_id` = :content_category_id';
            $params['content_category_id'] = $criteria->categoryId;
        }
        $this->appendDeletedStateFilter($where, $params, $criteria->deletedState, 'content');
    }

    private function contentFrom(): string
    {
        return 'FROM `' . self::CONTENT_TABLE . '` AS `content`';
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendDeletedStateFilter(
        array &$where,
        array &$params,
        CategoryDeletedStateEnum $deletedState,
        string $tableAlias,
    ): void {
        if ($deletedState === CategoryDeletedStateEnum::NON_DELETED) {
            $where[] = sprintf('`%s`.`deleted_at` IS NULL', $tableAlias);
        } elseif ($deletedState === CategoryDeletedStateEnum::DELETED_ONLY) {
            $where[] = sprintf('`%s`.`deleted_at` IS NOT NULL', $tableAlias);
        }
    }

    private function contentSelect(): string
    {
        return 'SELECT `id`, `category_id`, `language_code`, `name`, `description`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CONTENT_TABLE . '` AS `content`';
    }

    /** @param array<string, mixed> $row */
    private function hydrateContent(array $row): CategoryContentDTO
    {
        return new CategoryContentDTO(
            id: $this->integerValue($row, 'id'),
            categoryId: $this->integerValue($row, 'category_id'),
            languageCode: $this->nullableStringValue($row, 'language_code'),
            name: $this->stringValue($row, 'name'),
            description: $this->nullableStringValue($row, 'description'),
            createdAt: $this->timestampValue($row, 'created_at', $this->clock),
            updatedAt: $this->timestampValue($row, 'updated_at', $this->clock),
            deletedAt: $this->nullableTimestampValue($row, 'deleted_at', $this->clock),
        );
    }
}
