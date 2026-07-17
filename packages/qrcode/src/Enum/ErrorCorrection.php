<?php

declare(strict_types=1);

/**
 * Error-correction level — how much of the symbol can be recovered if damaged.
 *
 * Higher levels survive more damage at the cost of a denser symbol (fewer data
 * codewords per version, so larger or more versions for the same payload).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Enum;

enum ErrorCorrection: string
{
    case Low = 'L';
    case Medium = 'M';
    case Quartile = 'Q';
    case High = 'H';

    /**
     * Approximate fraction of the symbol that can be restored at this level.
     *
     * @return float
     */
    public function recoveryRate(): float
    {
        return match ($this) {
            self::Low => 0.07,
            self::Medium => 0.15,
            self::Quartile => 0.25,
            self::High => 0.30,
        };
    }
}
