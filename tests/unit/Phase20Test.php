<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting / WebSocket Tests | Created: 2026-04-06

use Nemesis\Testing\TestCase;
use Nemesis\Broadcasting\Channel;
use Nemesis\Broadcasting\ChannelManager;
use Nemesis\Broadcasting\WebSocketFrame;
use Nemesis\Broadcasting\WebSocketServer;
use Nemesis\Broadcasting\BroadcastManager;
use Nemesis\Broadcasting\SseStream;
use Nemesis\Broadcasting\Contracts\ShouldBroadcast;

// =============================================================================
// Channel — type detection
// =============================================================================

class ChannelTypeTest extends TestCase
{
    public function testPublicChannel(): void
    {
        $ch = new Channel('orders');
        $this->assertTrue($ch->isPublic());
        $this->assertFalse($ch->isPrivate());
        $this->assertFalse($ch->isPresence());
        $this->assertEquals(Channel::TYPE_PUBLIC, $ch->type);
    }

    public function testPrivateChannel(): void
    {
        $ch = new Channel('private-orders');
        $this->assertTrue($ch->isPrivate());
        $this->assertFalse($ch->isPublic());
        $this->assertFalse($ch->isPresence());
        $this->assertEquals(Channel::TYPE_PRIVATE, $ch->type);
    }

    public function testPresenceChannel(): void
    {
        $ch = new Channel('presence-room.1');
        $this->assertTrue($ch->isPresence());
        $this->assertFalse($ch->isPublic());
        $this->assertFalse($ch->isPrivate());
        $this->assertEquals(Channel::TYPE_PRESENCE, $ch->type);
    }

    public function testRequiresAuthForPrivate(): void
    {
        $this->assertTrue((new Channel('private-orders'))->requiresAuth());
    }

    public function testRequiresAuthForPresence(): void
    {
        $this->assertTrue((new Channel('presence-chat'))->requiresAuth());
    }

    public function testDoesNotRequireAuthForPublic(): void
    {
        $this->assertFalse((new Channel('orders'))->requiresAuth());
    }

    public function testToStringReturnsName(): void
    {
        $this->assertEquals('private-orders', (string) new Channel('private-orders'));
    }

    public function testDetectTypeStatic(): void
    {
        $this->assertEquals(Channel::TYPE_PUBLIC,   Channel::detectType('news'));
        $this->assertEquals(Channel::TYPE_PRIVATE,  Channel::detectType('private-news'));
        $this->assertEquals(Channel::TYPE_PRESENCE, Channel::detectType('presence-news'));
    }
}

// =============================================================================
// ChannelManager — subscriptions
// =============================================================================

class ChannelManagerTest extends TestCase
{
    private function mgr(): ChannelManager { return new ChannelManager(); }

    public function testSubscribeAndIsSubscribed(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $this->assertTrue($m->isSubscribed('sock1', 'orders'));
    }

    public function testNotSubscribedInitially(): void
    {
        $m = $this->mgr();
        $this->assertFalse($m->isSubscribed('sock1', 'orders'));
    }

    public function testUnsubscribeRemovesSubscription(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->unsubscribe('sock1', 'orders');
        $this->assertFalse($m->isSubscribed('sock1', 'orders'));
    }

    public function testUnsubscribeAllRemovesFromAllChannels(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->subscribe('sock1', 'private-orders');
        $m->subscribe('sock1', 'news');
        $m->unsubscribeAll('sock1');

        $this->assertFalse($m->isSubscribed('sock1', 'orders'));
        $this->assertFalse($m->isSubscribed('sock1', 'private-orders'));
        $this->assertFalse($m->isSubscribed('sock1', 'news'));
    }

    public function testSubscribersListsAllSockets(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->subscribe('sock2', 'orders');
        $m->subscribe('sock3', 'orders');

        $subs = $m->subscribers('orders');
        $this->assertCount(3, $subs);
        $this->assertContains('sock1', $subs);
        $this->assertContains('sock2', $subs);
        $this->assertContains('sock3', $subs);
    }

    public function testSubscribersEmptyForUnknownChannel(): void
    {
        $m = $this->mgr();
        $this->assertEmpty($m->subscribers('nonexistent'));
    }

    public function testSubscriberCountAfterUnsubscribe(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->subscribe('sock2', 'orders');
        $this->assertEquals(2, $m->subscriberCount('orders'));

        $m->unsubscribe('sock1', 'orders');
        $this->assertEquals(1, $m->subscriberCount('orders'));
    }

