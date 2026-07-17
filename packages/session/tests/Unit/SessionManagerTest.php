<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit;

use PHPdot\Session\Exception\SessionExpiredException;
use PHPdot\Session\Handler\ArrayHandler;
use PHPdot\Session\SessionConfig;
use PHPdot\Session\SessionId;
use PHPdot\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionManagerTest extends TestCase
{
    private ArrayHandler $handler;
    private SessionConfig $config;
    private SessionManager $manager;

    protected function setUp(): void
    {
        $this->handler = new ArrayHandler();
        $this->config = new SessionConfig(gcProbability: 0);
        $this->manager = new SessionManager($this->handler, $this->config);
    }

    #[Test]
    public function startWithNullCreatesNewSession(): void
    {
        $session = $this->manager->start(null);

        self::assertTrue($session->isStarted());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $session->id());
    }

    #[Test]
    public function startWithEmptyStringCreatesNewSession(): void
    {
        $session = $this->manager->start('');

        self::assertTrue($session->isStarted());
    }

    #[Test]
    public function startWithValidIdResumesExistingSession(): void
    {
        $first = $this->manager->start(null);
        $first->set('user_id', 42);
        $this->manager->save($first);

        $resumed = $this->manager->start($first->id());

        self::assertSame(42, $resumed->get('user_id'));
    }

    #[Test]
    public function startWithInvalidFormatCreatesNewSession(): void
    {
        $session = $this->manager->start('not-a-valid-session-id');

        self::assertTrue($session->isStarted());
    }

    #[Test]
    public function startWithNonexistentIdCreatesNewSession(): void
    {
        $id = SessionId::generate()->toString();

        $session = $this->manager->start($id);

        self::assertTrue($session->isStarted());
        self::assertNotSame($id, $session->id());
    }

    #[Test]
    public function startWithExpiredSessionThrows(): void
    {
        $config = new SessionConfig(lifetime: 1, gcProbability: 0);
        $manager = new SessionManager($this->handler, $config);

        $session = $manager->start(null);
        $this->manager->save($session);

        $this->handler->write(
            $session->id(),
            json_encode([
                'data' => [],
                'flash_new' => [],
                'flash_old' => [],
                'token' => null,
                'created_at' => time() - 100,
                'last_activity' => time() - 100,
            ], JSON_THROW_ON_ERROR),
            0,
        );

        $this->expectException(SessionExpiredException::class);

        $manager->start($session->id());
    }

    #[Test]
    public function saveWritesToHandler(): void
    {
        $session = $this->manager->start(null);
        $session->set('key', 'value');

        $this->manager->save($session);

        self::assertTrue($this->handler->exists($session->id()));
    }

    #[Test]
    public function saveUpdatesLastActivity(): void
    {
        $session = $this->manager->start(null);
        $before = $session->lastActivity();

        $this->manager->save($session);

        self::assertGreaterThanOrEqual($before, $session->lastActivity());
    }

    #[Test]
    public function saveAgesFlashData(): void
    {
        $session = $this->manager->start(null);
        $session->flash('msg', 'hello');
        $this->manager->save($session);

        $resumed = $this->manager->start($session->id());

        self::assertSame('hello', $resumed->getFlash('msg'));

        $this->manager->save($resumed);

        $final = $this->manager->start($resumed->id());

        self::assertNull($final->getFlash('msg'));
    }

    #[Test]
    public function destroyRemovesFromHandler(): void
    {
        $session = $this->manager->start(null);
        $this->manager->save($session);
        $id = $session->id();

        $this->manager->destroy($id);

        self::assertFalse($this->handler->exists($id));
    }

    #[Test]
    public function cookieHeaderIncludesAllOptions(): void
    {
        $config = new SessionConfig(
            name: 'sid',
            lifetime: 3600,
            path: '/',
            domain: '.example.com',
            secure: true,
            httpOnly: true,
            sameSite: 'Strict',
            partitioned: true,
        );
        $manager = new SessionManager($this->handler, $config);
        $session = $manager->start(null);

        $header = $manager->cookieHeader($session);

        self::assertStringContainsString('sid=' . $session->id(), $header);
        self::assertStringContainsString('Path=/', $header);
        self::assertStringContainsString('Domain=.example.com', $header);
        self::assertStringContainsString('Max-Age=3600', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
        self::assertStringContainsString('Partitioned', $header);
    }

    #[Test]
    public function cookieHeaderOmitsDomainWhenEmpty(): void
    {
        $config = new SessionConfig(domain: '');
        $manager = new SessionManager($this->handler, $config);
        $session = $manager->start(null);

        $header = $manager->cookieHeader($session);

        self::assertStringNotContainsString('Domain=', $header);
    }

    #[Test]
    public function cookieHeaderOmitsMaxAgeWhenLifetimeIsZero(): void
    {
        $config = new SessionConfig(lifetime: 0);
        $manager = new SessionManager($this->handler, $config);
        $session = $manager->start(null);

        $header = $manager->cookieHeader($session);

        self::assertStringNotContainsString('Max-Age=', $header);
    }

    #[Test]
    public function expireCookieHeaderSetsMaxAgeZero(): void
    {
        $header = $this->manager->expireCookieHeader();

        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringContainsString($this->config->name . '=', $header);
    }

    #[Test]
    public function gcRunsWithProbability100(): void
    {
        $config = new SessionConfig(lifetime: 1, gcProbability: 100);
        $manager = new SessionManager($this->handler, $config);

        $this->handler->write('old_session', '{}', 1);

        // Simulate expired entry
        $reflection = new \ReflectionClass($this->handler);
        $storage = $reflection->getProperty('storage');
        $data = $storage->getValue($this->handler);
        $data['old_session']['expiry'] = time() - 10;
        $storage->setValue($this->handler, $data);

        $manager->start(null);

        self::assertFalse($this->handler->exists('old_session'));
    }

    #[Test]
    public function gcDoesNotRunWithProbabilityZero(): void
    {
        $config = new SessionConfig(lifetime: 1, gcProbability: 0);
        $manager = new SessionManager($this->handler, $config);

        $this->handler->write('old_session', '{}', 1);

        $reflection = new \ReflectionClass($this->handler);
        $storage = $reflection->getProperty('storage');
        $data = $storage->getValue($this->handler);
        $data['old_session']['expiry'] = time() - 10;
        $storage->setValue($this->handler, $data);

        $manager->start(null);

        // GC did not run — verify the raw storage still contains the entry
        $rawStorage = $storage->getValue($this->handler);

        self::assertArrayHasKey('old_session', $rawStorage);
    }

    #[Test]
    public function newSessionExposesTimestampsViaAccessors(): void
    {
        $before = time();
        $session = $this->manager->start(null);

        self::assertGreaterThanOrEqual($before, $session->createdAt());
        self::assertGreaterThanOrEqual($before, $session->lastActivity());
    }

    #[Test]
    public function newSessionHidesInternalsFromUserData(): void
    {
        $session = $this->manager->start(null);

        self::assertSame([], $session->all());
        self::assertFalse($session->has('_created_at'));
        self::assertFalse($session->has('_last_activity'));
        self::assertFalse($session->has('_flash_new'));
        self::assertFalse($session->has('_flash_old'));
        self::assertFalse($session->has('_token'));
    }

    #[Test]
    public function resumePreservesData(): void
    {
        $session = $this->manager->start(null);
        $session->set('key', 'value');
        $session->set('nested', ['a' => 1, 'b' => 2]);
        $this->manager->save($session);

        $resumed = $this->manager->start($session->id());

        self::assertSame('value', $resumed->get('key'));
        self::assertSame(['a' => 1, 'b' => 2], $resumed->get('nested'));
    }

    #[Test]
    public function getConfigReturnsConfig(): void
    {
        self::assertSame($this->config, $this->manager->getConfig());
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(SessionManager::class);

        self::assertTrue($reflection->isFinal());
    }
}
