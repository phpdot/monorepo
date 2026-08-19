<?php

declare(strict_types=1);

/**
 * Encodes and decodes opaque pagination cursors for cursor-based pagination.
 *
 * A cursor is the base64 of a canonical extended JSON document `{"v": value}`,
 * so BSON types survive the round trip — an ObjectId comes back as an ObjectId,
 * a UTCDateTime as a UTCDateTime — where a plain string cast would silently
 * match nothing against typed fields.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Pagination;

use MongoDB\BSON\Document as BSONDocument;
use MongoDB\BSON\Int64;
use PHPdot\MongoDB\Exception\InvalidCursorException;

final class CursorCodec
{
    /**
     * Encode a field value into an opaque cursor string.
     *
     * @param mixed $value Raw BSON field value from the last item of a page
     *
     * @return string
     */
    public static function encode(mixed $value): string
    {
        return base64_encode(BSONDocument::fromPHP(['v' => $value])->toCanonicalExtendedJSON());
    }

    /**
     * Decode an opaque cursor string back into its typed field value.
     *
     * @param string $cursor Cursor produced by encode()
     *
     * @throws InvalidCursorException If the cursor is not a valid encoded value
     *
     * @return mixed
     */
    public static function decode(string $cursor): mixed
    {
        $json = base64_decode($cursor, true);

        if ($json === false) {
            throw new InvalidCursorException('Malformed pagination cursor: not base64');
        }

        try {
            $decoded = BSONDocument::fromJSON($json)->toPHP();
        } catch (\Throwable $e) {
            throw new InvalidCursorException('Malformed pagination cursor: ' . $e->getMessage(), 0, $e);
        }

        $values = is_object($decoded) ? get_object_vars($decoded) : $decoded;

        if (!array_key_exists('v', $values)) {
            throw new InvalidCursorException('Malformed pagination cursor: missing value');
        }

        $value = $values['v'];

        return $value instanceof Int64 ? (int) (string) $value : $value;
    }
}
