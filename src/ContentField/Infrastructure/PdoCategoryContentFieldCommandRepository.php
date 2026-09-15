<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField\Infrastructure;

use DateTimeImmutable;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ContentField\Contract\CategoryContentFieldCommandRepositoryInterface;
use Maatify\Category\ContentField\Mutation\Exception\CategoryContentFieldAlreadyExistsException;
use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use PDO;
use PDOException;

/** PDO write adapter for Host-defined Category Content Fields. */
final readonly class PdoCategoryContentFieldCommandRepository implements CategoryContentFieldCommandRepositoryInterface
{
    private const FIELD_TABLE = 'maa_category_category_content_fields';

    public function __construct(
        private PDO $pdo,
        private ScopedOrderingManager $orderingManager,
    ) {}

    public function create(CreateCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): int
    {
        $orderingScope = $this->orderingScope(
            $command->categoryId,
            $command->languageCode,
            $command->platform,
        );
        $this->lockCreationScope($orderingScope);
        $displayOrder = $this->orderingManager->getNextPosition(
            $this->pdo,
            $this->orderingConfig(),
            $orderingScope,
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::FIELD_TABLE . '` '
            . '(`category_id`, `field_key`, `language_code`, `platform`, `format`, `value`, '
            . '`display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:category_id, :field_key, :language_code, :platform, :format, :value, '
            . ':display_order, :created_at, :updated_at, NULL)',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        try {
            $statement->execute([
                'category_id' => $command->categoryId,
                'field_key' => $command->fieldKey,
                'language_code' => $command->languageCode,
                'platform' => $command->platform,
                'format' => $command->format->value,
                'value' => $command->value,
                'display_order' => $displayOrder,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (PDOException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ((is_int($driverCode) || is_string($driverCode)) && (int) $driverCode === 1062) {
                throw CategoryContentFieldAlreadyExistsException::withIdentity(
                    $command->categoryId,
                    $command->fieldKey,
                    $command->languageCode,
                    $command->platform,
                    $exception,
                );
            }

            throw $exception;
        }

        $id = $this->pdo->lastInsertId();
        if ($id === false || !ctype_digit($id) || (int) $id < 1) {
            throw CategoryPersistenceException::invalidContentFieldAutoIncrementIdentity();
        }

        return (int) $id;
    }

    public function update(UpdateCategoryContentFieldCommand $command, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::FIELD_TABLE . '` '
            . 'SET `format` = :format, `value` = :value, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $statement->execute([
            'format' => $command->format->value,
            'value' => $command->value,
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->fieldId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function updateDisplayOrder(
        UpdateCategoryContentFieldDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $orderingScope = $this->activeOrderingScope($command->fieldId);
        if ($orderingScope === false) {
            return false;
        }

        return $this->orderingManager->moveWithinScope(
            $this->pdo,
            $this->orderingConfig(),
            $orderingScope,
            $command->fieldId,
            $command->displayOrder,
            $this->formatTimestamp($occurredAt),
        );
    }

    public function softDelete(
        SoftDeleteCategoryContentFieldCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::FIELD_TABLE . '` '
            . 'SET `deleted_at` = :deleted_at, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        $statement->execute([
            'deleted_at' => $timestamp,
            'updated_at' => $timestamp,
            'id' => $command->fieldId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function restore(
        RestoreCategoryContentFieldCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::FIELD_TABLE . '` '
            . 'SET `deleted_at` = NULL, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NOT NULL',
        );
        $statement->execute([
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->fieldId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function activeOrderingScope(int $fieldId): string|false
    {
        $statement = $this->pdo->prepare(
            'SELECT `ordering_scope` FROM `' . self::FIELD_TABLE . '` '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1',
        );
        $statement->execute(['id' => $fieldId]);
        $value = $statement->fetchColumn();

        if ($value === false) {
            return false;
        }
        if (!is_string($value)) {
            throw CategoryPersistenceException::unexpectedColumnType('ordering_scope');
        }

        return $value;
    }

    /** The service owns the transaction around this lock and insert. */
    private function lockCreationScope(string $orderingScope): void
    {
        $statement = $this->pdo->prepare(
            'SELECT `id` FROM `' . self::FIELD_TABLE . '` '
            . 'WHERE `ordering_scope` = :ordering_scope FOR UPDATE',
        );
        $statement->execute(['ordering_scope' => $orderingScope]);
    }

    private function orderingConfig(): ScopedOrderingConfig
    {
        return new ScopedOrderingConfig(
            table: self::FIELD_TABLE,
            scopeColumn: 'ordering_scope',
            idColumn: 'id',
            orderColumn: 'display_order',
            deletedAtColumn: 'deleted_at',
            nullableScope: false,
            updatedAtColumn: 'updated_at',
        );
    }

    private function orderingScope(int $categoryId, ?string $languageCode, ?string $platform): string
    {
        $language = $languageCode === null
            ? 'N:'
            : 'L' . mb_strlen($languageCode) . ':' . $languageCode;
        $platformValue = $platform === null
            ? 'N:'
            : 'L' . mb_strlen($platform) . ':' . $platform;

        return 'C' . $categoryId . '|' . $language . '|' . $platformValue;
    }

    private function formatTimestamp(DateTimeImmutable $occurredAt): string
    {
        return $occurredAt->format('Y-m-d H:i:s');
    }
}
