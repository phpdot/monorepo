<?php

declare(strict_types=1);

/**
 * Web request context: the peer the request came from and the client that made
 * it. Used by step-up policies ("unusual country → demand a second factor") and
 * by audit logging. Immutable per request.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Request;

use PHPdot\Iam\Identities\Contract\Context\RequestDetailsInterface;

final readonly class WebRequestDetails implements RequestDetailsInterface
{
    /**
     * @param string $ip The client IP (resolved by the framework, post-proxy)
     * @param string $userAgent The User-Agent header, empty when absent
     * @param string $country The resolved country code, empty when unknown
     */
    public function __construct(
        private string $ip,
        private string $userAgent = '',
        private string $country = '',
    ) {}

    public function channel(): string
    {
        return 'web';
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function country(): string
    {
        return $this->country;
    }
}
