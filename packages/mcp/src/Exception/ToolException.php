<?php

declare(strict_types=1);

/**
 * A tool could not be discovered or run: unknown, undeclared, duplicated, refused,
 * or one that answered in a shape nothing downstream can use.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Exception;

final class ToolException extends McpException
{
    /**
     * No discovered tool carries the name.
     *
     * @param string $name The name the caller asked for
     *
     * @return self
     */
    public static function unknownTool(string $name): self
    {
        return new self(sprintf('No tool is named [%s].', $name));
    }

    /**
     * The call resolved to something that is not a tool — a prompt, a resource,
     * anything else the protocol can name. This server serves tools only.
     *
     * @param string $kind What the reference actually was
     *
     * @return self
     */
    public static function unsupportedReference(string $kind): self
    {
        return new self(sprintf('This server serves tools only; the call resolved to a %s.', $kind));
    }

    /**
     * A tool declared no permission, which would make it reachable and ungoverned.
     *
     * This is a boot failure on purpose: it must be discovered by the operator at
     * startup, not by a model that found an ungoverned tool in production.
     *
     * @param string $name The tool's name
     * @param string $class The class holding it
     * @param string $method The method carrying the attribute
     *
     * @return self
     */
    public static function unpermissioned(string $name, string $class, string $method): self
    {
        return new self(sprintf(
            'Tool [%s] on %s::%s declares no permission. Every tool states exactly one.',
            $name,
            $class,
            $method,
        ));
    }

    /**
     * Two tools claimed one name, and the vocabulary would answer to either.
     *
     * @param string $name The contested name
     * @param string $firstClass The first claimant's class
     * @param string $firstMethod The first claimant's method
     * @param string $secondClass The second claimant's class
     * @param string $secondMethod The second claimant's method
     *
     * @return self
     */
    public static function duplicateTool(
        string $name,
        string $firstClass,
        string $firstMethod,
        string $secondClass,
        string $secondMethod,
    ): self {
        return new self(sprintf(
            'Two tools claim the name [%s]: %s::%s and %s::%s.',
            $name,
            $firstClass,
            $firstMethod,
            $secondClass,
            $secondMethod,
        ));
    }

    /**
     * The actor does not hold the permission the tool requires.
     *
     * @param string $permission The key the actor lacks
     * @param string $name The tool that requires it
     *
     * @return self
     */
    public static function denied(string $permission, string $name): self
    {
        return new self(sprintf('You do not hold [%s], which [%s] requires.', $permission, $name));
    }

    /**
     * The tool ran and answered with something other than an array.
     *
     * @param string $name The tool that answered wrongly
     *
     * @return self
     */
    public static function badAnswer(string $name): self
    {
        return new self(sprintf('Tool [%s] answered with something other than an array.', $name));
    }
}