    public function testChannelsForSocket(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->subscribe('sock1', 'news');
        $m->subscribe('sock1', 'private-admin');

        $channels = $m->channelsFor('sock1');
        $this->assertCount(3, $channels);
        $this->assertContains('orders', $channels);
        $this->assertContains('news', $channels);
    }

    public function testChannelsListsAllActive(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'a');
        $m->subscribe('sock2', 'b');
        $this->assertContains('a', $m->channels());
        $this->assertContains('b', $m->channels());
    }

    public function testChannelRemovedWhenLastSubscriberLeaves(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->unsubscribe('sock1', 'orders');
        $this->assertNotContains('orders', $m->channels());
    }

    public function testPresenceMeta(): void
    {
        $m = $this->mgr();
        $meta = ['user_id' => 42, 'user_info' => ['name' => 'Alice']];
        $m->subscribe('sock1', 'presence-chat', $meta);

        $stored = $m->memberMeta('sock1', 'presence-chat');
        $this->assertEquals(42, $stored['user_id']);
    }

    public function testPresenceMembers(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'presence-chat', ['user_id' => 1]);
        $m->subscribe('sock2', 'presence-chat', ['user_id' => 2]);

        $members = $m->presenceMembers('presence-chat');
        $this->assertCount(2, $members);
        $this->assertArrayHasKey('sock1', $members);
        $this->assertArrayHasKey('sock2', $members);
    }

    public function testReset(): void
    {
        $m = $this->mgr();
        $m->subscribe('sock1', 'orders');
        $m->reset();
        $this->assertEmpty($m->channels());
    }
}

// =============================================================================
// ChannelManager — authorization callbacks
// =============================================================================

class ChannelManagerAuthTest extends TestCase
{
    public function testPublicChannelAlwaysAuthorized(): void
    {
        $m = new ChannelManager();
        $this->assertTrue($m->isAuthorized('sock1', 'orders'));
    }

    public function testPrivateChannelDeniedWithNoCallback(): void
    {
        $m = new ChannelManager();
        $this->assertFalse($m->isAuthorized('sock1', 'private-orders'));
    }

    public function testPrivateChannelAllowedByCallback(): void
    {
        $m = new ChannelManager();
        $m->authChannel('private-orders', fn($sid, $ch, $uid) => true);
        $this->assertTrue($m->isAuthorized('sock1', 'private-orders'));
    }

    public function testPrivateChannelDeniedByCallback(): void
    {
        $m = new ChannelManager();
        $m->authChannel('private-orders', fn($sid, $ch, $uid) => false);
        $this->assertFalse($m->isAuthorized('sock1', 'private-orders'));
    }

    public function testWildcardPatternMatchesMultipleChannels(): void
    {
        $m = new ChannelManager();
        $m->authChannel('private-orders.*', fn($sid, $ch, $uid) => true);
        $this->assertTrue($m->isAuthorized('sock1', 'private-orders.123'));
        $this->assertTrue($m->isAuthorized('sock1', 'private-orders.456'));
    }

    public function testCallbackReceivesSocketIdAndChannel(): void
    {
        $m        = new ChannelManager();
        $captured = [];
        $m->authChannel('private-orders', function($sid, $ch, $uid) use (&$captured) {
            $captured = ['sid' => $sid, 'ch' => $ch];
            return true;
        });
        $m->isAuthorized('mysocket', 'private-orders', 99);
        $this->assertEquals('mysocket', $captured['sid']);
        $this->assertEquals('private-orders', $captured['ch']);
    }
}

// =============================================================================
// WebSocketFrame — encode / decode (RFC 6455)
// =============================================================================

class WebSocketFrameEncodeTest extends TestCase
{
    public function testEncodeShortTextFrame(): void
    {
        $frame = WebSocketFrame::encode('hello');
        // Byte 0: 0x81 (FIN=1, opcode=0x1 text)
        $this->assertEquals(0x81, ord($frame[0]));
        // Byte 1: 0x05 (no mask, len=5)
        $this->assertEquals(5, ord($frame[1]));
        $this->assertEquals('hello', substr($frame, 2));
    }

    public function testEncode126BytePayload(): void
    {
        $payload = str_repeat('A', 126);
        $frame   = WebSocketFrame::encode($payload);
        $this->assertEquals(0x81, ord($frame[0]));
        $this->assertEquals(126,  ord($frame[1]));
        // Extended length: 2 bytes big-endian
        $this->assertEquals(126,  unpack('n', substr($frame, 2, 2))[1]);
        $this->assertEquals($payload, substr($frame, 4));
    }

