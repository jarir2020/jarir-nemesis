<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 8 Test Suite | Created: 2026-04-02
// Tests: Testing Infrastructure — TestResponse, HttpTestClient, DatabaseAssertions,
//        EventFake, QueueFake, MailFake, RefreshDatabase, ActingAs, WithFaker, SimpleFaker

use Nemesis\Testing\TestCase;
use Nemesis\Testing\TestResponse;
use Nemesis\Testing\HttpTestClient;
use Nemesis\Testing\SimpleFaker;
use Nemesis\Testing\DatabaseAssertions;
use Nemesis\Testing\Fakes\EventFake;
use Nemesis\Testing\Fakes\QueueFake;
use Nemesis\Testing\Fakes\MailFake;
use Nemesis\Testing\Concerns\RefreshDatabase;
use Nemesis\Testing\Concerns\ActingAs;
use Nemesis\Testing\Concerns\WithFaker;

class Phase8Test extends TestCase
{
    // =========================================================================
    // Infrastructure classes exist
    // =========================================================================

    public function testTestResponseClassExists(): void
    {
        $this->assertTrue(class_exists(TestResponse::class));
    }

    public function testHttpTestClientClassExists(): void
    {
        $this->assertTrue(class_exists(HttpTestClient::class));
    }

    public function testSimpleFakerClassExists(): void
    {
        $this->assertTrue(class_exists(SimpleFaker::class));
    }

    public function testDatabaseAssertionsTraitExists(): void
    {
        $this->assertTrue(trait_exists(DatabaseAssertions::class));
    }

    public function testEventFakeClassExists(): void
    {
        $this->assertTrue(class_exists(EventFake::class));
    }

    public function testQueueFakeClassExists(): void
    {
        $this->assertTrue(class_exists(QueueFake::class));
    }

    public function testMailFakeClassExists(): void
    {
        $this->assertTrue(class_exists(MailFake::class));
    }

    public function testRefreshDatabaseTraitExists(): void
    {
        $this->assertTrue(trait_exists(RefreshDatabase::class));
    }

    public function testActingAsTraitExists(): void
    {
        $this->assertTrue(trait_exists(ActingAs::class));
    }

    public function testWithFakerTraitExists(): void
    {
        $this->assertTrue(trait_exists(WithFaker::class));
    }

    // =========================================================================
    // TestResponse
    // =========================================================================

    public function testTestResponseGetStatus(): void
    {
        $r = new TestResponse(200, 'body');
        $this->assertEquals(200, $r->getStatus());
    }

    public function testTestResponseGetContent(): void
    {
        $r = new TestResponse(200, 'hello');
        $this->assertEquals('hello', $r->getContent());
    }

    public function testTestResponseAssertOk(): void
    {
        $r = new TestResponse(200, '');
        $r->assertOk(); // must not throw
        $this->assertTrue(true);
    }

