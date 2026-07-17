<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit;

use InvalidArgumentException;
use PHPdot\Session\SessionConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new SessionConfig();

        self::assertSame('phpdot_session', $config->name);
        self::assertSame(7200, $config->lifetime);
        self::assertSame('/', $config->path);
        self::assertSame('', $config->domain);
        self::assertTrue($config->secure);
        self::assertTrue($config->httpOnly);
        self::assertSame('Lax', $config->sameSite);
        self::assertFalse($config->partitioned);
        self::assertSame(2, $config->gcProbability);
        self::assertSame('/tmp/phpdot_sessions', $config->savePath);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new SessionConfig(
            name: 'my_session',
            lifetime: 3600,
            path: '/app',
            domain: '.example.com',
            secure: false,
            httpOnly: false,
            sameSite: 'Strict',
            partitioned: true,
            gcProbability: 10,
            savePath: '/var/sessions',
        );

        self::assertSame('my_session', $config->name);
        self::assertSame(3600, $config->lifetime);
        self::assertSame('/app', $config->path);
        self::assertSame('.example.com', $config->domain);
        self::assertFalse($config->secure);
        self::assertFalse($config->httpOnly);
        self::assertSame('Strict', $config->sameSite);
        self::assertTrue($config->partitioned);
        self::assertSame(10, $config->gcProbability);
        self::assertSame('/var/sessions', $config->savePath);
    }

    #[Test]
    public function classIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(SessionConfig::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function zeroLifetimeMeansBrowserSession(): void
    {
        $config = new SessionConfig(lifetime: 0);

        self::assertSame(0, $config->lifetime);
    }

    #[Test]
    public function zeroGcProbabilityDisablesGc(): void
    {
        $config = new SessionConfig(gcProbability: 0);

        self::assertSame(0, $config->gcProbability);
    }

    #[Test]
    public function emptyNameRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not be empty');

        new SessionConfig(name: '');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidCookieNameProvider(): iterable
    {
        yield 'space'        => ['my session'];
        yield 'tab'          => ["my\tsession"];
        yield 'newline'      => ["my\nsession"];
        yield 'equals'       => ['my=session'];
        yield 'semicolon'    => ['my;session'];
        yield 'comma'        => ['my,session'];
        yield 'control char' => ["my\x01session"];
        yield 'DEL char'     => ["my\x7Fsession"];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCookieNameProvider')]
    public function invalidCookieNameRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains invalid characters');

        new SessionConfig(name: $name);
    }

    #[Test]
    public function negativeLifetimeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lifetime must be >= 0');

        new SessionConfig(lifetime: -1);
    }

    #[Test]
    public function unknownSameSiteRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sameSite must be one of Strict, Lax, None');

        new SessionConfig(sameSite: 'Foo');
    }

    #[Test]
    public function lowercaseSameSiteRejected(): void
    {
        // Browsers expect the canonical capitalisation per RFC 6265bis.
        $this->expectException(InvalidArgumentException::class);

        new SessionConfig(sameSite: 'lax');
    }

    #[Test]
    public function sameSiteNoneRequiresSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sameSite=None requires secure=true');

        new SessionConfig(secure: false, sameSite: 'None');
    }

    #[Test]
    public function sameSiteNoneWithSecureAccepted(): void
    {
        $config = new SessionConfig(secure: true, sameSite: 'None');

        self::assertSame('None', $config->sameSite);
        self::assertTrue($config->secure);
    }

    #[Test]
    public function negativeGcProbabilityRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('gcProbability must be in [0, 100]');

        new SessionConfig(gcProbability: -1);
    }

    #[Test]
    public function gcProbabilityAbove100Rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('gcProbability must be in [0, 100]');

        new SessionConfig(gcProbability: 101);
    }

    #[Test]
    public function gcProbabilityAt100Accepted(): void
    {
        $config = new SessionConfig(gcProbability: 100);

        self::assertSame(100, $config->gcProbability);
    }

    #[Test]
    public function sameSiteConstantsMatchSpec(): void
    {
        self::assertSame('Strict', SessionConfig::SAME_SITE_STRICT);
        self::assertSame('Lax', SessionConfig::SAME_SITE_LAX);
        self::assertSame('None', SessionConfig::SAME_SITE_NONE);
    }
}
