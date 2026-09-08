<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Integration;

use PHPdot\Iam\Authorization\Discovery\IamScan;
use PHPdot\Iam\Exception\DuplicatePermissionException;
use PHPdot\Iam\Exception\InvalidPermissionKeyException;
use PHPdot\Iam\Exception\InvalidPolicyException;
use PHPdot\Iam\Exception\InvalidScanException;
use PHPdot\Iam\Tests\Fixtures\Scan\Valid\Permissions;
use PHPdot\Iam\Tests\Fixtures\Scan\Valid\ProbePolicy;
use PHPdot\Iam\Tests\Fixtures\Scan\Valid\ProbeResource;
use PHPdot\Iam\Tests\Fixtures\Scan\Valid\SecondProbePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Discovery contract: the in-process scan finds #[Permission] constants and
 * inventories PolicyInterface classes (several per resource is composition,
 * not a collision), returns pure data, and fails fast with TYPED exceptions
 * on vocabulary violations — bad keys, duplicate keys, malformed policy
 * shapes — naming the declaration sites.
 */
final class IamScanTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixtures/Scan';

    #[Test]
    public function theScanFindsPermissionsAndPoliciesWithoutLoadingThem(): void
    {
        $result = new IamScan()->run([self::FIXTURES . '/Valid']);

        $keys = array_map(static fn($p) => $p->key, $result['permissions']);
        sort($keys);

        self::assertSame(['probe.thing.rule', 'probe.thing.view'], $keys);

        $inventory = array_map(
            static fn($p) => [$p->policy, $p->resource],
            $result['policies'],
        );
        sort($inventory);

        self::assertSame([
            [ProbePolicy::class, ProbeResource::class],
            [SecondProbePolicy::class, ProbeResource::class],
        ], $inventory, 'two policies on one resource is composition, not a collision');

        $byKey = [];
        foreach ($result['permissions'] as $permission) {
            $byKey[$permission->key] = $permission;
        }

        self::assertTrue($byKey['probe.thing.rule']->root);
        self::assertFalse($byKey['probe.thing.view']->root);
        self::assertSame(Permissions::class . '::ProbeView', $byKey['probe.thing.view']->declaredBy);
    }

    #[Test]
    public function aDuplicateKeyFailsNamingBothSites(): void
    {
        $this->expectException(DuplicatePermissionException::class);
        $this->expectExceptionMessage('dup.thing.act');

        new IamScan()->run([self::FIXTURES . '/DuplicateKey']);
    }

    #[Test]
    public function aMalformedKeyFailsNamingItsSite(): void
    {
        $this->expectException(InvalidPermissionKeyException::class);
        $this->expectExceptionMessage('Invalid permission key');

        new IamScan()->run([self::FIXTURES . '/InvalidKey']);
    }

    #[Test]
    public function aMalformedPolicyShapeFailsNamingTheClass(): void
    {
        $this->expectException(InvalidPolicyException::class);
        $this->expectExceptionMessage('Malformed policy');

        new IamScan()->run([self::FIXTURES . '/MalformedPolicy']);
    }

    #[Test]
    public function aMissingScanDirectoryFailsInsteadOfReportingAnEmptyCatalog(): void
    {
        $this->expectException(InvalidScanException::class);
        $this->expectExceptionMessage('does not exist');

        new IamScan()->run(['/nonexistent/' . uniqid()]);
    }

    #[Test]
    public function anEmptyDirectoryScanReturnsAnEmptyCatalog(): void
    {
        $empty = sys_get_temp_dir() . '/iam-empty-scan-' . uniqid();
        mkdir($empty);

        try {
            $result = new IamScan()->run([$empty]);
        } finally {
            rmdir($empty);
        }

        self::assertSame(['permissions' => [], 'policies' => []], $result);
    }
}
