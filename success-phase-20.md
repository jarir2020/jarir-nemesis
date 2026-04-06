# Phase 20 — Real-time / WebSockets ✓

**Completed:** 2026-04-06  
**Tests:** 1037 total / 1037 passed (66 new in Phase 20)  
**Branch:** main

---

## What Was Built

| File | Purpose |
|---|---|
| `src/Broadcasting/Contracts/BroadcasterContract.php` | Typed broadcaster interface |
| `src/Broadcasting/Contracts/ShouldBroadcast.php` | Event interface for dispatchable events |
| `src/Broadcasting/Channel.php` | Value object: type detection, requiresAuth() |
| `src/Broadcasting/ChannelManager.php` | Subscription tracking, presence members, auth callbacks |
| `src/Broadcasting/WebSocketFrame.php` | RFC 6455 frame encoder/decoder + handshake |
| `src/Broadcasting/WebSocketServer.php` | Full server: event loop, Pusher protocol, auth |
| `src/Broadcasting/SseStream.php` | SSE fallback broadcaster |
| `src/Broadcasting/BroadcastManager.php` | Static facade + fake/assertion system |

---

## Channel Types

```php
new Channel('orders')           // public  — no auth needed
new Channel('private-orders')   // private — requires HMAC auth
new Channel('presence-chat.1')  // presence — auth + user metadata
```

## CLI

```bash
php nemesis websocket:start
php nemesis websocket:start --host=0.0.0.0 --port=6001
```

## Frontend (pusher-js + Laravel Echo)

```javascript
const pusher = new Pusher('nemesis-key', {
    wsHost: 'localhost',
    wsPort: 6001,
    forceTLS: false,
    cluster: 'mt1',
    authEndpoint: '/broadcasting/auth',
});

// Public channel
const orders = pusher.subscribe('orders');
orders.bind('OrderUpdated', data => console.log(data));

// Private channel (requires auth endpoint)
const myOrders = pusher.subscribe('private-orders');
myOrders.bind('OrderShipped', data => console.log(data));
```

## Broadcasting (server-side)

```php
// Fluent API
Broadcast::to('orders')->event('OrderUpdated', ['order_id' => 1]);
Broadcast::toChannels(['orders', 'private-admin'])->event('OrderCreated', $data);

// ShouldBroadcast interface
class OrderShipped implements ShouldBroadcast {
    public function broadcastOn(): string  { return 'orders'; }
    public function broadcastAs(): string  { return 'OrderShipped'; }
    public function broadcastWith(): array { return ['order_id' => $this->order->id]; }
}
Broadcast::dispatch(new OrderShipped($order));
```

## Auth Endpoint

```php
// POST /broadcasting/auth
public function auth(Request $request): Response
{
    $server   = new WebSocketServer(appKey: env('WS_APP_KEY'), appSecret: env('WS_APP_SECRET'));
    $socketId = $request->input('socket_id');
    $channel  = $request->input('channel_name');
    $user     = AuthManager::user($request);

    if (!$user) return Response::json(['error' => 'Unauthorized'], 401);

    return Response::json($server->channelAuthResponse($socketId, $channel, $user));
}
```

## SSE Fallback

```php
$sse = new SseStream();
$sse->headers();
$sse->retry(3000);
while (true) {
    $sse->send(['order_id' => 1], 'OrderUpdated', '42');
    $sse->keepAlive();
    sleep(1);
}
```

## Testing

```php
Broadcast::fake();
// ... trigger code ...
Broadcast::assertBroadcastOn('orders', 'OrderUpdated');
Broadcast::assertBroadcastCount(1);
Broadcast::assertBroadcast(fn($r) => $r['payload']['order_id'] === 1);
Broadcast::assertNothingBroadcast();
Broadcast::resetFakes();
```
