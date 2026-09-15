<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Query\Infrastructure;

use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldReadQueryInterface;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldCollectionDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** Dedicated PDO adapter for visible Content Field query behavior. */
final readonly class PdoCategoryContentFieldReadQuery extends PdoReadQuerySupport implements CategoryContentFieldReadQueryInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';
    private const CONTENT_FIELD_TABLE = 'maa_category_category_content_fields';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    /** Lists fields for the exact requested scope, with no fallback. */
    public function listVisibleContentFields(
        int $categoryId,
        CategoryContentFieldScopeDTO $scope,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentFieldCollectionDTO {
        $scopeWhere = [];
        $scopeParams = [];
        if ($scope->languageCode === null) {
            $scopeWhere[] = '`field`.`language_code` IS NULL';
        } else {
            $scopeWhere[] = '`field`.`language_code` = :field_language_code';
            $scopeParams['field_language_code'] = $scope->languageCode;
        }
        if ($scope->platform === null) {
            $scopeWhere[] = '`field`.`platform` IS NULL';
        } else {
            $scopeWhere[] = '`field`.`platform` = :field_platform';
            $scopeParams['field_platform'] = $scope->platform;
        }

        $statement = $this->pdo->prepare(
            'WITH RECURSIVE `category_ancestors` AS ('
            . 'SELECT `id`, `parent_id`, `status`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `id` = :field_ancestor_start_id '
            . 'UNION ALL '
            . 'SELECT `parent`.`id`, `parent`.`parent_id`, `parent`.`status`, `parent`.`deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `parent` '
            . 'INNER JOIN `category_ancestors` AS `child` '
            . 'ON `child`.`parent_id` = `parent`.`id`'
            . ') '
            . 'SELECT `field`.`id`, `field`.`category_id`, `field`.`field_key`, '
            . '`field`.`language_code`, `field`.`platform`, `field`.`format`, `field`.`value`, '
            . '`field`.`display_order`, `field`.`created_at`, `field`.`updated_at`, `field`.`deleted_at` '
            . 'FROM `' . self::CONTENT_FIELD_TABLE . '` AS `field` '
            . 'WHERE `field`.`category_id` = :field_category_id '
            . 'AND `field`.`deleted_at` IS NULL '
            . 'AND EXISTS (SELECT 1 FROM `category_ancestors` AS `visible_category` '
            . 'WHERE `visible_category`.`id` = :visible_field_category_id) '
            . 'AND NOT EXISTS (SELECT 1 FROM `category_ancestors` AS `ancestor` '
            . 'WHERE `ancestor`.`status` <> \'active\' OR `ancestor`.`deleted_at` IS NOT NULL) '
            . 'AND ' . implode(' AND ', $scopeWhere)
            . ' ORDER BY `field`.`display_order` ASC, `field`.`id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, array_merge([
            'field_ancestor_start_id' => $categoryId,
            'field_category_id' => $categoryId,
            'visible_field_category_id' => $categoryId,
        ], $scopeParams), $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateField($row);
        }

        /** @var list<CategoryContentFieldDTO> $items */
        return new CategoryContentFieldCollectionDTO($items);
    }

    /** @param array<string, mixed> $row */
    private function hydrateField(array $row): CategoryContentFieldDTO
    {
        $format = $this->stringValue($row, 'format');
        try {
            $fieldFormat = CategoryContentFieldFormatEnum::from($format);
        } catch (\ValueError $exception) {
            throw CategoryPersistenceException::invalidStorageValue('format', $exception);
        }

        return new CategoryContentFieldDTO(
            id: $this->integerValue($row, 'id'),
            categoryId: $this->integerValue($row, 'category_id'),
            fieldKey: $this->stringValue($row, 'field_key'),
            languageCode: $this->nullableStringValue($row, 'language_code'),
            platform: $this->nullableStringValue($row, 'platform'),
            format: $fieldFormat,
            value: $this->stringValue($row, 'value'),
            displayOrder: $this->integerValue($row, 'display_order'),
            createdAt: $this->timestampValue($row, 'created_at', $this->clock),
            updatedAt: $this->timestampValue($row, 'updated_at', $this->clock),
            deletedAt: $this->nullableTimestampValue($row, 'deleted_at', $this->clock),
        );
    }
}
