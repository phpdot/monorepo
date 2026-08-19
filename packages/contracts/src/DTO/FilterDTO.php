<?php

declare(strict_types=1);

/**
 * FilterDTO contract — a DTO describing what to SELECT, never what to change.
 *
 * Carries match, sort and page together: a page number is part of the question
 * being asked. A write input is not a filter — it changes state; a result is
 * not — the filter is the question, the result is the answer.
 *
 * Construction rule: a named constructor that WHITELISTS and clamps, and that
 * DEGRADES rather than rejecting — an unrecognised sort key becomes no sort, a
 * page of "nonsense" becomes page 1. It has no `fromArray()`: the array it
 * would take is the untrusted one. Failing here would mean a stale bookmark
 * returning an error instead of a sane page, which no screen wants.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\DTO;

interface FilterDTO extends DTO {}
