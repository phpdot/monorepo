<?php

declare(strict_types=1);

/**
 * A discovery run whose inputs cannot be trusted: a declared scan directory
 * that does not exist (an empty result would orphan the whole catalog and
 * still report a successful sync), or a scanned declaration the scanner could
 * not read back (an attribute it matched but cannot instantiate is a sync
 * error, never a silently dropped permission).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class InvalidScanException extends IamException
{
    /**
     * A scan directory that does not exist.
     *
     * @param string $directory The unusable path
     *
     * @return self
     */
    public static function missingDirectory(string $directory): self
    {
        return new self(sprintf(
            'Scan directory [%s] does not exist — an empty scan would orphan the catalog and report success.',
            $directory,
        ));
    }

    /**
     * A declaration the scanner matched but could not read back.
     *
     * @param string $site The class::constant site
     *
     * @return self
     */
    public static function unreadableDeclaration(string $site): self
    {
        return new self(sprintf(
            'Declaration [%s] matched the scan but could not be read back — a dropped permission is a sync error.',
            $site,
        ));
    }
}
