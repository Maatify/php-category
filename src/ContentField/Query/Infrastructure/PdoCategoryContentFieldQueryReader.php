<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Query\Infrastructure;

use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldQueryReaderInterface;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** PDO read adapter for Content Field mutation-support lookups. */
final readonly class PdoCategoryContentFieldQueryReader extends PdoReadQuerySupport implements CategoryContentFieldQueryReaderInterface
{
    private const CONTENT_FIELD_TABLE = 'maa_category_category_content_fields';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    public function findContentFieldById(int $fieldId): ?CategoryContentFieldDTO
    {
        return $this->findField($fieldId, false);
    }

    /** Finds and locks a Category Content Field regardless of soft-delete state. */
    public function findContentFieldByIdForUpdate(int $fieldId): ?CategoryContentFieldDTO
    {
        return $this->findField($fieldId, true);
    }

    private function findField(int $fieldId, bool $forUpdate): ?CategoryContentFieldDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `category_id`, `field_key`, `language_code`, `platform`, `format`, `value`, '
            . '`display_order`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CONTENT_FIELD_TABLE . '` '
            . 'WHERE `id` = :id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['id' => $fieldId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateField($row) : null;
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
