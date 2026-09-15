<?php

declare(strict_types=1);

namespace Maatify\Category\Common\DTO;

use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;

/**
 * Normalized positive Category-domain identity.
 *
 * The string form is accepted for boundary inputs so canonical validation can
 * reject signs, whitespace, leading zeroes, decimal notation, and overflow
 * before an integer cast occurs.
 */
final readonly class CategoryIdDTO implements \JsonSerializable
{
    public int $value;

    public function __construct(int|string $value, string $field = 'id')
    {
        if (is_int($value)) {
            if ($value < 1) {
                throw CategoryInvalidArgumentException::invalidId($field);
            }

            $this->value = $value;

            return;
        }

        if (preg_match('/^[1-9][0-9]*\\z/D', $value) !== 1) {
            throw CategoryInvalidArgumentException::invalidId($field);
        }

        $maximum = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
            throw CategoryInvalidArgumentException::invalidId($field);
        }

        $this->value = (int) $value;
    }

    /** @return array{value: int} */
    public function jsonSerialize(): mixed
    {
        return ['value' => $this->value];
    }
}
