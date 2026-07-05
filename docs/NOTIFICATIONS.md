# Notifications

Nemesis provides a multi-channel notification system. Define a notification once; send it via any combination of log, database, mail, broadcast, Slack, and webhooks.

---

## Defining a Notification

```php
use Nemesis\Notifications\Notification;

class OrderShipped extends Notification
{
    public function __construct(private readonly Order $order) {}

    // Which channels to use
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    // Mail channel payload
    public function toMail(object $notifiable): array
    {
        return [
            'subject' => "Your order #{$this->order->getOrderNumber()} has shipped",
            'body'    => "Tracking: {$this->order->getTrackingNumber()}",
        ];
    }

    // Database channel payload (stored as JSON)
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'order_shipped',
            'order_number' => $this->order->getOrderNumber(),
            'tracking'     => $this->order->getTrackingNumber(),
        ];
    }
}
```

---

## Sending Notifications

### Via the Notification class (static)

```php
use App\Notifications\OrderShipped;

// Single notifiable
Notification::send($user, new OrderShipped($order));

// Multiple notifiables
Notification::sendToMany([$user1, $user2], new OrderShipped($order));
```

### Via the Notifiable trait

Add the `Notifiable` trait to any model:

```php
use Nemesis\Core\Model;
use Nemesis\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;
}
```

Then call directly on the model:

```php
$user->notify(new OrderShipped($order));
```

---

## Channels

### Log Channel (default)

Records notifications to the application log. Always available; useful during development.

```php
public function via(object $notifiable): array
{
    return ['log'];
}
```

### Database Channel

Stores notifications in a `notifications` table (auto-created). Useful for in-app notification centres.

```php
public function toDatabase(object $notifiable): array
{
    return ['message' => 'You have a new message.', 'from' => $sender->name];
}
```

Query a user's notifications:

```php
use Nemesis\Notifications\Channels\DatabaseChannel;

// All notifications for a notifiable
$all = DatabaseChannel::forNotifiable($user);

// Unread only
$unread = DatabaseChannel::unreadForNotifiable($user);

// Mark all read
DatabaseChannel::markAllRead($user);
```

Via the Notifiable trait:

```php
$user->notifications();            // all
$user->unreadNotifications();      // unread
$user->unreadNotificationCount();  // int
$user->markNotificationsRead();    // mark all read
```

### Mail Channel

```php
public function toMail(object $notifiable): array
{
    return [
        'to'      => $notifiable->email,  // optional override
        'subject' => 'Welcome!',
        'body'    => 'Thanks for joining.',
    ];
}
```

The mail channel uses the Nemesis Mailer if available, falling back to PHP's `mail()`.

**Route for mail** — add to your notifiable model:

```php
public function routeNotificationForMail(): string
{
    return $this->email;
}
```

### Broadcast Channel

Fires a `notification.sent` action via `HookDispatcher` and dispatches a typed event via `EventDispatcher`. Wire this to your WebSocket server.

```php
public function via(object $notifiable): array
{
    return ['broadcast'];
}

public function toBroadcast(object $notifiable): array
{
    return ['event' => 'order.shipped', 'data' => $this->order->toArray()];
}
```

### Slack Channel

```php
public function toSlack(object $notifiable): array
{
    return [
        'text'       => "Order #{$this->order->getOrderNumber()} shipped!",
        'username'   => 'Nemesis Bot',
        'icon_emoji' => ':package:',
    ];
}
```

**Route for Slack** — add to your notifiable model:

```php
public function routeNotificationForSlack(): string
{
    return 'https://hooks.slack.com/services/your-webhook-url';
}
```

### SMS Channel

Nemesis now includes a built-in SMS channel with fake/test helpers. Implement `toSms()` on the notification and optionally `routeNotificationForSms()` on the notifiable model.

```php
public function via(object $notifiable): array
{
    return ['sms'];
}

public function toSms(object $notifiable): array
{
    return ['message' => 'Your code is 123456'];
}
```

```php
public function routeNotificationForSms(): string
{
    return $this->phone;
}
```

Use `Nemesis\Notifications\Channels\SmsChannel::fake()` in tests to capture outgoing SMS payloads.

### Webhook Channel

POST (or PUT) a JSON payload to any URL:

```php
public function toWebhook(object $notifiable): array
{
    return [
        'url'     => 'https://api.example.com/hook',
        'method'  => 'POST',      // default POST
        'headers' => ['X-Token' => 'secret'],
        'payload' => $this->order->toArray(),
    ];
}
```

---

## Custom Channels

Register a custom channel:

```php
use Nemesis\Notifications\NotificationManager;
use Nemesis\Notifications\NotificationChannelInterface;

class SMSChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, \Nemesis\Notifications\Notification $notification): void
    {
        $data = $notification->toSms($notifiable);
        // Send SMS...
    }
}

// Register
NotificationManager::getInstance()->extend('sms', new SMSChannel());
```

Use it:

```php
public function via(object $notifiable): array
{
    return ['sms'];
}

public function toSms(object $notifiable): array
{
    return ['to' => $notifiable->phone, 'message' => 'Your order shipped!'];
}
```

---

## Fault Isolation

Each channel dispatch is wrapped in a `try/catch`. If one channel fails, the others still run. Failures are logged via the log channel automatically — your application will not crash due to a broken notification channel.

---

## Testing

### Fake Mail

```php
use Nemesis\Notifications\Channels\MailChannel;

MailChannel::fake();

$user->notify(new WelcomeNotification());

$sent = MailChannel::sent();
$last = MailChannel::lastSent();
MailChannel::assertSentTo('alice@example.com');
```

### Fake Slack / Webhook

```php
use Nemesis\Notifications\Channels\SlackChannel;

SlackChannel::fake();
$user->notify(new AlertNotification());
// SlackChannel::sent() for assertions
```

### Log Channel in Tests

```php
use Nemesis\Notifications\Channels\LogChannel;

LogChannel::fake();
$user->notify(new SomeNotification());

$entries = LogChannel::for(SomeNotification::class);
$last    = LogChannel::last();
```
