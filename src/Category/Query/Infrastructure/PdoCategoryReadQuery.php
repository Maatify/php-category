<?php

declare(strict_types=1);

namespace Maatify\Category\Query\Infrastructure;

use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Query\Contract\CategoryReadQueryInterface;
use Maatify\Category\Query\DTO\CategoryCollectionDTO;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** Dedicated PDO adapter for visible Category query behavior. */
final readonly class PdoCategoryReadQuery extends PdoReadQuerySupport implements CategoryReadQueryInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    public function findVisibleById(int $categoryId): ?CategoryDTO
    {
        $statement = $this->pdo->prepare(
            'WITH RECURSIVE `category_ancestors` AS ('
            . 'SELECT `id`, `parent_id`, `status`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `id` = :ancestor_category_id '
            . 'UNION ALL '
            . 'SELECT `parent`.`id`, `parent`.`parent_id`, `parent`.`status`, `parent`.`deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `parent` '
            . 'INNER JOIN `category_ancestors` AS `child` '
            . 'ON `child`.`parent_id` = `parent`.`id`'
            . ') '
            . 'SELECT `id`, `parent_id`, `code`, `status`, `display_order`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `category` '
            . 'WHERE `category`.`id` = :visible_category_id '
            . 'AND `category`.`status` = \'active\' '
            . 'AND `category`.`deleted_at` IS NULL '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM `category_ancestors` AS `ancestor` '
            . 'WHERE `ancestor`.`status` <> \'active\' '
            . 'OR `ancestor`.`deleted_at` IS NOT NULL'
            . ') LIMIT 1',
        );
        $statement->execute([
            'ancestor_category_id' => $categoryId,
            'visible_category_id' => $categoryId,
        ]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    public function listVisibleRootCategories(
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `parent_id`, `code`, `status`, `display_order`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `parent_id` IS NULL AND `status` = \'active\' '
            . 'AND `deleted_at` IS NULL '
            . 'ORDER BY `display_order` ASC, `id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, [], $criteria->maxResults);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $this->hydrateCategories($rows);
    }

    public function listVisibleChildren(
        int $parentId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryCollectionDTO {
        $statement = $this->pdo->prepare(
            'WITH RECURSIVE `category_ancestors` AS ('
            . 'SELECT `id`, `parent_id`, `status`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `id` = :ancestor_start_id '
            . 'UNION ALL '
            . 'SELECT `parent`.`id`, `parent`.`parent_id`, `parent`.`status`, `parent`.`deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `parent` '
            . 'INNER JOIN `category_ancestors` AS `child` '
            . 'ON `child`.`parent_id` = `parent`.`id`'
            . ') '
            . 'SELECT `child`.`id`, `child`.`parent_id`, `child`.`code`, `child`.`status`, '
            . '`child`.`display_order`, `child`.`created_at`, `child`.`updated_at`, `child`.`deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `child` '
            . 'WHERE `child`.`parent_id` = :children_parent_id '
            . 'AND `child`.`status` = \'active\' AND `child`.`deleted_at` IS NULL '
            . 'AND EXISTS (SELECT 1 FROM `category_ancestors` AS `requested_parent` '
            . 'WHERE `requested_parent`.`id` = :requested_parent_id) '
            . 'AND NOT EXISTS (SELECT 1 FROM `category_ancestors` AS `ancestor` '
            . 'WHERE `ancestor`.`status` <> \'active\' OR `ancestor`.`deleted_at` IS NOT NULL) '
            . 'ORDER BY `child`.`display_order` ASC, `child`.`id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, [
            'ancestor_start_id' => $parentId,
            'children_parent_id' => $parentId,
            'requested_parent_id' => $parentId,
        ], $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $this->hydrateCategories($rows);
    }

    /** @param list<array<string, mixed>> $rows */
    private function hydrateCategories(array $rows): CategoryCollectionDTO
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateCategory($row);
        }

        /** @var list<CategoryDTO> $items */
        return new CategoryCollectionDTO($items);
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
