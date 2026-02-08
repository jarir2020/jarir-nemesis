# Broadcasting Documentation

Broadcasting allows you to send events from your server to your client-side application in real-time.

---

## Configuration

Nemesis supports multiple broadcasters. The default is `LogBroadcaster`, which writes events to `storage/logs/broadcast.log`.

### Real-time WebSockets
To use real-time WebSockets, you can start the built-in server:

```bash
php nemesis websocket:serve 8080
```

---

## Defining Events

Any event you wish to broadcast should implement a pattern that accepts payload data.

### Example Usage

```php
use Nemesis\Broadcasting\LogBroadcaster;

$broadcaster = new LogBroadcaster();
$broadcaster->broadcast(['news', 'alerts'], 'UserLoggedIn', ['user_id' => 1]);
```

---

## Built-in WebSocket Server

The `WebSocketServer` class (located in `src/Broadcasting`) provides a basic implementation for handling incoming socket connections and routing messages to channels.

### Starting the Server
```bash
php nemesis websocket:serve
```

### Client-side Connection
You can connect to the server using standard JavaScript WebSockets:

```javascript
const socket = new WebSocket('ws://localhost:8080');

socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};
```
