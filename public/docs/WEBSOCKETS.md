# WebSockets Documentation

## Overview

Nemesis includes a native WebSocket server for real-time, bidirectional communication between server and clients.

---

## Starting WebSocket Server

```bash
php nemesis websockets:serve

# Custom port
php nemesis websockets:serve --port=8080
```

---

## Broadcasting Events

### Server-Side

```php
use Nemesis\Broadcasting\Broadcaster;

$broadcaster = new Broadcaster();

// Broadcast to channel
$broadcaster->broadcast(['channel-1'], 'NewOrder', [
    'id' => 123,
    'total' => 99.99
]);

// Broadcast to multiple channels
$broadcaster->broadcast(['channel-1', 'channel-2'], 'Update', $data);
```

---

## Client-Side Integration

### JavaScript Client

```html
<script>
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = function() {
    console.log('Connected to WebSocket');
    
    // Subscribe to channel
    ws.send(JSON.stringify({
        action: 'subscribe',
        channel: 'channel-1'
    }));
};

ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Event:', data.event);
    console.log('Data:', data.data);
    
    if (data.event === 'NewOrder') {
        // Handle new order
        updateOrderList(data.data);
    }
};

ws.onerror = function(error) {
    console.error('WebSocket error:', error);
};

ws.onclose = function() {
    console.log('Disconnected from WebSocket');
};
</script>
```

---

## Channels

### Public Channels

```php
// Anyone can subscribe
$broadcaster->broadcast(['public-notifications'], 'Alert', $data);
```

### Private Channels

```php
// Require authentication
$broadcaster->broadcast(['private-user-' . $userId], 'Message', $data);
```

---

## Use Cases

- **Live notifications** - Real-time alerts
- **Chat applications** - Instant messaging
- **Live dashboards** - Real-time metrics
- **Collaborative editing** - Multi-user editing
- **Live updates** - Stock prices, sports scores

---

## Best Practices

1. **Use channels** - Organize events by topic
2. **Authenticate connections** - Verify user identity
3. **Handle reconnections** - Implement retry logic
4. **Limit payload size** - Keep messages small
5. **Use heartbeats** - Detect dead connections
