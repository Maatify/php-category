<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\Infrastructure;

use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\Content\Query\Contract\CategoryContentReadQueryInterface;
use Maatify\Category\Content\Query\DTO\CategoryContentCollectionDTO;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** Dedicated PDO adapter for visible Category Content query behavior. */
final readonly class PdoCategoryContentReadQuery extends PdoReadQuerySupport implements CategoryContentReadQueryInterface
{
    private const CATEGORY_TABLE = 'maa_category_categories';
    private const CONTENT_TABLE = 'maa_category_category_contents';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    /**
     * Lists non-deleted contents for a visible Category in language-code
     * order. The Package validates the syntactic/storage contract; the Host
     * validates semantic language support and owns fallback/locale policy.
     */
    public function listVisibleContents(
        int $categoryId,
        CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO(),
    ): CategoryContentCollectionDTO {
        $statement = $this->pdo->prepare(
            'WITH RECURSIVE `category_ancestors` AS ('
            . 'SELECT `id`, `parent_id`, `status`, `deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` '
            . 'WHERE `id` = :content_ancestor_start_id '
            . 'UNION ALL '
            . 'SELECT `parent`.`id`, `parent`.`parent_id`, `parent`.`status`, `parent`.`deleted_at` '
            . 'FROM `' . self::CATEGORY_TABLE . '` AS `parent` '
            . 'INNER JOIN `category_ancestors` AS `child` '
            . 'ON `child`.`parent_id` = `parent`.`id`'
            . ') '
            . 'SELECT `content`.`id`, `content`.`category_id`, '
            . '`content`.`language_code`, `content`.`name`, `content`.`description`, '
            . '`content`.`created_at`, `content`.`updated_at`, `content`.`deleted_at` '
            . 'FROM `' . self::CONTENT_TABLE . '` AS `content` '
            . 'WHERE `content`.`category_id` = :content_category_id '
            . 'AND `content`.`deleted_at` IS NULL '
            . 'AND EXISTS (SELECT 1 FROM `category_ancestors` AS `visible_category` '
            . 'WHERE `visible_category`.`id` = :visible_content_category_id) '
            . 'AND NOT EXISTS (SELECT 1 FROM `category_ancestors` AS `ancestor` '
            . 'WHERE `ancestor`.`status` <> \'active\' OR `ancestor`.`deleted_at` IS NOT NULL) '
            . 'ORDER BY `content`.`language_code` ASC, `content`.`id` ASC LIMIT :max_results',
        );
        $this->executeBounded($statement, [
            'content_ancestor_start_id' => $categoryId,
            'content_category_id' => $categoryId,
            'visible_content_category_id' => $categoryId,
        ], $criteria->maxResults);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateContent($row);
        }

        /** @var list<CategoryContentDTO> $items */
        return new CategoryContentCollectionDTO($items);
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
