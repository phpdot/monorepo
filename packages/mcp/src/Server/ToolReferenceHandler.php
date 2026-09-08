<?php

declare(strict_types=1);

/**
 * Runs an MCP tool call through the host's own registry.
 *
 * The SDK's default handler maps a call's arguments onto a PHP method's parameters
 * BY NAME, which suits a tool written as `function search(string $query, string $from)`
 * and suits nothing here: tools take one {@see ToolArguments}, because a model's
 * arguments arrive shaped like a form post — a number as a string, a bare scalar
 * where a list was declared, a key simply absent. Coercing that is the tool layer's
 * job and it is done in one place.
 *
 * So the handler seam is taken instead of fought. The SDK hands us the raw argument
 * array and the reference; the tool's name is read off the reference and the call
 * routes into {@see ToolRegistry::call()} — which re-checks the permission, because
 * the registry never trusts its caller to have filtered.
 *
 * THE ACTOR IS BOUND PER REQUEST, never per worker. One of these is built for the
 * request being served, holds that request's actor, and is discarded with it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Server;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use PHPdot\Mcp\Contract\ToolActorInterface;
use PHPdot\Mcp\Exception\ToolException;
use PHPdot\Mcp\Tool\ToolArguments;
use PHPdot\Mcp\Tool\ToolRegistry;

final readonly class ToolReferenceHandler implements ReferenceHandlerInterface
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolActorInterface $actor,
    ) {}

    /**
     * @param ElementReference $reference What the SDK resolved the call to
     * @param array<string, mixed> $arguments What the caller sent
     *
     * @throws ToolException If the tool is unknown here, or the actor may not call it
     *
     * @return array<string, mixed>
     */
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if (!$reference instanceof ToolReference) {
            throw ToolException::unsupportedReference($reference::class);
        }

        /*
         * `_session` and `_request` are the SDK's own injectables and are not the
         * model's arguments. Passing them through would put two keys in the tool's
         * input that its schema never declared.
         */
        unset($arguments['_session'], $arguments['_request']);

        return $this->registry->call($reference->tool->name, new ToolArguments($arguments), $this->actor);
    }
}
