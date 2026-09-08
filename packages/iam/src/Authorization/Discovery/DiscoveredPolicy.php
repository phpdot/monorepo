<?php

declare(strict_types=1);

/**
 * One inventoried policy: the class and the resource type its __invoke is
 * natively typed to. Pure scan output — dispatch never touches this; it
 * exists so the admin surface can SHOW what rules the installation has.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Discovery;

final readonly class DiscoveredPolicy
{
    /**
     * @param string $policy The policy class
     * @param string $resource The IamResource subtype it judges
     */
    public function __construct(
        public string $policy,
        public string $resource,
    ) {}
}