    public function testEncodeJsonFrame(): void
    {
        $frame = WebSocketFrame::encodeJson(['event' => 'test', 'data' => '{}']);
        $this->assertEquals(0x81, ord($frame[0])); // text frame
        $decoded = json_decode(substr($frame, 2, ord($frame[1])), true);
        $this->assertEquals('test', $decoded['event']);
    }

    public function testEncodePingFrame(): void
    {
        $frame = WebSocketFrame::ping('ping-payload');
        $this->assertEquals(0x89, ord($frame[0])); // FIN=1, opcode=0x9
    }

    public function testEncodePongFrame(): void
    {
        $frame = WebSocketFrame::pong('pong-payload');
        $this->assertEquals(0x8A, ord($frame[0])); // FIN=1, opcode=0xA
        $this->assertEquals('pong-payload', substr($frame, 2));
    }

    public function testEncodeCloseFrame(): void
    {
        $frame = WebSocketFrame::close(1000, 'Normal closure');
        $this->assertEquals(0x88, ord($frame[0])); // FIN=1, opcode=0x8
        // First 2 bytes of payload = status code
        $code = unpack('n', substr($frame, 2, 2))[1];
        $this->assertEquals(1000, $code);
    }
}

class WebSocketFrameDecodeTest extends TestCase
{
    /** Build a masked client frame (clients MUST mask per RFC 6455). */
    private function buildMaskedFrame(string $payload, int $opcode = 0x1): string
    {
        $mask = random_bytes(4);
        $masked = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $masked .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        $len   = strlen($payload);
        $frame = chr(0x80 | $opcode);
        $frame .= chr(0x80 | $len); // MASK bit set
        $frame .= $mask . $masked;
        return $frame;
    }

    public function testDecodeMaskedTextFrame(): void
    {
        $payload = 'hello world';
        $raw     = $this->buildMaskedFrame($payload);
        $frame   = WebSocketFrame::decode($raw);

        $this->assertNotNull($frame);
        $this->assertEquals(WebSocketFrame::OP_TEXT, $frame['opcode']);
        $this->assertEquals($payload, $frame['payload']);
        $this->assertTrue($frame['masked']);
        $this->assertTrue($frame['fin']);
    }

    public function testDecodeReturnsNullForIncompleteData(): void
    {
        $this->assertNull(WebSocketFrame::decode(''));
        $this->assertNull(WebSocketFrame::decode("\x81")); // only 1 byte
    }

    public function testDecodeRoundTrip(): void
    {
        $payload = '{"event":"pusher:subscribe","data":{"channel":"orders"}}';
        $raw     = $this->buildMaskedFrame($payload);
        $frame   = WebSocketFrame::decode($raw);
        $this->assertEquals($payload, $frame['payload']);
    }

    public function testDecodeRawLenAccountsForAllBytes(): void
    {
        $payload = 'test';
        $raw     = $this->buildMaskedFrame($payload);
        $frame   = WebSocketFrame::decode($raw);
        $this->assertEquals(strlen($raw), $frame['raw_len']);
    }

    public function testEncodeDecodeRoundTripUnmasked(): void
    {
        // Server sends unmasked frames
        $payload = 'server→client message';
        $encoded = WebSocketFrame::encode($payload);
        $decoded = WebSocketFrame::decode($encoded);

        $this->assertNotNull($decoded);
        $this->assertEquals($payload, $decoded['payload']);
        $this->assertFalse($decoded['masked']);
    }
}

// =============================================================================
// WebSocketFrame — handshake
// =============================================================================

class WebSocketHandshakeTest extends TestCase
{
    public function testComputeAcceptKey(): void
    {
        // RFC 6455 example from the spec
        $clientKey  = 'dGhlIHNhbXBsZSBub25jZQ==';
        $expected   = 's3pPLMBiTxaQ9kYGzzhZRbK+xOo=';
        $actual     = WebSocketFrame::computeAcceptKey($clientKey);
        $this->assertEquals($expected, $actual);
    }

    public function testBuildHandshakeResponseContainsUpgrade(): void
    {
        $response = WebSocketFrame::buildHandshakeResponse('dGhlIHNhbXBsZSBub25jZQ==');
        $this->assertStringContains('101 Switching Protocols', $response);
        $this->assertStringContains('Upgrade: websocket', $response);
        $this->assertStringContains('Connection: Upgrade', $response);
        $this->assertStringContains('Sec-WebSocket-Accept:', $response);
    }

