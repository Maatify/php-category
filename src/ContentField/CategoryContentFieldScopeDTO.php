<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField;

use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Exact language/platform scope for a Category Content Field. */
final readonly class CategoryContentFieldScopeDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $languageCode = null,
        public ?string $platform = null,
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
    }

    /** @return array{languageCode: ?string, platform: ?string} */
    public function jsonSerialize(): mixed
    {
        return [
            'languageCode' => $this->languageCode,
            'platform' => $this->platform,
        ];
    }
}
