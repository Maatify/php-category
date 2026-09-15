<?php

declare(strict_types=1);

namespace Maatify\Category\Query\Infrastructure;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Query\Contract\CategoryManagementReadQueryInterface;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** PDO adapter for bounded management reads without consumer visibility rules. */
final readonly class PdoCategoryManagementReadQuery extends PdoReadQuerySupport implements CategoryManagementReadQueryInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';

    private PdoPaginator $paginator;

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
        $this->paginator = new PdoPaginator();
    }

    /** Finds a Category using the requested explicit soft-deletion state. */
    public function findById(int $categoryId, CategoryDeletedStateEnum $deletedState): ?CategoryDTO
    {
        $where = ['`id` = :category_id'];
        $params = ['category_id' => $categoryId];
        $this->appendDeletedStateFilter($where, $params, $deletedState, 'category');

        $statement = $this->pdo->prepare(
            $this->categorySelect() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
        );
        $statement->execute($params);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    /** Finds a Category by its exact code using the requested explicit soft-deletion state. */
    public function findByCode(string $code, CategoryDeletedStateEnum $deletedState): ?CategoryDTO
    {
        $where = ['`category`.`code` = :category_code'];
        $params = ['category_code' => $code];
        $this->appendDeletedStateFilter($where, $params, $deletedState, 'category');

        $statement = $this->pdo->prepare(
            $this->categorySelect() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
        );
        $statement->execute($params);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    /** Lists Categories in display-order/id order, bounded by the criteria. */
    public function listCategories(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->listCategoriesWithWhere($criteria, []);
    }

    /** Lists root Categories in display-order/id order, bounded by the criteria. */
    public function listRootCategories(CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->listCategoriesWithWhere($criteria, ['`parent_id` IS NULL']);
    }

    /** Lists direct children in display-order/id order, bounded by the criteria. */
    public function listChildren(int $parentId, CategoryListCriteriaDTO $criteria): CategoryCollectionDTO
    {
        return $this->listCategoriesWithWhere($criteria, ['`parent_id` = :parent_id'], ['parent_id' => $parentId]);
    }

    /** @return PageResult<CategoryDTO> */
    public function paginateCategories(CategoryListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult
    {
        return $this->paginateCategoriesWithWhere($criteria, [], [], $pageRequest);
    }

    /** @return PageResult<CategoryDTO> */
    public function paginateRootCategories(CategoryListCriteriaDTO $criteria, PageRequest $pageRequest): PageResult
    {
        return $this->paginateCategoriesWithWhere($criteria, ['`parent_id` IS NULL'], [], $pageRequest);
    }

    /** @return PageResult<CategoryDTO> */
    public function paginateChildren(
        int $parentId,
        CategoryListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        return $this->paginateCategoriesWithWhere(
            $criteria,
            ['`parent_id` = :parent_id'],
            ['parent_id' => $parentId],
            $pageRequest,
        );
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function listCategoriesWithWhere(
        CategoryListCriteriaDTO $criteria,
        array $where,
        array $params = [],
    ): CategoryCollectionDTO {
        $this->appendCategoryCriteria($where, $params, $criteria, true);

        $statement = $this->pdo->prepare(
            $this->categorySelect()
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY `display_order` ASC, `id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, $params, $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateCategory($row);
        }

        /** @var list<CategoryDTO> $items */
        return new CategoryCollectionDTO($items);
    }

    /**
     * @param list<string> $scopeWhere
     * @param array<string, int|string> $scopeParams
     * @return PageResult<CategoryDTO>
     */
    private function paginateCategoriesWithWhere(
        CategoryListCriteriaDTO $criteria,
        array $scopeWhere,
        array $scopeParams,
        PageRequest $pageRequest,
    ): PageResult {
        $baseWhere = $scopeWhere;
        $baseParams = $scopeParams;
        $this->appendCategoryCriteria($baseWhere, $baseParams, $criteria, false);

        $filteredWhere = $baseWhere;
        $filteredParams = $baseParams;
        $this->appendCategorySearch($filteredWhere, $filteredParams, $criteria);

        $baseWhereSql = $this->whereClause($baseWhere);
        $filteredWhereSql = $this->whereClause($filteredWhere);
        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) ' . $this->categoryFrom() . $baseWhereSql,
            totalParams: $baseParams,
            filteredCountSql: 'SELECT COUNT(*) ' . $this->categoryFrom() . $filteredWhereSql,
            filteredCountParams: $filteredParams,
            dataSql: $this->categorySelect() . $filteredWhereSql,
            dataParams: $filteredParams,
        );

        return $this->paginator->paginate(
            $this->pdo,
            $descriptor,
            $pageRequest,
            $this->paginationConfig(),
            fn (array $row): CategoryDTO => $this->hydrateCategory($row),
        );
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendCategoryCriteria(
        array &$where,
        array &$params,
        CategoryListCriteriaDTO $criteria,
        bool $includeSearch,
    ): void {
        if ($criteria->status !== null) {
            $where[] = '`category`.`status` = :category_status';
            $params['category_status'] = $criteria->status->value;
        }
        $this->appendDeletedStateFilter($where, $params, $criteria->deletedState, 'category');
        if ($includeSearch) {
            $this->appendCategorySearch($where, $params, $criteria);
        }
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendCategorySearch(
        array &$where,
        array &$params,
        CategoryListCriteriaDTO $criteria,
    ): void {
        if ($criteria->search === null) {
            return;
        }

        $where[] = "`category`.`code` LIKE :category_search ESCAPE '\\\\'";
        $search = trim($criteria->search);
        $search = strtr($search, [
            '\\' => '\\\\',
            '%' => '\\%',
            '_' => '\\_',
        ]);
        $params['category_search'] = '%' . $search . '%';
    }

    /** @param list<string> $where */
    private function whereClause(array $where): string
    {
        return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    }

    private function categoryFrom(): string
    {
        return 'FROM `' . self::CATEGORY_TABLE . '` AS `category`';
    }

    private function paginationConfig(): PaginationConfig
    {
        return new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'display_order' => 'category.display_order',
                'code' => 'category.code',
                'id' => 'category.id',
                'created_at' => 'category.created_at',
            ]),
            defaultSortBy: 'display_order',
            defaultSortDirection: SortDirectionEnum::ASC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::ASC,
            defaultPerPage: 20,
            minPerPage: 1,
            maxPerPage: 100,
        );
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

    private function categorySelect(): string
    {
        return 'SELECT `id`, `parent_id`, `code`, `status`, `display_order`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `category`';
    }

    /** @param array<string, mixed> $row */
    private function hydrateCategory(array $row): CategoryDTO
    {
        $status = $row['status'] ?? null;
        if (!is_string($status)) {
            throw CategoryPersistenceException::unexpectedColumnType('status');
        }
        $parentId = $row['parent_id'] ?? null;
        if ($parentId !== null && !is_int($parentId) && !is_string($parentId)) {
            throw CategoryPersistenceException::unexpectedColumnType('parent_id');
        }
        try {
            $categoryStatus = CategoryStatusEnum::from($status);
        } catch (\ValueError $exception) {
            throw CategoryPersistenceException::invalidStorageValue('status', $exception);
        }

        return new CategoryDTO(
            id: $this->integerValue($row, 'id'),
            parentId: $parentId === null ? null : (int) $parentId,
            code: $this->stringValue($row, 'code'),
            status: $categoryStatus,
            displayOrder: $this->integerValue($row, 'display_order'),
            createdAt: $this->timestampValue($row, 'created_at', $this->clock),
            updatedAt: $this->timestampValue($row, 'updated_at', $this->clock),
            deletedAt: $this->nullableTimestampValue($row, 'deleted_at', $this->clock),
        );
    }
}
