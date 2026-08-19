<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests;

use PHPdot\Package\Scanner\PackageScanner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Docblock @param parsing regressions: a param with no same-line description
 * must never swallow the NEXT LINE as its description (the old regex's \s+
 * matched across newlines, so scaffolds showed literal "@param int $port"
 * comments on the wrong key), multi-line descriptions must be joined instead
 * of truncated, and generic types containing spaces must parse.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ParamDescriptionParsingTest extends TestCase
{
    #[Test]
    public function bareParamNeverSwallowsTheNextLine(): void
    {
        $descriptions = $this->parse(ParamFixture::class);

        self::assertArrayNotHasKey('host', $descriptions, 'a bare @param has no description at all');
        self::assertSame('Port', $descriptions['port']);
    }

    #[Test]
    public function multiLineDescriptionsAreJoined(): void
    {
        $descriptions = $this->parse(ParamFixture::class);

        self::assertSame(
            'Directories to scan; empty means the current working directory is used instead.',
            $descriptions['paths'],
        );
    }

    #[Test]
    public function genericTypesWithSpacesParse(): void
    {
        $descriptions = $this->parse(ParamFixture::class);

        self::assertSame('Extra settings merged underneath.', $descriptions['rawSettings']);
    }

    /**
     * Run the private parser against a fixture class.
     *
     * @param class-string $class The fixture class
     *
     * @return array<string, string>
     */
    private function parse(string $class): array
    {
        $scanner = new ReflectionClass(PackageScanner::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(PackageScanner::class, 'parseParamDescriptions');

        $result = $method->invoke($scanner, new ReflectionClass($class));

        self::assertIsArray($result);

        /**
         * @var array<string, string> $result
         */
        return $result;
    }
}

final class ParamFixture
{
    /**
     * Create the fixture.
     *
     * @param string $host
     * @param int $port Port
     * @param list<string> $paths Directories to scan; empty means the
     *                            current working directory is used instead.
     * @param array<string, mixed> $rawSettings Extra settings merged underneath.
     */
    public function __construct(
        public readonly string $host = '',
        public readonly int $port = 0,
        public readonly array $paths = [],
        public readonly array $rawSettings = [],
    ) {}
}
