<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole\Query\Infrastructure;

use Maatify\Category\Common\Exception\CategoryPersistenceException;
use Maatify\Category\Common\Infrastructure\PdoReadQuerySupport;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/** PDO read adapter for Image Role mutation-support lookups. */
final readonly class PdoCategoryImageRoleQueryReader extends PdoReadQuerySupport implements CategoryImageRoleQueryReaderInterface
{
    private const IMAGE_ROLE_TABLE = 'maa_category_category_image_roles';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

    public function findImageRoleByIdForUpdate(int $roleId): ?CategoryImageRoleDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `role_key`, `status`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::IMAGE_ROLE_TABLE . '` '
            . 'WHERE `id` = :id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['id' => $roleId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateRole($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrateRole(array $row): CategoryImageRoleDTO
    {
        $status = $this->stringValue($row, 'status');
        try {
            $roleStatus = CategoryImageRoleStatusEnum::from($status);
        } catch (\ValueError $exception) {
            throw CategoryPersistenceException::invalidStorageValue('status', $exception);
        }

        return new CategoryImageRoleDTO(
            id: $this->integerValue($row, 'id'),
            roleKey: $this->stringValue($row, 'role_key'),
            status: $roleStatus,
            createdAt: $this->timestampValue($row, 'created_at', $this->clock),
            updatedAt: $this->timestampValue($row, 'updated_at', $this->clock),
            deletedAt: $this->nullableTimestampValue($row, 'deleted_at', $this->clock),
        );
    }
}
