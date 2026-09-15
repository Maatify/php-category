<?php

declare(strict_types=1);

namespace Maatify\Category\Content\Query\Infrastructure;

use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\Content\Query\Contract\CategoryContentQueryReaderInterface;
use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** PDO read adapter for Category Content mutation-support lookups. */
final readonly class PdoCategoryContentQueryReader extends PdoReadQuerySupport implements CategoryContentQueryReaderInterface
{
    private const CONTENT_TABLE = 'maa_category_category_contents';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    public function findContentById(int $contentId): ?CategoryContentDTO
    {
        return $this->findContent($contentId, false);
    }

    /**
     * Finds and locks a Category Content regardless of soft-delete state.
     *
     * This lookup MUST execute with a row lock inside the current
     * TransactionRunnerInterface::run() transaction.
     */
    public function findContentByIdForUpdate(int $contentId): ?CategoryContentDTO
    {
        return $this->findContent($contentId, true);
    }

    private function findContent(int $contentId, bool $forUpdate): ?CategoryContentDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `category_id`, `language_code`, `name`, `description`, '
            . '`created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::CONTENT_TABLE . '` '
            . 'WHERE `id` = :id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['id' => $contentId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateContent($row) : null;
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
