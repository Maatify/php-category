<?php

declare(strict_types=1);

namespace Maatify\Category\ImageAssignment;

use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Exact Category Image Assignment scope; null dimensions are meaningful. */
final readonly class CategoryImageAssignmentScopeDTO implements \JsonSerializable
{
    public ?int $roleId;

    public function __construct(
        public ?string $languageCode = null,
        public ?string $platform = null,
        int|string|null $roleId = null,
    ) {
        if ($languageCode !== null && trim($languageCode) === '') {
            throw CategoryInvalidArgumentException::emptyField('languageCode');
        }

        if ($languageCode !== null && mb_strlen($languageCode) > 16) {
            throw CategoryInvalidArgumentException::fieldTooLong('languageCode', 16);
        }

        if ($platform !== null && trim($platform) === '') {
            throw CategoryInvalidArgumentException::emptyField('platform');
        }

        if ($platform !== null && mb_strlen($platform) > 255) {
            throw CategoryInvalidArgumentException::fieldTooLong('platform', 255);
        }

        $this->roleId = $roleId === null
            ? null
            : (new CategoryIdDTO($roleId, 'roleId'))->value;
    }

    /** @return array{languageCode: ?string, platform: ?string, roleId: ?int} */
    public function jsonSerialize(): mixed
    {
        return [
            'languageCode' => $this->languageCode,
            'platform' => $this->platform,
            'roleId' => $this->roleId,
        ];
    }
}
