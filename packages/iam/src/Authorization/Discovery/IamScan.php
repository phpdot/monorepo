<?php

declare(strict_types=1);

/**
 * Discovery over phpdot/attribute: the Scanner finds classes and reads
 * attributes (token discovery + reflection — the ecosystem's machinery, not
 * ours); this class keeps only iam's DOMAIN LAWS — the key format, key
 * uniqueness, and the policy __invoke convention. Runs ONLY inside
 * iam:permission:sync — a CLI process that exits when done, so loading
 * application classes is harmless.
 *
 * Fail-fast with typed exceptions naming declaration sites: a malformed
 * key, duplicate key, or malformed policy shape is a sync error, never a
 * runtime surprise.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Discovery;

use PHPdot\Attribute\Scanner;
use PHPdot\Iam\Authorization\Attribute\Permission;
use PHPdot\Iam\Authorization\Contract\PolicyInterface;
use PHPdot\Iam\Authorization\IamResource;
use PHPdot\Iam\Exception\DuplicatePermissionException;
use PHPdot\Iam\Exception\InvalidPermissionKeyException;
use PHPdot\Iam\Exception\InvalidPolicyException;
use PHPdot\Iam\Exception\InvalidScanException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

final class IamScan
{
    /**
     * Scan directories for #[Permission] constants and PolicyInterface classes.
     *
     * @param list<string> $directories Absolute directories to scan
     *
     * @return array{permissions: list<DiscoveredPermission>, policies: list<DiscoveredPolicy>}
     */
    public function run(array $directories): array
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                throw InvalidScanException::missingDirectory($directory);
            }
        }

        $registry = new Scanner()->scan($directories);

        $permissions = [];
        $declaredAt = [];

        foreach ($registry->findConstantAttributes(Permission::class) as $result) {
            $site = $result->class . '::' . ($result->constant ?? '<none>');

            if (!$result->instance instanceof Permission || $result->constant === null) {
                throw InvalidScanException::unreadableDeclaration($site);
            }

            $meta = $result->instance;
            $key = constant($result->class . '::' . $result->constant);

            if (!is_string($key) || !PermissionKey::valid($key)) {
                throw InvalidPermissionKeyException::at(is_scalar($key) ? (string) $key : gettype($key), $site);
            }

            if (isset($declaredAt[$key])) {
                throw new DuplicatePermissionException(sprintf(
                    'Permission key [%s] is declared twice: %s and %s.',
                    $key,
                    $declaredAt[$key],
                    $site,
                ));
            }

            $declaredAt[$key] = $site;
            $permissions[] = new DiscoveredPermission(
                key: $key,
                name: $meta->name,
                description: $meta->description,
                root: $meta->root,
                declaredBy: $site,
            );
        }

        $policies = [];

        foreach ($registry->findImplementing(PolicyInterface::class) as $class) {
            $policy = $this->inventoryPolicy($class);

            if ($policy !== null) {
                $policies[] = $policy;
            }
        }

        return ['permissions' => $permissions, 'policies' => $policies];
    }

    /**
     * Inventory one policy class, enforcing the __invoke convention:
     * exactly public __invoke(IdentityInterface, <T extends IamResource>): bool.
     *
     * @param string $class The implementing class
     *
     * @return DiscoveredPolicy|null
     */
    private function inventoryPolicy(string $class): null|DiscoveredPolicy
    {
        try {
            if (!class_exists($class)) {
                return null;
            }

            $reflection = new ReflectionClass($class);
        } catch (Throwable) {
            return null;
        }

        if (!$reflection->isInstantiable()) {
            return null;
        }

        $invoke = $reflection->hasMethod('__invoke') ? $reflection->getMethod('__invoke') : null;
        $parameters = $invoke?->getParameters() ?? [];
        $identityType = ($parameters[0] ?? null)?->getType();
        $resourceType = ($parameters[1] ?? null)?->getType();
        $returnType = $invoke?->getReturnType();

        if ($invoke === null
            || !$invoke->isPublic()
            || count($parameters) !== 2
            || !$identityType instanceof ReflectionNamedType
            || $identityType->getName() !== IdentityInterface::class
            || !$resourceType instanceof ReflectionNamedType
            || ($resourceType->getName() !== IamResource::class && !is_subclass_of($resourceType->getName(), IamResource::class))
            || !$returnType instanceof ReflectionNamedType
            || $returnType->getName() !== 'bool'
        ) {
            throw InvalidPolicyException::malformed($reflection->getName());
        }

        return new DiscoveredPolicy(policy: $reflection->getName(), resource: $resourceType->getName());
    }
}
