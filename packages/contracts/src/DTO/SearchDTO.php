<?php

declare(strict_types=1);

/**
 * SearchDTO contract — a DTO carrying the ANSWER to a filter: the matched
 * records and the pagination describing the slice they came from.
 *
 * It classifies rather than constrains. A FilterDTO is the question, a
 * SearchDTO is the answer, and neither is an EntityDTO — a result stands for a
 * collection, not for one stored record.
 *
 * Construction rule: none. It is assembled by the layer that ran the query,
 * from the entities and the paginator it already holds, so there is no adapter
 * and no `fromArray()` — a result is produced, never parsed. Prefer composing
 * the paginator over copying its numbers; a stored `total` can disagree with
 * the slice it came from.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\DTO;

interface SearchDTO extends DTO {}
