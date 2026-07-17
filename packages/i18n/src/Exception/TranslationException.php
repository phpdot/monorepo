<?php

declare(strict_types=1);

/**
 * Base exception for translation errors raised by the i18n package.
 *
 * Reserved for failures that consumers can meaningfully catch and react to.
 * Routine misses (a key not present in any catalog) are not exceptions —
 * `Translator::translate()` returns `[key]` and tracks them via
 * `getMissing()` instead.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n\Exception;

final class TranslationException extends \RuntimeException {}
