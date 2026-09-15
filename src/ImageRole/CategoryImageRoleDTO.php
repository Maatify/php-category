<?php

declare(strict_types=1);

namespace Maatify\Category\ImageRole;

use DateTimeImmutable;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Package-owned Category Image Role identity and lifecycle state. */
final readonly class CategoryImageRoleDTO implements \JsonSerializable
{
    public const MAX_ROLE_KEY_LENGTH = 100;

    public function __construct(
        public int $id,
        public string $roleKey,
        public CategoryImageRoleStatusEnum $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
        if ($id < 1) {
            throw CategoryInvalidArgumentException::nonPositiveId('id');
        }

        self::assertValidRoleKey($roleKey);
    }

    public static function assertValidRoleKey(string $roleKey): void
    {
        if (trim($roleKey) === '') {
            throw CategoryInvalidArgumentException::emptyField('roleKey');
        }

        if (mb_strlen($roleKey) > self::MAX_ROLE_KEY_LENGTH) {
            throw CategoryInvalidArgumentException::fieldTooLong('roleKey', self::MAX_ROLE_KEY_LENGTH);
        }
    }

    /** @return array{id: int, roleKey: string, status: string, createdAt: string, updatedAt: string, deletedAt: ?string} */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'roleKey' => $this->roleKey,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'deletedAt' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }
}
