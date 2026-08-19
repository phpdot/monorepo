<?php

declare(strict_types=1);

/**
 * DTO contract — the marker every DTO role extends.
 *
 * Lives in `phpdot/contracts` so a consumer (a transport, an audit recorder)
 * can accept any DTO without knowing which package declared it.
 *
 * It declares NO methods, and that is the contract: a DTO is typed public
 * properties behind a constructor, and nothing else. The wire shape IS the
 * property list — json_encode() reads the public properties and a template
 * reads them by name, so there is no toArray() to drift from them, and
 * whoever built the DTO has already made every value carry-ready.
 *
 * Deliberately does NOT declare how one is built either. A DTO is built from
 * a different source at every boundary, and those sources do not fail the
 * same way: a row this application selected cannot be invalid, a query string
 * must degrade, a write body must be rejected. One `fromArray()` here would
 * force all three through the coercion the first one wants — which silently
 * turns a missing email into `''` on the boundary that needed an error. The
 * builder of each DTO owns its construction rule instead.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\DTO;

interface DTO {}
