<?php

declare(strict_types=1);

/**
 * EntityDTO contract — a DTO standing for one stored record.
 *
 * It classifies: `<X>Entity` is one; `<X>Save` is not, because nothing is
 * stored yet, and neither is `<X>Search` or a value composed from several
 * records.
 *
 * It also owns `fromArray()`, and is the only contract that does. An entity is
 * hydrated from a row THIS APPLICATION selected, so narrowing `mixed` off the
 * driver is all that happens — there is no value to judge, because the query
 * decided the shape. Nothing outside a repository should call it.
 *
 * A DTO built from something a caller sent is a FilterDTO or a SaveDTO, and
 * neither has this method, precisely because coercing untrusted input silently
 * is the defect that separation exists to prevent.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\DTO;

interface EntityDTO extends DTO {}
