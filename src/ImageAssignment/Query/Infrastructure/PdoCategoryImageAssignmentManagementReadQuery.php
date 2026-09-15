<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\Infrastructure;

use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentManagementReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\Enum\CategoryImageAssignmentRoleFilterModeEnum;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** Dedicated PDO adapter for Image Assignment management reads. */
final readonly class PdoCategoryImageAssignmentManagementReadQuery extends PdoReadQuerySupport implements CategoryImageAssignmentManagementReadQueryInterface
{
    private const IMAGE_ASSIGNMENT_TABLE = 'maa_category_category_image_assignments';

    private PdoPaginator $paginator;

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
        $this->paginator = new PdoPaginator();
    }

    public function findImageAssignmentById(
        int $assignmentId,
        CategoryDeletedStateEnum $deletedState,
    ): ?CategoryImageAssignmentDTO {
        $where = ['`id` = :assignment_id'];
        $params = ['assignment_id' => $assignmentId];
        $this->appendDeletedStateFilter($where, $params, $deletedState, 'assignment');

        $statement = $this->pdo->prepare(
            $this->imageAssignmentSelect() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
        );
        $statement->execute($params);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateAssignment($row) : null;
    }

    public function listImageAssignments(
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): CategoryImageAssignmentCollectionDTO {
        $where = [];
        /** @var array<string, int|string> $params */
        $params = [];
        if ($criteria->categoryId !== null) {
            $where[] = '`assignment`.`category_id` = :image_category_id';
            $params['image_category_id'] = $criteria->categoryId;
        }
        $this->appendDeletedStateFilter($where, $params, $criteria->deletedState, 'assignment');

        if ($criteria->scope !== null) {
            if ($criteria->scope->languageCode === null) {
                $where[] = '`assignment`.`language_code` IS NULL';
            } else {
                $where[] = '`assignment`.`language_code` = :image_language_code';
                $params['image_language_code'] = $criteria->scope->languageCode;
            }
            if ($criteria->scope->platform === null) {
                $where[] = '`assignment`.`platform` IS NULL';
            } else {
                $where[] = '`assignment`.`platform` = :image_platform';
                $params['image_platform'] = $criteria->scope->platform;
            }
        }

        switch ($criteria->roleFilter->mode) {
            case CategoryImageAssignmentRoleFilterModeEnum::OMITTED:
                break;
            case CategoryImageAssignmentRoleFilterModeEnum::EXACT_NULL:
                $where[] = '`assignment`.`role_id` IS NULL';
                break;
            case CategoryImageAssignmentRoleFilterModeEnum::CONCRETE:
                $roleId = $criteria->roleFilter->roleId;
                if ($roleId === null) {
                    throw CategoryInvalidArgumentException::invalidId('roleId');
                }
                $where[] = '`assignment`.`role_id` = :image_role_id';
                $params['image_role_id'] = $roleId;
                break;
        }

        $statement = $this->pdo->prepare(
            $this->imageAssignmentSelect()
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY `assignment`.`category_id` ASC, `assignment`.`ordering_scope` ASC, '
            . '`assignment`.`display_order` ASC, `assignment`.`id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, $params, $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateAssignment($row);
        }

        /** @var list<CategoryImageAssignmentDTO> $items */
        return new CategoryImageAssignmentCollectionDTO($items);
    }

    /** @return PageResult<CategoryImageAssignmentDTO> */
    public function paginateImageAssignments(
        CategoryImageAssignmentListCriteriaDTO $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        $where = [];
        /** @var array<string, int|string> $params */
        $params = [];
        $this->appendCriteria($where, $params, $criteria);
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) ' . $this->imageAssignmentFrom() . $whereSql,
            totalParams: $params,
            filteredCountSql: 'SELECT COUNT(*) ' . $this->imageAssignmentFrom() . $whereSql,
            filteredCountParams: $params,
            dataSql: $this->imageAssignmentPaginationSelect() . $whereSql,
            dataParams: $params,
        );

        return $this->paginator->paginate(
            $this->pdo,
            $descriptor,
            $pageRequest,
            new PaginationConfig(
                sortWhitelist: new SortWhitelist([
                    'business_order' => 'management_order',
                    'category_id' => 'assignment.category_id',
                    'display_order' => 'assignment.display_order',
                    'media_asset_id' => 'assignment.media_asset_id',
                    'id' => 'assignment.id',
                    'created_at' => 'assignment.created_at',
                ]),
                defaultSortBy: 'business_order',
                defaultSortDirection: SortDirectionEnum::ASC,
                tieBreakerSortBy: 'id',
                tieBreakerDirection: SortDirectionEnum::ASC,
                defaultPerPage: 20,
                minPerPage: 1,
                maxPerPage: 100,
            ),
            fn (array $row): CategoryImageAssignmentDTO => $this->hydrateAssignment($row),
        );
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendCriteria(
        array &$where,
        array &$params,
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): void {
        if ($criteria->categoryId !== null) {
            $where[] = '`assignment`.`category_id` = :image_category_id';
            $params['image_category_id'] = $criteria->categoryId;
        }
        $this->appendDeletedStateFilter($where, $params, $criteria->deletedState, 'assignment');
        $this->appendScopeFilter($where, $params, $criteria);
        $this->appendRoleFilter($where, $params, $criteria);
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendScopeFilter(
        array &$where,
        array &$params,
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): void {
        if ($criteria->scope === null) {
            return;
        }
        if ($criteria->scope->languageCode === null) {
            $where[] = '`assignment`.`language_code` IS NULL';
        } else {
            $where[] = '`assignment`.`language_code` = :image_language_code';
            $params['image_language_code'] = $criteria->scope->languageCode;
        }
        if ($criteria->scope->platform === null) {
            $where[] = '`assignment`.`platform` IS NULL';
        } else {
            $where[] = '`assignment`.`platform` = :image_platform';
            $params['image_platform'] = $criteria->scope->platform;
        }
    }

    /**
     * @param list<string> $where
     * @param array<string, int|string> $params
     */
    private function appendRoleFilter(
        array &$where,
        array &$params,
        CategoryImageAssignmentListCriteriaDTO $criteria,
    ): void {
        switch ($criteria->roleFilter->mode) {
            case CategoryImageAssignmentRoleFilterModeEnum::OMITTED:
                return;
            case CategoryImageAssignmentRoleFilterModeEnum::EXACT_NULL:
                $where[] = '`assignment`.`role_id` IS NULL';
                return;
            case CategoryImageAssignmentRoleFilterModeEnum::CONCRETE:
                $roleId = $criteria->roleFilter->roleId;
                if ($roleId === null) {
                    throw CategoryInvalidArgumentException::invalidId('roleId');
                }
                $where[] = '`assignment`.`role_id` = :image_role_id';
                $params['image_role_id'] = $roleId;
                return;
        }
    }

    private function imageAssignmentFrom(): string
    {
        return 'FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` AS `assignment`';
    }

    private function imageAssignmentPaginationSelect(): string
    {
        // The paginator's single default key is backed by the complete four-column business ordering.
        return 'SELECT `assignment`.`id`, `assignment`.`category_id`, '
            . '`assignment`.`media_asset_id`, `assignment`.`role_id`, '
            . '`assignment`.`language_code`, `assignment`.`platform`, '
            . '`assignment`.`is_default`, `assignment`.`display_order`, '
            . '`assignment`.`created_at`, `assignment`.`updated_at`, '
            . '`assignment`.`deleted_at`, `assignment`.`ordering_scope`, '
            . 'ROW_NUMBER() OVER (ORDER BY `assignment`.`category_id` ASC, '
            . '`assignment`.`ordering_scope` ASC, `assignment`.`display_order` ASC, '
            . '`assignment`.`id` ASC) AS `management_order` '
            . 'FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` AS `assignment`';
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

    private function imageAssignmentSelect(): string
    {
        return 'SELECT `assignment`.`id`, `assignment`.`category_id`, '
            . '`assignment`.`media_asset_id`, `assignment`.`role_id`, '
            . '`assignment`.`language_code`, `assignment`.`platform`, '
            . '`assignment`.`is_default`, `assignment`.`display_order`, '
            . '`assignment`.`created_at`, `assignment`.`updated_at`, '
            . '`assignment`.`deleted_at`, `assignment`.`ordering_scope` '
            . 'FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` AS `assignment`';
    }

    /** @param array<string, mixed> $row */
    private function hydrateAssignment(array $row): CategoryImageAssignmentDTO
    {
        return new CategoryImageAssignmentDTO(
            id: $this->integerValue($row, 'id'),
            categoryId: $this->integerValue($row, 'category_id'),
            mediaAssetId: $this->integerValue($row, 'media_asset_id'),
            roleId: $this->nullableIntegerValue($row, 'role_id'),
            languageCode: $this->nullableStringValue($row, 'language_code'),
            platform: $this->nullableStringValue($row, 'platform'),
            isDefault: $this->booleanValue($row, 'is_default'),
            displayOrder: $this->integerValue($row, 'display_order'),
            createdAt: $this->timestampValue($row, 'created_at', $this->clock),
            updatedAt: $this->timestampValue($row, 'updated_at', $this->clock),
            deletedAt: $this->nullableTimestampValue($row, 'deleted_at', $this->clock),
        );
    }
}
