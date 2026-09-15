<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Query\Infrastructure;

use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentQueryReaderInterface;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** PDO read adapter for Image Assignment mutation-support lookups. */
final readonly class PdoCategoryImageAssignmentQueryReader extends PdoReadQuerySupport implements CategoryImageAssignmentQueryReaderInterface
{
    private const IMAGE_ASSIGNMENT_TABLE = 'maa_category_category_image_assignments';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    public function findImageAssignmentById(int $assignmentId): ?CategoryImageAssignmentDTO
    {
        return $this->findAssignment($assignmentId, false);
    }

    /** Finds and locks an Image Assignment regardless of soft-delete state. */
    public function findImageAssignmentByIdForUpdate(int $assignmentId): ?CategoryImageAssignmentDTO
    {
        return $this->findAssignment($assignmentId, true);
    }

    private function findAssignment(int $assignmentId, bool $forUpdate): ?CategoryImageAssignmentDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `category_id`, `media_asset_id`, `role_id`, `language_code`, `platform`, '
            . '`is_default`, `display_order`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::IMAGE_ASSIGNMENT_TABLE . '` '
            . 'WHERE `id` = :id LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['id' => $assignmentId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateAssignment($row) : null;
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
