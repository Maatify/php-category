<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Infrastructure;

use DateTimeImmutable;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageRole\Contract\CategoryImageRoleCommandRepositoryInterface;
use Maatify\Category\ImageRole\Lifecycle\Exception\CategoryImageRoleAlreadyExistsException;
use Maatify\Category\Common\Exception\CategoryPersistenceException;
use PDO;
use PDOException;

/** PDO write adapter for the package-owned Category Image Role registry. */
final readonly class PdoCategoryImageRoleCommandRepository implements CategoryImageRoleCommandRepositoryInterface
{
    private const ROLE_TABLE = 'maa_category_category_image_roles';

    public function __construct(private PDO $pdo) {}

    public function create(CreateCategoryImageRoleCommand $command, DateTimeImmutable $occurredAt): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::ROLE_TABLE . '` '
            . '(`role_key`, `status`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:role_key, :status, :created_at, :updated_at, NULL)',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        try {
            $statement->execute([
                'role_key' => $command->roleKey,
                'status' => $command->status->value,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (PDOException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ((is_int($driverCode) || is_string($driverCode)) && (int) $driverCode === 1062) {
                throw CategoryImageRoleAlreadyExistsException::withKey($command->roleKey, $exception);
            }

            throw $exception;
        }

        $id = $this->pdo->lastInsertId();
        if ($id === false || !ctype_digit($id) || (int) $id < 1) {
            throw CategoryPersistenceException::invalidImageRoleAutoIncrementIdentity();
        }

        return (int) $id;
    }

    public function updateStatus(
        UpdateCategoryImageRoleStatusCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::ROLE_TABLE . '` '
            . 'SET `status` = :status, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $statement->execute([
            'status' => $command->status->value,
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->roleId,
        ]);

        if ($statement->rowCount() > 0) {
            return true;
        }

        // MySQL reports zero changed rows when the requested status already
        // matches the stored status. The row was locked by the application
        // service, so a no-op update is still a successful status operation.
        $exists = $this->pdo->prepare(
            'SELECT 1 FROM `' . self::ROLE_TABLE . '` '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1',
        );
        $exists->execute(['id' => $command->roleId]);

        return $exists->fetchColumn() !== false;
    }

    public function softDelete(
        SoftDeleteCategoryImageRoleCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::ROLE_TABLE . '` '
            . 'SET `deleted_at` = :deleted_at, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $timestamp = $this->formatTimestamp($occurredAt);
        $statement->execute([
            'deleted_at' => $timestamp,
            'updated_at' => $timestamp,
            'id' => $command->roleId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function restore(
        RestoreCategoryImageRoleCommand $command,
        DateTimeImmutable $occurredAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::ROLE_TABLE . '` '
            . 'SET `deleted_at` = NULL, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `deleted_at` IS NOT NULL',
        );
        $statement->execute([
            'updated_at' => $this->formatTimestamp($occurredAt),
            'id' => $command->roleId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function formatTimestamp(DateTimeImmutable $occurredAt): string
    {
        return $occurredAt->format('Y-m-d H:i:s');
    }
}
