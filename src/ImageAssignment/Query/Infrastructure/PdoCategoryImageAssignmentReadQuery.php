<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\Infrastructure;

use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** Dedicated PDO adapter for visible Image Assignment query behavior. */
final readonly class PdoCategoryImageAssignmentReadQuery extends PdoReadQuerySupport implements CategoryImageAssignmentReadQueryInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';
    private const IMAGE_ASSIGNMENT_TABLE = 'maa_category_category_image_assignments';
    private const IMAGE_ROLE_TABLE = 'maa_category_category_image_roles';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    /** Lists assignments for the exact requested scope, with no fallback. */
    public function listVisibleImageAssignments(
        int $categoryId,
        CategoryImageAssignmentScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryImageAssignmentCollectionDTO {
        $scopeWhere = [];
        $scopeParams = [];
        if ($scope->languageCode === null) {
            $scopeWhere[] = '`assignment`.`language_code` IS NULL';
        } else {
            $scopeWhere[] = '`assignment`.`language_code` = :image_language_code';
            $scopeParams['image_language_code'] = $scope->languageCode;
        }
        if ($scope->platform === null) {
            $scopeWhere[] = '`assignment`.`platform` IS NULL';
        } else {
            $scopeWhere[] = '`assignment`.`platform` = :image_platform';
            $scopeParams['image_platform'] = $scope->platform;
        }
        if ($scope->roleId === null) {
            $scopeWhere[] = '`assignment`.`role_id` IS NULL';
        } else {
            $scopeWhere[] = '`assignment`.`role_id` = :image_role_id';
            $scopeParams['image_role_id'] = $scope->roleId;
        }

        $statement = $this->pdo->prepare(
            'WITH RECURSIVE `category_ancestors` AS ('
            . 'SELECT `id`, `parent_id`, `status`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `id` = :image_ancestor_start_id '
            . 'UNION ALL '
            . 'SELECT `parent`.`id`, `parent`.`parent_id`, `parent`.`status`, `parent`.`deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `parent` '
            . 'INNER JOIN `category_ancestors` AS `child` '
            . 'ON `child`.`parent_id` = `parent`.`id`'
            . ') '
            . 'SELECT `assignment`.`id`, `assignment`.`category_id`, '
            . '`assignment`.`media_asset_id`, `assignment`.`role_id`, '
            . '`assignment`.`language_code`, `assignment`.`platform`, '
            . '`assignment`.`is_default`, `assignment`.`display_order`, '
            . '`assignment`.`created_at`, `assignment`.`updated_at`, `assignment`.`deleted_at` '
            . 'FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` AS `assignment` '
            . 'WHERE `assignment`.`category_id` = :image_category_id '
            . 'AND `assignment`.`deleted_at` IS NULL '
            . 'AND EXISTS (SELECT 1 FROM `category_ancestors` AS `visible_category` '
            . 'WHERE `visible_category`.`id` = :image_visible_category_id) '
            . 'AND NOT EXISTS (SELECT 1 FROM `category_ancestors` AS `ancestor` '
            . 'WHERE `ancestor`.`status` <> \'active\' OR `ancestor`.`deleted_at` IS NOT NULL) '
            . 'AND (`assignment`.`role_id` IS NULL OR EXISTS (SELECT 1 FROM `'
            . self::IMAGE_ROLE_TABLE . '` AS `role` WHERE `role`.`id` = `assignment`.`role_id` '
            . 'AND `role`.`status` = \'active\' AND `role`.`deleted_at` IS NULL)) '
            . 'AND ' . implode(' AND ', $scopeWhere)
            . ' ORDER BY `assignment`.`display_order` ASC, `assignment`.`id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, array_merge([
            'image_ancestor_start_id' => $categoryId,
            'image_category_id' => $categoryId,
            'image_visible_category_id' => $categoryId,
        ], $scopeParams), $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateAssignment($row);
        }

        /** @var list<CategoryImageAssignmentDTO> $items */
        return new CategoryImageAssignmentCollectionDTO($items);
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
