<?php

declare(strict_types=1);

/**
 * SaveDTO contract — a DTO carrying a create or update intent.
 *
 * It classifies rather than constrains. It is the write counterpart of
 * FilterDTO, and unlike EntityDTO nothing it describes is stored yet — a
 * uniqueness or state rule may still reject it. Audit reads this
 * classification to know a change was attempted.
 *
 * Construction rule: a named constructor that VALIDATES and REJECTS. It has no
 * `fromArray()`, and the reason is sharper here than anywhere else — coercion
 * turns a missing required value into a default, so `To::string(null)` would
 * store a user with an empty email rather than failing. A FilterDTO degrades
 * because a wrong question deserves a sane answer; a SaveDTO must not, because
 * a wrong write deserves an error.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\DTO;

interface SaveDTO extends DTO {}