    public function testHandshakeResponseEndsWithDoubleCrLf(): void
    {
        $response = WebSocketFrame::buildHandshakeResponse('test-key');
        $this->assertStringEndsWith("\r\n\r\n", $response);
    }

    public function testParseHandshakeHeaders(): void
    {
        $raw = "GET /ws HTTP/1.1\r\n"
             . "Host: localhost:6001\r\n"
             . "Upgrade: websocket\r\n"
             . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
             . "Sec-WebSocket-Version: 13\r\n"
             . "\r\n";

        $headers = WebSocketFrame::parseHandshakeHeaders($raw);
        $this->assertEquals('websocket',                    $headers['upgrade']);
        $this->assertEquals('dGhlIHNhbXBsZSBub25jZQ==',    $headers['sec-websocket-key']);
        $this->assertEquals('13',                           $headers['sec-websocket-version']);
        $this->assertEquals('localhost:6001',               $headers['host']);
    }
}

// =============================================================================
// WebSocketServer — channel auth
// =============================================================================

class WebSocketServerAuthTest extends TestCase
{
    public function testComputeChannelAuthMatchesPusherFormat(): void
    {
        $server = new WebSocketServer('127.0.0.1', 6001, 'my-key', 'my-secret');

        $socketId = '123456.654321';
        $channel  = 'private-orders';

        $auth      = $server->computeChannelAuth($socketId, $channel);
        $expected  = 'my-key:' . hash_hmac('sha256', "{$socketId}:{$channel}", 'my-secret');

        $this->assertEquals($expected, $auth);
    }

    public function testChannelAuthResponseForPrivateChannel(): void
    {
        $server = new WebSocketServer('127.0.0.1', 6001, 'my-key', 'my-secret');
        $result = $server->channelAuthResponse('123456.654321', 'private-orders');

        $this->assertArrayHasKey('auth', $result);
        $this->assertStringStartsWith('my-key:', $result['auth']);
    }

    public function testChannelAuthResponseForPresenceChannel(): void
    {
        $server = new WebSocketServer('127.0.0.1', 6001, 'my-key', 'my-secret');
        $result = $server->channelAuthResponse('123456.654321', 'presence-chat', [
            'id' => 42, 'name' => 'Alice',
        ]);

        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayHasKey('channel_data', $result);
        $channelData = json_decode($result['channel_data'], true);
        $this->assertEquals(42, $channelData['user_id']);
    }

    public function testChannelAuthResponseForPublicChannel(): void
    {
        $server = new WebSocketServer('127.0.0.1', 6001, 'my-key', 'my-secret');
        $result = $server->channelAuthResponse('123456.654321', 'orders');
        $this->assertEquals('', $result['auth']);
    }

    public function testDifferentSecretsProduceDifferentAuth(): void
    {
        $s1 = new WebSocketServer('127.0.0.1', 6001, 'key', 'secret-a');
        $s2 = new WebSocketServer('127.0.0.1', 6001, 'key', 'secret-b');

        $a1 = $s1->computeChannelAuth('sock.1', 'private-ch');
        $a2 = $s2->computeChannelAuth('sock.1', 'private-ch');
        $this->assertNotEquals($a1, $a2);
    }
}

// =============================================================================
// BroadcastManager — fake + assertions
// =============================================================================

class BroadcastManagerTest extends TestCase
{
    public function setUp(): void  { BroadcastManager::fake(); }
    public function tearDown(): void { BroadcastManager::resetFakes(); }

    public function testBroadcastToChannel(): void
    {
        BroadcastManager::to('orders')->event('OrderUpdated', ['order_id' => 1]);
        BroadcastManager::assertBroadcastOn('orders', 'OrderUpdated');
    }

    public function testBroadcastToChannels(): void
    {
        BroadcastManager::toChannels(['orders', 'private-admin'])->event('OrderCreated', []);
        BroadcastManager::assertBroadcastOn('orders', 'OrderCreated');
        BroadcastManager::assertBroadcastOn('private-admin', 'OrderCreated');
    }

    public function testAssertBroadcastOnFailsWhenNotSent(): void
    {
        $this->expectException(\RuntimeException::class);
        BroadcastManager::assertBroadcastOn('orders', 'MissingEvent');
    }

