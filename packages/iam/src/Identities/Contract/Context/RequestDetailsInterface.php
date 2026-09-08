<?php

declare(strict_types=1);

/**
 * Per-channel request context attached to an identity: where the request came
 * from (web vs cli) and the attributes a policy may need (ip, user agent,
 * command, …). The concrete shape is channel-specific; this is the common seam.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Contract\Context;

interface RequestDetailsInterface
{
    /**
     * The request channel — 'web' or 'cli'.
     *
     * @return string
     */
    public function channel(): string;
}
