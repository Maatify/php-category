<?php

declare(strict_types=1);

namespace Maatify\Category\ContentField;

use JsonException;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/** Storage format of a Host-defined Category Content Field value. */
enum CategoryContentFieldFormatEnum: string
{
    case TEXT = 'text';
    case HTML = 'html';
    case JSON = 'json';

    public function assertValue(string $value): void
    {
        if ($this !== self::JSON) {
            return;
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw CategoryInvalidArgumentException::invalidJsonValue($exception);
        }
    }
}
