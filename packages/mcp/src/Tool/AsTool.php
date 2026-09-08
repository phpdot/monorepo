<?php

declare(strict_types=1);

/**
 * Marks a service method as a tool a model may call.
 *
 * A discovery convention, deliberately the same shape as the rest of the estate:
 * routes are found by filename, commands and permissions by attribute, and tools by
 * this. Nothing is registered by hand — a directory scanned for this attribute is
 * the whole registration story.
 *
 * A tool is four facts and three hints. The facts are the method, its permission
 * key, what it answers in the domain's own words, and the schema beside it: a
 * schema says a value is a string, and only prose says what a channel IS, which is
 * why `description` is the part that decides whether any of this works.
 *
 * The hints ride MCP's tool annotations to every client: `readOnly` says the tool
 * does not modify its environment, `destructive` says it may perform destructive
 * updates, `idempotent` says repeating the call adds nothing. All three are passed
 * explicitly on the wire — never left null — so the protocol's silent defaults
 * (an unset destructiveHint reads as true) never answer for a tool that did not
 * say so.
 *
 * `permission` is REQUIRED and has no default. A tool that forgot to declare one
 * would be reachable and ungoverned, which must be a boot failure rather than
 * something discovered later by a model that found it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Tool;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsTool
{
    /**
     * @param string $name The tool's name, as the model sees it
     * @param string $permission The key the caller must hold, from the host's permission vocabulary
     * @param string $description What the tool answers, in the domain's own words
     * @param bool $readOnly Whether the tool does not modify its environment
     * @param bool $destructive Whether the tool may perform destructive updates
     * @param bool $idempotent Whether repeated calls with the same arguments add nothing
     */
    public function __construct(
        public string $name,
        public string $permission,
        public string $description,
        public bool $readOnly = false,
        public bool $destructive = false,
        public bool $idempotent = false,
    ) {}
}