    public function testAssertNothingBroadcastPasses(): void
    {
        BroadcastManager::assertNothingBroadcast(); // no events sent yet
    }

    public function testAssertNothingBroadcastFails(): void
    {
        BroadcastManager::to('orders')->event('SomeEvent', []);
        $this->expectException(\RuntimeException::class);
        BroadcastManager::assertNothingBroadcast();
    }

    public function testAssertBroadcastCount(): void
    {
        BroadcastManager::to('a')->event('EventA', []);
        BroadcastManager::to('b')->event('EventB', []);
        BroadcastManager::assertBroadcastCount(2);
    }

    public function testAssertBroadcastCountFails(): void
    {
        BroadcastManager::to('a')->event('E', []);
        $this->expectException(\RuntimeException::class);
        BroadcastManager::assertBroadcastCount(5);
    }

    public function testAssertBroadcastCallback(): void
    {
        BroadcastManager::to('orders')->event('OrderUpdated', ['order_id' => 99]);
        BroadcastManager::assertBroadcast(
            fn($r) => $r['channel'] === 'orders' && $r['payload']['order_id'] === 99
        );
    }

    public function testRecordedContainsAllEvents(): void
    {
        BroadcastManager::to('ch1')->event('E1', ['x' => 1]);
        BroadcastManager::to('ch2')->event('E2', ['x' => 2]);
        $recorded = BroadcastManager::recorded();
        $this->assertCount(2, $recorded);
        $this->assertEquals('ch1', $recorded[0]['channel']);
        $this->assertEquals('ch2', $recorded[1]['channel']);
    }

    public function testResetFakesClearsAll(): void
    {
        BroadcastManager::to('orders')->event('E', []);
        BroadcastManager::resetFakes();
        $this->assertFalse(BroadcastManager::isFaking());
        $this->assertEmpty(BroadcastManager::recorded());
    }

    public function testDispatchShouldBroadcastEvent(): void
    {
        $event = new class implements ShouldBroadcast {
            public function broadcastOn(): string  { return 'orders'; }
            public function broadcastAs(): string  { return 'OrderShipped'; }
            public function broadcastWith(): array { return ['order_id' => 7]; }
        };

        BroadcastManager::dispatch($event);
        BroadcastManager::assertBroadcastOn('orders', 'OrderShipped');
        BroadcastManager::assertBroadcast(fn($r) => $r['payload']['order_id'] === 7);
    }
}

// =============================================================================
// SSE Stream — format helpers
// =============================================================================

class SseStreamTest extends TestCase
{
    public function testFormatArrayDataAsJson(): void
    {
        $out = SseStream::format(['order_id' => 1], 'OrderUpdated', '42');
        $this->assertStringContains('id: 42', $out);
        $this->assertStringContains('event: OrderUpdated', $out);
        $this->assertStringContains('data: {"order_id":1}', $out);
        $this->assertStringEndsWith("\n\n", $out);
    }

    public function testFormatStringData(): void
    {
        $out = SseStream::format('hello world');
        $this->assertStringContains('data: hello world', $out);
        $this->assertStringEndsWith("\n\n", $out);
    }

    public function testFormatWithoutEventOrId(): void
    {
        $out = SseStream::format('ping');
        $this->assertFalse(str_contains($out, 'event:'));
        $this->assertFalse(str_contains($out, 'id:'));
        $this->assertStringContains('data: ping', $out);
    }

    public function testFormatWithEventOnly(): void
    {
        $out = SseStream::format('test', 'MyEvent');
        $this->assertStringContains('event: MyEvent', $out);
        $this->assertFalse(str_contains($out, 'id:'));
    }

    public function testFormatRetry(): void
    {
        $out = SseStream::formatRetry(3000);
        $this->assertEquals("retry: 3000\n\n", $out);
    }

    public function testFormatKeepAlive(): void
    {
        $out = SseStream::formatKeepAlive();
        $this->assertEquals(": keep-alive\n\n", $out);
    }

    public function testFormatKeepAliveCustomComment(): void
    {
        $out = SseStream::formatKeepAlive('heartbeat');
        $this->assertEquals(": heartbeat\n\n", $out);
    }

    public function testMultilineDataEachLineHasPrefix(): void
    {
        $out = SseStream::format("line1\nline2\nline3");
        $lines = explode("\n", trim($out));
        $dataLines = array_filter($lines, fn($l) => str_starts_with($l, 'data:'));
        $this->assertCount(3, $dataLines);
    }
}
