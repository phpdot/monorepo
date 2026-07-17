<?php

declare(strict_types=1);

/**
 * InstallHook
 *
 * Marks a class as an install-time hook. phpdot/package discovers it during the
 * `post-autoload-dump` scan and invokes its handler after config files are
 * generated — so packages need no Composer script of their own. The class must
 * implement {@see \PHPdot\Package\Contract\InstallHandler}.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class InstallHook {}
