<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment\Infrastructure;

use DateTimeImmutable;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageAssignment\Contract\CategoryImageAssignmentCommandRepositoryInterface;
use Maatify\Category\ImageAssignment\Assignment\Exception\CategoryImageAssignmentAlreadyExistsException;
use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use PDO;
use PDOException;

/** PDO write adapter for Category-owned Image Assignment references. */
final readonly class PdoCategoryImageAssignmentCommandRepository implements CategoryImageAssignmentCommandRepositoryInterface
{
    private const ASSIGNMENT_TABLE = 'maa_category_category_image_assignments';

    public function __construct(
        private PDO $pdo,
        private ScopedOrderingManager $orderingManager,
    ) {}

    public function create(CreateCategoryImageAssignmentCommand $command, DateTimeImmutable $occurredAt): int
    {
        $orderingScope = $this->orderingScope(
            $command->categoryId,
            $command->languageCode,
            $command->platform,
            $command->roleId,
        );
        $this->lockCreationScope($orderingScope);
        $displayOrder = $this->orderingManager->getNextPosition(
            $this->pdo,
            $this->orderingConfig(),
            $orderingScope,
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::ASSIGNMENT_TABLE . '` '
            . '(`category_id`, `media_asset_id`, `language_code`, `platform`, '
            . '`role_id`, '
            . '`display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:category_id, :media_asset_id, :language_code, :platform, '
            . ':role_id, '
            . ':display_order, :created_at, :updated_at, NULL)',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        try {
            $statement->execute([
                'category_id' => $command->categoryId,
                'media_asset_id' => $command->mediaAssetId,
                'language_code' => $command->languageCode,
                'platform' => $command->platform,
                'role_id' => $command->roleId,
                'display_order' => $displayOrder,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (PDOException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ((is_int($driverCode) || is_string($driverCode)) && (int) $driverCode === 1062) {
                throw CategoryImageAssignmentAlreadyExistsException::withIdentity(
                    $command->categoryId,
                    $command->mediaAssetId,
                    $command->languageCode,
                    $command->platform,
                    $exception,
                    $command->roleId,
                );
            }

            throw $exception;
        }

        $id = $this->pdo->lastInsertId();
        if ($id === false || !ctype_digit($id) || (int) $id < 1) {
            throw CategoryPersistenceException::invalidImageAssignmentAutoIncrementIdentity();
        }

        return (int) $id;
    }

    public function updateDisplayOrder(
        UpdateCategoryImageAssignmentDisplayOrderCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $orderingScope = $this->activeOrderingScope($command->assignmentId);
        if ($orderingScope === false) {
            return false;
        }

        return $this->orderingManager->moveWithinScope(
            $this->pdo,
            $this->orderingConfig(),
            $orderingScope,
            $command->assignmentId,
            $command->displayOrder,
            $this->formatTimestamp($occurredAt),
        );
    }

    public function setDefault(
        SetCategoryImageAssignmentDefaultCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $orderingScope = $this->activeOrderingScope($command->assignmentId);
        if ($orderingScope === false) {
            return false;
        }

        $this->lockDefaultScope($orderingScope);
        if (!$this->lockActiveAssignmentInScope($command->assignmentId, $orderingScope)) {
            return false;
        }

        $timestamp = $this->formatTimestamp($occurredAt);
        $clearStatement = $this->pdo->prepare(
            'UPDATE `' . self::ASSIGNMENT_TABLE . '` '
            . 'SET `is_default` = :clear_default, `updated_at` = :clear_updated_at '
            . 'WHERE `ordering_scope` = :clear_scope AND `deleted_at` IS NULL '
            . 'AND `is_default` = 1',
        );
        $clearStatement->execute([
            'clear_default' => 0,
            'clear_updated_at' => $timestamp,
            'clear_scope' => $orderingScope,
        ]);

        $setStatement = $this->pdo->prepare(
            'UPDATE `' . self::ASSIGNMENT_TABLE . '` '
            . 'SET `is_default` = :set_default, `updated_at` = :set_updated_at '
            . 'WHERE `id` = :set_id AND `deleted_at` IS NULL',
        );
        $setStatement->execute([
            'set_default' => 1,
            'set_updated_at' => $timestamp,
            'set_id' => $command->assignmentId,
        ]);

        return true;
    }

    public function clearDefault(
        ClearCategoryImageAssignmentDefaultCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $orderingScope = $this->activeOrderingScope($command->assignmentId);
        if ($orderingScope === false) {
            return false;
        }

        $this->lockDefaultScope($orderingScope);
        if (!$this->lockActiveAssignmentInScope($command->assignmentId, $orderingScope)) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE `' . self::ASSIGNMENT_TABLE . '` '
            . 'SET `is_default` = :clear_default, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL AND `is_default` = 1',
        );
        $statement->execute([
            'clear_default' => 0,
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->assignmentId,
        ]);

        return true;
    }

    public function softDelete(
        SoftDeleteCategoryImageAssignmentCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::ASSIGNMENT_TABLE . '` '
            . 'SET `deleted_at` = :deleted_at, `is_default` = :is_default, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        $statement->execute([
            'deleted_at' => $timestamp,
            'is_default' => 0,
            'updated_at' => $timestamp,
            'id' => $command->assignmentId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function restore(
        RestoreCategoryImageAssignmentCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::ASSIGNMENT_TABLE . '` '
            . 'SET `deleted_at` = NULL, `is_default` = :is_default, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NOT NULL',
        );
        $statement->execute([
            'updated_at' => $this->formatTimestamp($occurredAt),
            'is_default' => 0,
            'id' => $command->assignmentId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function activeOrderingScope(int $assignmentId): string|false
    {
        $statement = $this->pdo->prepare(
            'SELECT `ordering_scope` FROM `' . self::ASSIGNMENT_TABLE . '` '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1',
        );
        $statement->execute(['id' => $assignmentId]);
        $value = $statement->fetchColumn();

        if ($value === false) {
            return false;
        }
        if (!is_string($value)) {
            throw CategoryPersistenceException::unexpectedColumnType('ordering_scope');
        }

        return $value;
    }

    private function lockDefaultScope(string $orderingScope): void
    {
        $statement = $this->pdo->prepare(
            'SELECT `id` FROM `' . self::ASSIGNMENT_TABLE . '` '
            . 'WHERE `ordering_scope` = :ordering_scope FOR UPDATE',
        );
        $statement->execute(['ordering_scope' => $orderingScope]);
    }

    private function lockActiveAssignmentInScope(int $assignmentId, string $orderingScope): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT `ordering_scope` FROM `' . self::ASSIGNMENT_TABLE . '` '
            . 'WHERE `id` = :assignment_id AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['assignment_id' => $assignmentId]);
        $value = $statement->fetchColumn();

        if ($value === false) {
            return false;
        }
        if (!is_string($value)) {
            throw CategoryPersistenceException::unexpectedColumnType('ordering_scope');
        }

        return $value === $orderingScope;
    }

    /** The service owns the transaction around this lock and insert. */
    private function lockCreationScope(string $orderingScope): void
    {
        $statement = $this->pdo->prepare(
            'SELECT `id` FROM `' . self::ASSIGNMENT_TABLE . '` '
            . 'WHERE `ordering_scope` = :ordering_scope FOR UPDATE',
        );
        $statement->execute(['ordering_scope' => $orderingScope]);
    }

    private function orderingConfig(): ScopedOrderingConfig
    {
        return new ScopedOrderingConfig(
            table: self::ASSIGNMENT_TABLE,
            scopeColumn: 'ordering_scope',
            idColumn: 'id',
            orderColumn: 'display_order',
            deletedAtColumn: 'deleted_at',
            nullableScope: false,
            updatedAtColumn: 'updated_at',
        );
    }

    private function orderingScope(
        int $categoryId,
        ?string $languageCode,
        ?string $platform,
        ?int $roleId,
    ): string
    {
        $language = $languageCode === null
            ? 'N:'
            : 'L' . mb_strlen($languageCode) . ':' . $languageCode;
        $platformValue = $platform === null
            ? 'N:'
            : 'L' . mb_strlen($platform) . ':' . $platform;

        $role = $roleId === null ? 'N:' : 'R' . $roleId;

        return 'C' . $categoryId . '|' . $language . '|' . $platformValue . '|' . $role;
    }

    private function formatTimestamp(DateTimeImmutable $occurredAt): string
    {
        return $occurredAt->format('Y-m-d H:i:s');
    }
}
