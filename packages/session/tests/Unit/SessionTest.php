<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit;

use PHPdot\Session\Session;
use PHPdot\Session\SessionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(SessionId::generate(), time(), time());
        $this->session->load([]);
    }

    #[Test]
    public function getReturnsStoredValue(): void
    {
        $this->session->set('name', 'Omar');

        self::assertSame('Omar', $this->session->get('name'));
    }

    #[Test]
    public function getReturnsDefaultWhenKeyMissing(): void
    {
        self::assertSame('fallback', $this->session->get('missing', 'fallback'));
    }

    #[Test]
    public function getReturnsNullByDefault(): void
    {
        self::assertNull($this->session->get('missing'));
    }

    #[Test]
    public function hasTrueForExistingKey(): void
    {
        $this->session->set('key', 'value');

        self::assertTrue($this->session->has('key'));
    }

    #[Test]
    public function hasFalseForMissingKey(): void
    {
        self::assertFalse($this->session->has('nonexistent'));
    }

    #[Test]
    public function removeDeletesKey(): void
    {
        $this->session->set('key', 'value');
        $this->session->remove('key');

        self::assertFalse($this->session->has('key'));
    }

    #[Test]
    public function removeNonExistentIsSafe(): void
    {
        $this->session->remove('nonexistent');

        self::assertFalse($this->session->has('nonexistent'));
    }

    #[Test]
    public function allReturnsAllData(): void
    {
        $this->session->set('a', 1);
        $this->session->set('b', 2);

        $all = $this->session->all();

        self::assertSame(1, $all['a']);
        self::assertSame(2, $all['b']);
    }

    #[Test]
    public function clearRemovesAllData(): void
    {
        $this->session->set('a', 1);
        $this->session->set('b', 2);
        $this->session->clear();

        self::assertSame([], $this->session->all());
    }

    #[Test]
    public function allExcludesInternalMetadata(): void
    {
        // Regression: all() used to leak _created_at, _last_activity,
        // _flash_new, _flash_old, _token alongside user keys.
        $this->session->set('user_id', 42);
        $this->session->set('email', 'x@y');
        $this->session->flash('notice', 'hello');
        $this->session->token();

        $all = $this->session->all();

        self::assertSame(
            ['user_id' => 42, 'email' => 'x@y', 'notice' => 'hello'],
            $all,
        );
        foreach (['_created_at', '_last_activity', '_flash_new', '_flash_old', '_token'] as $internal) {
            self::assertArrayNotHasKey($internal, $all);
        }
    }

    #[Test]
    public function userKeyDoesNotCollideWithCsrfToken(): void
    {
        // Regression: with internals namespaced separately, setting a key
        // that used to be reserved no longer clobbers the CSRF token.
        $token = $this->session->token();
        $this->session->set('_token', 'attacker-controlled');

        self::assertSame($token, $this->session->token());
        self::assertSame('attacker-controlled', $this->session->get('_token'));
    }

    #[Test]
    public function flashStoresValueAndTracksKey(): void
    {
        $this->session->flash('msg', 'hello');

        self::assertSame('hello', $this->session->get('msg'));
        self::assertTrue($this->session->hasFlash('msg'));
    }

    #[Test]
    public function getFlashReturnsFlashedValue(): void
    {
        $this->session->flash('msg', 'hello');

        self::assertSame('hello', $this->session->getFlash('msg'));
    }

    #[Test]
    public function getFlashReturnsDefaultWhenMissing(): void
    {
        self::assertSame('default', $this->session->getFlash('missing', 'default'));
    }

    #[Test]
    public function hasFlashTrueForNewFlash(): void
    {
        $this->session->flash('msg', 'hello');

        self::assertTrue($this->session->hasFlash('msg'));
    }

    #[Test]
    public function hasFlashFalseForNonFlashData(): void
    {
        $this->session->set('regular', 'data');

        self::assertFalse($this->session->hasFlash('regular'));
    }

    #[Test]
    public function hasFlashTrueForOldFlash(): void
    {
        $this->session->flash('msg', 'hello');

        $data = $this->session->toArray();
        $newSession = new Session(SessionId::generate(), time(), time());
        $newSession->load($data);

        self::assertTrue($newSession->hasFlash('msg'));
    }

    #[Test]
    public function flashLifecycleAcrossRequests(): void
    {
        $this->session->flash('msg', 'hello');
        $this->session->ageFlash();
        $data = $this->session->toArray();

        $request2 = new Session(SessionId::generate(), time(), time());
        $request2->load($data);

        self::assertSame('hello', $request2->getFlash('msg'));
        self::assertTrue($request2->hasFlash('msg'));

        $request2->ageFlash();
        $data2 = $request2->toArray();

        $request3 = new Session(SessionId::generate(), time(), time());
        $request3->load($data2);

        self::assertFalse($request3->hasFlash('msg'));
        self::assertNull($request3->getFlash('msg'));
    }

    #[Test]
    public function reflashMovesOldToNew(): void
    {
        $this->session->flash('msg', 'hello');
        $data = $this->session->toArray();

        $request2 = new Session(SessionId::generate(), time(), time());
        $request2->load($data);
        $request2->reflash();
        $request2->ageFlash();
        $data2 = $request2->toArray();

        $request3 = new Session(SessionId::generate(), time(), time());
        $request3->load($data2);

        self::assertSame('hello', $request3->getFlash('msg'));
    }

    #[Test]
    public function keepPreservesSpecificKeys(): void
    {
        $this->session->flash('keep_me', 'kept');
        $this->session->flash('drop_me', 'dropped');
        $data = $this->session->toArray();

        $request2 = new Session(SessionId::generate(), time(), time());
        $request2->load($data);
        $request2->keep(['keep_me']);
        $request2->ageFlash();
        $data2 = $request2->toArray();

        $request3 = new Session(SessionId::generate(), time(), time());
        $request3->load($data2);

        self::assertTrue($request3->hasFlash('keep_me'));
        self::assertFalse($request3->hasFlash('drop_me'));
    }

    #[Test]
    public function flashSameKeyTwiceUpdatesValue(): void
    {
        $this->session->flash('msg', 'first');
        $this->session->flash('msg', 'second');

        self::assertSame('second', $this->session->getFlash('msg'));
    }

    #[Test]
    public function multipleFlashKeysTrackedIndependently(): void
    {
        $this->session->flash('a', 1);
        $this->session->flash('b', 2);

        self::assertTrue($this->session->hasFlash('a'));
        self::assertTrue($this->session->hasFlash('b'));
    }

    #[Test]
    public function idReturnsSessionIdString(): void
    {
        $id = SessionId::generate();
        $session = new Session($id, time(), time());

        self::assertSame($id->toString(), $session->id());
    }

    #[Test]
    public function regenerateChangesId(): void
    {
        $oldId = $this->session->id();

        $this->session->regenerate();

        self::assertNotSame($oldId, $this->session->id());
    }

    #[Test]
    public function regenerateGeneratesValidId(): void
    {
        $this->session->regenerate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $this->session->id());
    }

    #[Test]
    public function regenerateWithDestroyMarksDestroyed(): void
    {
        $this->session->regenerate(destroy: true);

        self::assertTrue($this->session->isDestroyed());
    }

    #[Test]
    public function regenerateWithoutDestroyDoesNotMarkDestroyed(): void
    {
        $this->session->regenerate(destroy: false);

        self::assertFalse($this->session->isDestroyed());
    }

    #[Test]
    public function invalidateClearsAndRegenerates(): void
    {
        $this->session->set('key', 'value');
        $oldId = $this->session->id();

        $this->session->invalidate();

        self::assertSame([], $this->session->all());
        self::assertNotSame($oldId, $this->session->id());
        self::assertTrue($this->session->isDestroyed());
    }

    #[Test]
    public function isStartedFalseBeforeLoad(): void
    {
        $session = new Session(SessionId::generate(), time(), time());

        self::assertFalse($session->isStarted());
    }

    #[Test]
    public function isStartedTrueAfterLoad(): void
    {
        self::assertTrue($this->session->isStarted());
    }

    #[Test]
    public function tokenGeneratesOnFirstCall(): void
    {
        $token = $this->session->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    #[Test]
    public function tokenReturnsSameOnSubsequentCalls(): void
    {
        $token1 = $this->session->token();
        $token2 = $this->session->token();

        self::assertSame($token1, $token2);
    }

    #[Test]
    public function regenerateTokenProducesNewToken(): void
    {
        $token1 = $this->session->token();
        $token2 = $this->session->regenerateToken();

        self::assertNotSame($token1, $token2);
        self::assertSame($token2, $this->session->token());
    }

    #[Test]
    public function createdAtReturnsConstructorValue(): void
    {
        $now = time();
        $session = new Session(SessionId::generate(), $now, $now + 100);

        self::assertSame($now, $session->createdAt());
    }

    #[Test]
    public function lastActivityReturnsConstructorValue(): void
    {
        $now = time();
        $session = new Session(SessionId::generate(), $now - 100, $now);

        self::assertSame($now, $session->lastActivity());
    }

    #[Test]
    public function setLastActivityUpdatesValue(): void
    {
        $newTime = time() + 500;

        $this->session->setLastActivity($newTime);

        self::assertSame($newTime, $this->session->lastActivity());
    }

    #[Test]
    public function toArrayReturnsFullPayload(): void
    {
        $this->session->set('key', 'value');
        $this->session->flash('msg', 'hello');
        $token = $this->session->token();

        $array = $this->session->toArray();

        self::assertSame(['key' => 'value', 'msg' => 'hello'], $array['data']);
        self::assertSame(['msg'], $array['flash_new']);
        self::assertSame([], $array['flash_old']);
        self::assertSame($token, $array['token']);
        self::assertIsInt($array['created_at']);
        self::assertIsInt($array['last_activity']);
    }

    #[Test]
    public function loadSetsDataAndMarksStarted(): void
    {
        $session = new Session(SessionId::generate(), time(), time());

        self::assertFalse($session->isStarted());

        $session->load(['data' => ['foo' => 'bar']]);

        self::assertTrue($session->isStarted());
        self::assertSame('bar', $session->get('foo'));
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(Session::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function getSessionIdReturnsValueObject(): void
    {
        $id = SessionId::generate();
        $session = new Session($id, time(), time());

        self::assertTrue($id->equals($session->getSessionId()));
    }

    #[Test]
    public function ageFlashRemovesOldFlashKeys(): void
    {
        $this->session->flash('msg', 'hello');
        $data = $this->session->toArray();

        $request2 = new Session(SessionId::generate(), time(), time());
        $request2->load($data);

        self::assertTrue($request2->has('msg'));

        $request2->ageFlash();

        self::assertFalse($request2->has('msg'));
    }

    #[Test]
    public function reflashingKeyFromOldRemovesFromOld(): void
    {
        $this->session->flash('msg', 'hello');
        $data = $this->session->toArray();

        $request2 = new Session(SessionId::generate(), time(), time());
        $request2->load($data);

        $request2->flash('msg', 'updated');

        $request2->ageFlash();

        self::assertTrue($request2->has('msg'));
        self::assertSame('updated', $request2->get('msg'));
    }
}
