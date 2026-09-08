<?php

declare(strict_types=1);

/**
 * Every tool the host declares, and who may call which.
 *
 * Discovery is by convention, as the rest of the estate is: routes by filename,
 * commands and permissions by attribute, tools by {@see AsTool} on a method in a
 * directory the host points the registry at. A module gains tools by gaining a
 * file; nothing is registered by hand. The Scanner does the finding — token
 * discovery and reflection, the ecosystem's machinery — and this class keeps only
 * the domain laws: names are unique, permissions are declared, and the filter runs
 * before anything is shown.
 *
 * A TOOL CLASS IS A LAYER, NOT A SERVICE. It holds the module's service and turns
 * a model's arguments into the call that service already takes. The service is not
 * changed, does not know it is being called by a model, and stays the single place
 * the logic lives — which is the whole reason the tool surface is affordable.
 *
 * THE LIST IS FILTERED BEFORE IT IS SHOWN. `forActor()` answers only what the
 * caller may run, so a tool the actor cannot call is a tool the model is never
 * told about and therefore cannot be talked into naming. That is a security
 * control here and not an ergonomic one, and it is also the whole answer to
 * tool-count: an actor holding one app's keys sees one app's tools.
 *
 * DISCOVERY IS LAZY AND HAPPENS INSIDE A WORKER. The scanner reflects — and so
 * loads — every class in the scanned directories, and classes loaded before a
 * Swoole fork are frozen into every worker generation, where a reload stops
 * applying edits. The first `all()` therefore runs post-fork, on the request that
 * asked for it, and the result is memoized per worker as instance state: never a
 * static, never a persistent cache (the file cache has no mtime invalidation, and
 * a removed tool must leave the vocabulary with its file).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Tool;

use PHPdot\Attribute\Scanner;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Mcp\Contract\ToolActorInterface;
use PHPdot\Mcp\Exception\ToolException;
use PHPdot\Mcp\Server\McpConfig;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

#[Singleton]
final class ToolRegistry
{
    /** @var array<string, ToolDescriptor>|null Discovered once per worker, inside the worker. */
    private null|array $tools = null;

    private readonly Scanner $scanner;

    /**
     * @param ContainerInterface $container Resolves a tool class and its service
     * @param McpConfig $config Where to look, and who the server is
     * @param Scanner|null $scanner The discovery machinery; defaults to an uncached Scanner
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly McpConfig $config,
        null|Scanner $scanner = null,
    ) {
        $this->scanner = $scanner ?? new Scanner();
    }

    /**
     * Every declared tool, by name.
     *
     * @throws ToolException If two tools claim one name, or one declares no permission
     *
     * @return array<string, ToolDescriptor>
     */
    public function all(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $tools = [];

        foreach ($this->discover() as $tool) {
            if (trim($tool->permission) === '') {
                throw ToolException::unpermissioned($tool->name, $tool->class, $tool->method);
            }

            if (isset($tools[$tool->name])) {
                throw ToolException::duplicateTool(
                    $tool->name,
                    $tools[$tool->name]->class,
                    $tools[$tool->name]->method,
                    $tool->class,
                    $tool->method,
                );
            }

            $tools[$tool->name] = $tool;
        }

        ksort($tools);

        return $this->tools = $tools;
    }

    /**
     * The tools this actor may call.
     *
     * @param ToolActorInterface $actor Who is asking
     *
     * @return list<ToolDescriptor>
     */
    public function forActor(ToolActorInterface $actor): array
    {
        $allowed = [];

        foreach ($this->all() as $tool) {
            if ($actor->can($tool->permission)) {
                $allowed[] = $tool;
            }
        }

        return $allowed;
    }

    /**
     * Run one tool as this actor.
     *
     * The permission is checked HERE and not by the caller. A registry that trusted
     * its caller to have filtered would be one refactor away from running an
     * unfiltered name, and there is no second check downstream that would notice.
     *
     * @param string $name Which tool
     * @param ToolArguments $arguments What the model sent
     * @param ToolActorInterface $actor Who is asking
     *
     * @throws ToolException If the tool is unknown, or the actor may not call it
     *
     * @return array<string, mixed> The tool's answer
     */
    public function call(string $name, ToolArguments $arguments, ToolActorInterface $actor): array
    {
        $tool = $this->all()[$name] ?? null;

        if ($tool === null) {
            throw ToolException::unknownTool($name);
        }

        if (!$actor->can($tool->permission)) {
            throw ToolException::denied($tool->permission, $name);
        }

        /** @var object $instance The container builds the tool class and its service. */
        $instance = $this->container->get($tool->class);

        $answer = (new ReflectionMethod($tool->class, $tool->method))->invoke($instance, $arguments);

        if (!is_array($answer)) {
            throw ToolException::badAnswer($name);
        }

        /** @var array<string, mixed> $answer */
        return $answer;
    }

    /**
     * The scan itself: public methods carrying the attribute, as descriptors.
     *
     * A directory that does not exist answers nothing — the same silence a glob
     * gives — rather than failing a host whose deployment layout legitimately
     * varies.
     *
     * @return list<ToolDescriptor>
     */
    private function discover(): array
    {
        $directories = [];

        foreach ($this->config->discoveryDirs as $directory) {
            if (is_dir($directory)) {
                $directories[] = $directory;
            }
        }

        if ($directories === []) {
            return [];
        }

        $results = $this->scanner
            ->scan($directories, filter: [AsTool::class], visibilityFilter: ReflectionMethod::IS_PUBLIC)
            ->findMethodAttributes(AsTool::class);

        $tools = [];

        foreach ($results as $result) {
            $declared = $result->instance;
            $method = $result->method ?? '';

            if (!$declared instanceof AsTool || $method === '') {
                continue;
            }

            $tools[] = new ToolDescriptor(
                name: $declared->name,
                permission: $declared->permission,
                description: $declared->description,
                parameters: $this->parameters($result->class, $method),
                class: $result->class,
                method: $method,
                readOnly: $declared->readOnly,
                destructive: $declared->destructive,
                idempotent: $declared->idempotent,
            );
        }

        return $tools;
    }

    /**
     * The JSON Schema a tool declares for its arguments.
     *
     * Held as a `{method}Schema()` static beside the method rather than inside the
     * attribute: an attribute takes constant expressions, and a schema written as one
     * long literal argument is unreadable next to the prose that matters more.
     *
     * @param class-string $class The tool class
     * @param string $method The method
     *
     * @return array<string, mixed>
     */
    private function parameters(string $class, string $method): array
    {
        $schema = $method . 'Schema';

        if (!method_exists($class, $schema)) {
            return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
        }

        $parameters = (new ReflectionMethod($class, $schema))->invoke(null);

        if (!is_array($parameters)) {
            return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
        }

        /** @var array<string, mixed> $parameters */
        return $parameters;
    }
}