    public function testTestResponseAssertStatusThrows(): void
    {
        $r = new TestResponse(404, '');
        try {
            $r->assertStatus(200);
            $this->assertTrue(false, 'Expected exception');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testTestResponseAssertJson(): void
    {
        $r = new TestResponse(200, json_encode(['key' => 'val']));
        $r->assertJson(['key' => 'val']); // must not throw
        $this->assertTrue(true);
    }

    public function testTestResponseAssertJsonThrowsOnMismatch(): void
    {
        $r = new TestResponse(200, json_encode(['key' => 'val']));
        try {
            $r->assertJson(['key' => 'wrong']);
            $this->assertTrue(false, 'Expected exception');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testTestResponseAssertJsonPath(): void
    {
        $r = new TestResponse(200, json_encode(['user' => ['name' => 'Alice']]));
        $r->assertJsonPath('user.name', 'Alice');
        $this->assertTrue(true);
    }

    public function testTestResponseAssertSee(): void
    {
        $r = new TestResponse(200, 'Hello World');
        $r->assertSee('Hello');
        $this->assertTrue(true);
    }

    public function testTestResponseAssertNotSee(): void
    {
        $r = new TestResponse(200, 'Hello World');
        $r->assertNotSee('Goodbye');
        $this->assertTrue(true);
    }

    public function testTestResponseAssertCreated(): void
    {
        $r = new TestResponse(201, '');
        $r->assertCreated();
        $this->assertTrue(true);
    }

    public function testTestResponseAssertForbidden(): void
    {
        $r = new TestResponse(403, '');
        $r->assertForbidden();
        $this->assertTrue(true);
    }

    public function testTestResponseJsonCount(): void
    {
        $r = new TestResponse(200, json_encode(['a', 'b', 'c']));
        $r->assertJsonCount(3);
        $this->assertTrue(true);
    }

    public function testTestResponseJsonDecode(): void
    {
        $r = new TestResponse(200, json_encode(['x' => 1]));
        $this->assertEquals(1, $r->json('x'));
    }

    // =========================================================================
    // HttpTestClient
    // =========================================================================

    public function testHttpTestClientWithToken(): void
    {
        $client  = new HttpTestClient('http://localhost');
        $client2 = $client->withToken('abc123');
        $this->assertFalse($client === $client2); // clone, not mutate
    }

    public function testHttpTestClientWithHeaders(): void
    {
        $client  = new HttpTestClient();
        $client2 = $client->withHeaders(['X-Test' => 'yes']);
        $this->assertFalse($client === $client2);
    }

    // =========================================================================
    // EventFake
    // =========================================================================

    public function testEventFakeDispatchAndAssert(): void
    {
        $fake = new EventFake();
        $fake->dispatch('user.created', ['id' => 1]);
        $fake->assertDispatched('user.created');
        $this->assertTrue(true);
    }

    public function testEventFakeAssertNotDispatched(): void
    {
        $fake = new EventFake();
        $fake->assertNotDispatched('order.placed');
        $this->assertTrue(true);
    }

    public function testEventFakeAssertDispatchedTimes(): void
    {
        $fake = new EventFake();
        $fake->dispatch('ping');
        $fake->dispatch('ping');
        $fake->assertDispatchedTimes('ping', 2);
        $this->assertTrue(true);
    }

    public function testEventFakeAssertNothingDispatched(): void
    {
        $fake = new EventFake();
        $fake->assertNothingDispatched();
        $this->assertTrue(true);
    }

    public function testEventFakeAssertDispatchedWithCallback(): void
    {
        $fake = new EventFake();
        $fake->dispatch('user.created', ['id' => 42]);
        $fake->assertDispatched('user.created', fn($payload) => $payload['id'] === 42);
        $this->assertTrue(true);
    }

    public function testEventFakeFlush(): void
    {
        $fake = new EventFake();
        $fake->dispatch('test');
        $fake->flush();
        $fake->assertNothingDispatched();
        $this->assertTrue(true);
    }

    public function testEventFakeHasDispatched(): void
    {
        $fake = new EventFake();
        $this->assertFalse($fake->hasDispatched('x'));
        $fake->dispatch('x');
        $this->assertTrue($fake->hasDispatched('x'));
    }

    // =========================================================================
    // QueueFake
    // =========================================================================

    public function testQueueFakePushAndAssert(): void
    {
        $fake = new QueueFake();
        $fake->push('SendEmailJob', ['to' => 'alice@x.com']);
        $fake->assertPushed('SendEmailJob');
        $this->assertTrue(true);
    }

    public function testQueueFakeAssertNotPushed(): void
    {
        $fake = new QueueFake();
        $fake->assertNotPushed('NeverPushedJob');
        $this->assertTrue(true);
    }

    public function testQueueFakeAssertPushedTimes(): void
    {
        $fake = new QueueFake();
        $fake->push('Job');
        $fake->push('Job');
        $fake->assertPushedTimes('Job', 2);
        $this->assertTrue(true);
    }

    public function testQueueFakeAssertNothingPushed(): void
    {
        $fake = new QueueFake();
        $fake->assertNothingPushed();
        $this->assertTrue(true);
    }

    public function testQueueFakeFlush(): void
    {
        $fake = new QueueFake();
        $fake->push('SomeJob');
        $fake->flush();
        $fake->assertNothingPushed();
        $this->assertTrue(true);
    }

    // =========================================================================
    // MailFake
    // =========================================================================

    public function testMailFakeSendAndAssert(): void
    {
        $fake = new MailFake();
        $fake->send('welcome', ['to' => 'bob@x.com', 'subject' => 'Hi']);
        $fake->assertSent('welcome');
        $this->assertTrue(true);
    }

    public function testMailFakeAssertSentTo(): void
    {
        $fake = new MailFake();
        $fake->send('welcome', ['to' => 'carol@x.com']);
        $fake->assertSentTo('carol@x.com', 'welcome');
        $this->assertTrue(true);
    }

    public function testMailFakeAssertNotSent(): void
    {
        $fake = new MailFake();
        $fake->assertNotSent('invoice');
        $this->assertTrue(true);
    }

    public function testMailFakeAssertSentTimes(): void
    {
        $fake = new MailFake();
        $fake->send('newsletter', ['to' => 'a@b.com']);
        $fake->send('newsletter', ['to' => 'c@d.com']);
        $fake->assertSentTimes('newsletter', 2);
        $this->assertTrue(true);
    }

    public function testMailFakeAssertNothingSent(): void
    {
        $fake = new MailFake();
        $fake->assertNothingSent();
        $this->assertTrue(true);
    }

    public function testMailFakeFlush(): void
    {
        $fake = new MailFake();
        $fake->send('x', []);
        $fake->flush();
        $fake->assertNothingSent();
        $this->assertTrue(true);
    }

    public function testMailFakeHasSent(): void
    {
        $fake = new MailFake();
        $this->assertFalse($fake->hasSent('x'));
        $fake->send('x', []);
        $this->assertTrue($fake->hasSent('x'));
    }

    // =========================================================================
    // SimpleFaker
    // =========================================================================

    public function testSimpleFakerName(): void
    {
        $faker = new SimpleFaker();
        $name  = $faker->name();
        $this->assertFalse(empty($name));
        $this->assertTrue(str_contains($name, ' '));
    }

    public function testSimpleFakerEmail(): void
    {
        $faker = new SimpleFaker();
        $email = $faker->email();
        $this->assertTrue(str_contains($email, '@'));
    }

    public function testSimpleFakerNumberBetween(): void
    {
        $faker = new SimpleFaker();
        $n     = $faker->numberBetween(5, 10);
        $this->assertTrue($n >= 5 && $n <= 10);
    }

    public function testSimpleFakerUuid(): void
    {
        $faker = new SimpleFaker();
        $uuid  = $faker->uuid();
        $this->assertTrue((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid));
    }

    public function testSimpleFakerRandomString(): void
    {
        $faker = new SimpleFaker();
        $s     = $faker->randomString(20);
        $this->assertEquals(20, strlen($s));
    }

    public function testSimpleFakerRandomElement(): void
    {
        $faker  = new SimpleFaker();
        $items  = ['a', 'b', 'c'];
        $result = $faker->randomElement($items);
        $this->assertTrue(in_array($result, $items, true));
    }

    // =========================================================================
    // WithFaker trait (via TestCase)
    // =========================================================================

    public function testWithFakerHelperReturnsFaker(): void
    {
        $faker = $this->faker();
        $this->assertInstanceOf(SimpleFaker::class, $faker);
    }

    public function testWithFakerReturnsSameInstance(): void
    {
        $a = $this->faker();
        $b = $this->faker();
        $this->assertSame($a, $b);
    }

    // =========================================================================
    // ActingAs trait (via TestCase)
    // =========================================================================

    public function testActingAsStoresUser(): void
    {
        $this->actingAs(['id' => 99, 'name' => 'Tester']);
        $this->assertEquals(['id' => 99, 'name' => 'Tester'], $this->getActingUser());
        $this->clearActingAs();
    }

    public function testActingAsReturnsSelf(): void
    {
        $result = $this->actingAs(['id' => 1]);
        $this->assertSame($this, $result);
        $this->clearActingAs();
    }

    public function testClearActingAsNullsUser(): void
    {
        $this->actingAs(['id' => 1]);
        $this->clearActingAs();
        $this->assertNull($this->getActingUser());
    }

    // =========================================================================
    // TestCase new assertions
    // =========================================================================

    public function testAssertEmpty(): void
    {
        $this->assertEmpty([]);
        $this->assertTrue(true);
    }

    public function testAssertNotEmpty(): void
    {
        $this->assertNotEmpty([1]);
        $this->assertTrue(true);
    }

    public function testAssertGreaterThan(): void
    {
        $this->assertGreaterThan(5, 10);
        $this->assertTrue(true);
    }

    public function testAssertLessThan(): void
    {
        $this->assertLessThan(10, 5);
        $this->assertTrue(true);
    }

    public function testAssertContains(): void
    {
        $this->assertContains('b', ['a', 'b', 'c']);
        $this->assertTrue(true);
    }

    public function testAssertStringNotContainsString(): void
    {
        $this->assertStringNotContainsString('xyz', 'hello world');
        $this->assertTrue(true);
    }
}
