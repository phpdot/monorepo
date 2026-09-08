<?php

declare(strict_types=1);

/**
 * The base every policy resource extends — a deliberately empty, immutable
 * marker. Its whole job is the type constraint on AuthorizerInterface::can(): policies
 * receive typed readonly data objects, never entities, models, request
 * arrays, or scalars. Helpers arrive only when one is earned.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

abstract readonly class IamResource {}
