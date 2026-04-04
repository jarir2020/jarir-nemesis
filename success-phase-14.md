# Phase 14 — Notifications + Full-Text Search ✓

**Completed:** 2026-04-04
**Tests:** 40 new tests — all passing
**Cumulative suite:** 683/683 passing (0 failures)

---

## Deliverables

### Notification Center — `src/Notifications/`

**`Notification.php`** — Abstract base; override `via()` + channel methods

```php
class OrderShipped extends Notification {
    public function via(object $n): array      { return ['mail', 'database']; }
    public function toMail(object $n): array   { return ['subject'=>'Shipped!','body'=>'...']; }
    public function toDatabase(object $n): array { return ['order_id' => $this->id]; }
}

Notification::send($user, new OrderShipped($order));
Notification::sendToMany($users, new OrderShipped($order));
```

**`NotificationManager.php`** — Singleton dispatcher; routes to channels; swallows per-channel failures so one bad channel never blocks others. `extend()` to register custom channels.

**`NotificationChannelInterface.php`** — `send(object $notifiable, Notification $n): void`

**`Notifiable.php`** (trait) — add to any Model:

```php
class User extends Model { use Notifiable; }

$user->notify(new OrderShipped($order));
$user->notifications();             // all DB notifications
$user->unreadNotifications();       // unread only
$user->unreadNotificationCount();   // integer
$user->markNotificationsRead();
$user->routeNotificationForMail();  // override to customise address
```

**Channels:**

| Channel | File | Key method |
|---------|------|-----------|
| `log` | `Channels/LogChannel.php` | In-memory log, `for()`, `last()`, `reset()` — perfect for tests |
| `database` | `Channels/DatabaseChannel.php` | Auto-creates `notifications` table; `forNotifiable()`, `markAllRead()` |
| `mail` | `Channels/MailChannel.php` | Uses Nemesis Mailer / php mail(); `fake()` mode for tests |
| `broadcast` | `Channels/BroadcastChannel.php` | Fires `notification.sent` hook + EventDispatcher |
| `slack` | `Channels/SlackChannel.php` | POST to Incoming Webhook URL; `fake()` mode |
| `webhook` | `Channels/WebhookChannel.php` | POST/PUT to arbitrary HTTP endpoint; `fake()` mode |

`config/notifications.php` — default channels, mail from, Slack webhook URL.

---

### Full-Text Search — `src/Search/`

**`SearchEngine.php`** — Static facade

```php
SearchEngine::setDriver('database'); // 'null' | 'database' | 'meilisearch'

SearchEngine::index(Post::class, 42, ['title' => 'Hello', 'body' => '...']);
SearchEngine::remove(Post::class, 42);
SearchEngine::flush(Post::class);

$results = SearchEngine::query('hello world')
    ->in([Post::class, Page::class])
    ->limit(10)
    ->get();
// → [['model'=>'...', 'id'=>42, 'score'=>2.0, 'data'=>[...]]]
```

**`SearchQuery.php`** — Fluent builder: `in()`, `limit()`, `where()`, `get()`, `first()`, `count()`

**`Searchable.php`** (trait) — add to any Model:

```php
class Post extends Model {
    use Searchable;
    public function toSearchArray(): array { return ['title'=>$this->title,'body'=>$this->body]; }
}

$post->searchIndex();            // manual index
$post->searchRemove();           // remove from index
Post::search('hello')->get();    // scoped to Post
Post::flushSearchIndex();
```

**Drivers:**

| Driver | File | Notes |
|--------|------|-------|
| `NullDriver` | `Drivers/NullDriver.php` | In-memory substring match, scores by occurrence count |
| `DatabaseDriver` | `Drivers/DatabaseDriver.php` | `search_indexes` table (LIKE fallback), auto-create table, memory fallback |
| `MeiliSearchDriver` | `Drivers/MeiliSearchDriver.php` | REST API, falls back to NullDriver on unreachable host |

`config/search.php` — driver, database table, MeiliSearch/Typesense host + key.

---

## Test Coverage (40 tests)

| Group | Tests |
|-------|-------|
| Phase14NotificationTest | 8 |
| Phase14NotifiableTest | 6 |
| Phase14LogChannelTest | 4 |
| Phase14SearchEngineTest | 6 |
| Phase14SearchQueryTest | 7 |
| Phase14SearchableTraitTest | 4 |
| Phase14NullDriverTest | 5 |

---

## Next: Phase 15 — E-Commerce Plugin Bundle (FINAL PHASE)
- Payment Gateway abstraction (Stripe, PayPal, manual)
- Product Catalog (products, variants, attributes)
- Cart & Checkout
- Order Management + Invoice PDF
- Inventory & Stock
