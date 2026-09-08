<?php

declare(strict_types=1);

/**
 * Builds the driver for one wire shape.
 *
 * The extension point of the package: the two shipped factories cover the two
 * shapes, and a host with a vendor neither reaches adds a factory of its own and
 * binds {@see DriverFactories} over the default — no change to this package.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Contract;

use PHPdot\Ai\Registry\LlmShape;
use PHPdot\Ai\Registry\ProviderBlock;

interface DriverFactoryInterface
{
    /**
     * Which wire shape this factory builds for.
     *
     * @return LlmShape
     */
    public function shape(): LlmShape;

    /**
     * The driver for one provider block of this shape.
     *
     * @param ProviderBlock $block The provider as configured
     * @param int $timeout Seconds a whole turn may take
     * @param int $maxTokens Ceiling on one answer
     *
     * @return LlmDriver
     */
    public function build(ProviderBlock $block, int $timeout, int $maxTokens): LlmDriver;
}
