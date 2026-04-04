<?php
declare(strict_types=1);

// Nemesis 5.0.0 — Phase 14 Test Suite | Created: 2026-04-03
// Tests: Notification system (channels, Notifiable), Full-Text Search (engine, drivers, Searchable)

use Nemesis\Testing\TestCase;
use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationManager;
use Nemesis\Notifications\Notifiable;
use Nemesis\Notifications\Channels\LogChannel;
use Nemesis\Notifications\Channels\DatabaseChannel;
use Nemesis\Notifications\Channels\MailChannel;
use Nemesis\Notifications\Channels\BroadcastChannel;
use Nemesis\Search\SearchEngine;
use Nemesis\Search\SearchQuery;
use Nemesis\Search\Searchable;
use Nemesis\Search\Drivers\NullDriver;
use Nemesis\Search\Drivers\DatabaseDriver;

// ===========================================================================
// Stubs
// ===========================================================================

class Phase14_User
{
    use Notifiable;
    public int    $id    = 1;
    public string $email = 'user@example.com';
}

class Phase14_OrderShippedNotification extends Notification
{
    public function __construct(private int $orderId) {}

    public function via(object $notifiable): array { return ['log', 'database']; }

    public function toMail(object $notifiable): array {
        return ['subject' => 'Order #' . $this->orderId . ' shipped!', 'body' => 'It is on the way.'];
    }

    public function toDatabase(object $notifiable): array {
        return ['order_id' => $this->orderId, 'status' => 'shipped'];
    }
}

class Phase14_WelcomeNotification extends Notification
{
    public function via(object $notifiable): array { return ['mail', 'log']; }

    public function toMail(object $notifiable): array {
        return ['subject' => 'Welcome!', 'body' => 'Thanks for joining.'];
    }

    public function toDatabase(object $notifiable): array {
        return ['message' => 'Welcome'];
    }
}

class Phase14_PostModel
{
    use Searchable;
    public int    $id    = 0;
    public string $title = '';
    public string $body  = '';

    public function __construct(int $id, string $title, string $body = '')
    {
        $this->id    = $id;
        $this->title = $title;
        $this->body  = $body;
    }

    public function toSearchArray(): array
    {
        return ['title' => $this->title, 'body' => $this->body];
    }
}

// ===========================================================================
// Phase14NotificationTest — Notification base + NotificationManager
// ===========================================================================

class Phase14NotificationTest extends TestCase
{
    public function setUp(): void
    {
        NotificationManager::reset();
        MailChannel::reset();
    }

    public function testSendFiresLogChannel(): void
    {
        $user = new Phase14_User();
        Notification::send($user, new Phase14_OrderShippedNotification(42));

        $this->assertSame(1, LogChannel::count());
        $entry = LogChannel::last();
        $this->assertSame(Phase14_OrderShippedNotification::class, $entry['notification']);
    }

    public function testSendFiresDatabaseChannel(): void
    {
        $user = new Phase14_User();
        Notification::send($user, new Phase14_OrderShippedNotification(99));

        $stored = DatabaseChannel::all();
        $this->assertCount(1, $stored);
        $this->assertSame(99, $stored[0]['data']['order_id'] ?? null);
    }

    public function testSendToMany(): void
    {
        $users = [new Phase14_User(), new Phase14_User()];
        $users[0]->id = 1;
        $users[1]->id = 2;

        Notification::sendToMany($users, new Phase14_WelcomeNotification());
        $this->assertSame(2, LogChannel::count());
    }

    public function testViaDefaultsToLog(): void
    {
        $n = new class extends Notification {};
        $this->assertSame(['log'], $n->via(new Phase14_User()));
    }

    public function testToMailReturnsEmptyByDefault(): void
    {
        $n = new class extends Notification {};
        $this->assertSame([], $n->toMail(new Phase14_User()));
    }

    public function testMailChannelFakeRecordsEmail(): void
    {
        MailChannel::fake();

        $mgr = NotificationManager::getInstance();
        $mgr->extend('mail', new MailChannel());

        $user = new Phase14_User();
        Notification::send($user, new Phase14_WelcomeNotification());

        $this->assertTrue(MailChannel::assertSentTo('user@example.com'));
        $this->assertSame('Welcome!', MailChannel::lastSent()['subject'] ?? '');
    }

    public function testChannelFailureDoesNotBlockOthers(): void
    {
        // Replace 'log' with a channel that always throws
        $mgr = NotificationManager::getInstance();
        $mgr->extend('log', new class implements \Nemesis\Notifications\NotificationChannelInterface {
            public function send(object $n, Notification $notif): void { throw new \RuntimeException('boom'); }
        });
        $mgr->extend('database', new \Nemesis\Notifications\Channels\DatabaseChannel());

        $user = new Phase14_User();
        Notification::send($user, new Phase14_OrderShippedNotification(1));

        // Database channel still ran
        $this->assertCount(1, DatabaseChannel::all());
    }

    public function testManagerChannelNamesIncludeBuiltIns(): void
    {
        $names = NotificationManager::getInstance()->channelNames();
        $this->assertContains('mail',      $names);
        $this->assertContains('database',  $names);
        $this->assertContains('log',       $names);
        $this->assertContains('broadcast', $names);
    }
}

// ===========================================================================
// Phase14NotifiableTest — Notifiable trait on User stub
// ===========================================================================

class Phase14NotifiableTest extends TestCase
{
    public function setUp(): void
    {
        NotificationManager::reset();
    }

    public function testNotifyViaTraitFiresChannels(): void
    {
        $user = new Phase14_User();
        $user->notify(new Phase14_OrderShippedNotification(7));
        $this->assertSame(1, LogChannel::count());
    }

    public function testNotificationsReturnsDbRecords(): void
    {
        $user = new Phase14_User();
        $user->notify(new Phase14_OrderShippedNotification(10));
        $user->notify(new Phase14_OrderShippedNotification(11));

        $notifs = $user->notifications();
        $this->assertCount(2, $notifs);
    }

    public function testUnreadNotificationsInitiallyAll(): void
    {
        $user = new Phase14_User();
        $user->notify(new Phase14_OrderShippedNotification(20));

        $this->assertSame(1, $user->unreadNotificationCount());
    }

    public function testMarkNotificationsRead(): void
    {
        $user = new Phase14_User();
        $user->notify(new Phase14_OrderShippedNotification(30));
        $this->assertSame(1, $user->unreadNotificationCount());

        $user->markNotificationsRead();
        $this->assertSame(0, $user->unreadNotificationCount());
    }

    public function testUnreadNotificationsAfterReadIsEmpty(): void
    {
        $user = new Phase14_User();
        $user->notify(new Phase14_OrderShippedNotification(40));
        $user->markNotificationsRead();

        $this->assertCount(0, $user->unreadNotifications());
    }

    public function testRouteNotificationForMail(): void
    {
        $user = new Phase14_User();
        $this->assertSame('user@example.com', $user->routeNotificationForMail());
    }
}

// ===========================================================================
// Phase14LogChannelTest
// ===========================================================================

class Phase14LogChannelTest extends TestCase
{
    public function setUp(): void { LogChannel::reset(); }

    public function testLogRecordsEntry(): void
    {
        $chan = new LogChannel();
        $user = new Phase14_User();
        $chan->send($user, new Phase14_OrderShippedNotification(1));
        $this->assertSame(1, LogChannel::count());
    }

    public function testLogForClass(): void
    {
        $chan = new LogChannel();
        $user = new Phase14_User();
        $chan->send($user, new Phase14_OrderShippedNotification(1));
        $chan->send($user, new Phase14_WelcomeNotification());

        $entries = LogChannel::for(Phase14_OrderShippedNotification::class);
        $this->assertCount(1, $entries);
    }

    public function testLogLast(): void
    {
        $chan = new LogChannel();
        $user = new Phase14_User();
        $chan->send($user, new Phase14_WelcomeNotification());
        $last = LogChannel::last();
        $this->assertSame(Phase14_WelcomeNotification::class, $last['notification']);
    }

    public function testResetClearsLog(): void
    {
        $chan = new LogChannel();
        $user = new Phase14_User();
        $chan->send($user, new Phase14_WelcomeNotification());
        LogChannel::reset();
        $this->assertSame(0, LogChannel::count());
    }
}

// ===========================================================================
// Phase14SearchEngineTest
// ===========================================================================

class Phase14SearchEngineTest extends TestCase
{
    public function setUp(): void
    {
        SearchEngine::reset();
    }

    public function testIndexAndSearchBasic(): void
    {
        SearchEngine::index('Post', 1, ['title' => 'Hello World', 'body' => 'PHP Framework']);
        $results = SearchEngine::search('Hello');
        $this->assertCount(1, $results);
        $this->assertSame('Post', $results[0]['model']);
        $this->assertSame(1,      $results[0]['id']);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        SearchEngine::index('Post', 2, ['title' => 'PHP Framework', 'body' => '']);
        $results = SearchEngine::search('php');
        $this->assertCount(1, $results);
    }

    public function testRemoveDeletesFromIndex(): void
    {
        SearchEngine::index('Post', 3, ['title' => 'Gone', 'body' => '']);
        SearchEngine::remove('Post', 3);
        $results = SearchEngine::search('Gone');
        $this->assertCount(0, $results);
    }

    public function testFlushClearsModel(): void
    {
        SearchEngine::index('Post', 4, ['title' => 'A']);
        SearchEngine::index('Post', 5, ['title' => 'B']);
        SearchEngine::flush('Post');
        $this->assertCount(0, SearchEngine::search('A'));
        $this->assertCount(0, SearchEngine::search('B'));
    }

    public function testSearchReturnsEmptyForNoMatch(): void
    {
        SearchEngine::index('Post', 6, ['title' => 'Nemesis']);
        $this->assertCount(0, SearchEngine::search('xyz_not_found'));
    }

    public function testSearchLimitRespected(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            SearchEngine::index('Post', $i, ['title' => "Post {$i}"]);
        }
        $results = SearchEngine::search('Post', [], 3);
        $this->assertCount(3, $results);
    }
}

// ===========================================================================
// Phase14SearchQueryTest
// ===========================================================================

class Phase14SearchQueryTest extends TestCase
{
    public function setUp(): void
    {
        SearchEngine::reset();
    }

    public function testFluentQueryBuilder(): void
    {
        SearchEngine::index('Post', 1, ['title' => 'Hello PHP']);
        SearchEngine::index('Page', 2, ['title' => 'Hello CMS']);

        $results = SearchEngine::query('Hello')->get();
        $this->assertCount(2, $results);
    }

    public function testInScopesModels(): void
    {
        SearchEngine::index('Post', 1, ['title' => 'Hello']);
        SearchEngine::index('Page', 2, ['title' => 'Hello']);

        $results = SearchEngine::query('Hello')->in(['Post'])->get();
        $this->assertCount(1, $results);
        $this->assertSame('Post', $results[0]['model']);
    }

    public function testLimitReducesResults(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            SearchEngine::index('Doc', $i, ['content' => "Doc {$i} search me"]);
        }
        $results = SearchEngine::query('Doc')->limit(2)->get();
        $this->assertCount(2, $results);
    }

    public function testWhereFiltersResults(): void
    {
        SearchEngine::index('Post', 1, ['title' => 'Hello', 'status' => 'published']);
        SearchEngine::index('Post', 2, ['title' => 'Hello', 'status' => 'draft']);

        $results = SearchEngine::query('Hello')->where('status', 'published')->get();
        $this->assertCount(1, $results);
        $this->assertSame('published', $results[0]['data']['status']);
    }

    public function testFirstReturnsTopResult(): void
    {
        SearchEngine::index('Post', 1, ['title' => 'PHP']);
        $first = SearchEngine::query('PHP')->first();
        $this->assertNotNull($first);
        $this->assertSame(1, $first['id']);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $this->assertNull(SearchEngine::query('nothinghere')->first());
    }

    public function testQueryGetterMethods(): void
    {
        $q = SearchEngine::query('test')->in(['Post'])->limit(5)->where('active', 1);
        $this->assertSame('test',    $q->getTerm());
        $this->assertSame(['Post'],  $q->getModels());
        $this->assertSame(5,         $q->getLimit());
        $this->assertSame(['active' => 1], $q->getFilters());
    }
}

// ===========================================================================
// Phase14SearchableTraitTest
// ===========================================================================

class Phase14SearchableTraitTest extends TestCase
{
    public function setUp(): void
    {
        SearchEngine::reset();
    }

    public function testSearchIndexAddsToEngine(): void
    {
        $post = new Phase14_PostModel(1, 'Nemesis Framework');
        $post->searchIndex();

        $results = Phase14_PostModel::search('Nemesis')->get();
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['id']);
    }

    public function testSearchRemoveDeletesFromEngine(): void
    {
        $post = new Phase14_PostModel(2, 'Remove Me');
        $post->searchIndex();
        $post->searchRemove();

        $results = Phase14_PostModel::search('Remove')->get();
        $this->assertCount(0, $results);
    }

    public function testFlushSearchIndex(): void
    {
        (new Phase14_PostModel(3, 'Alpha'))->searchIndex();
        (new Phase14_PostModel(4, 'Beta'))->searchIndex();
        Phase14_PostModel::flushSearchIndex();

        $this->assertCount(0, Phase14_PostModel::search('Alpha')->get());
        $this->assertCount(0, Phase14_PostModel::search('Beta')->get());
    }

    public function testSearchScopedToModel(): void
    {
        SearchEngine::index('OtherModel', 99, ['title' => 'Nemesis']);
        (new Phase14_PostModel(5, 'Not Nemesis'))->searchIndex();

        // Searching Post class should only return Post results
        $results = Phase14_PostModel::search('Nemesis')->get();
        foreach ($results as $r) {
            $this->assertSame(Phase14_PostModel::class, $r['model']);
        }
    }
}

// ===========================================================================
// Phase14NullDriverTest
// ===========================================================================

class Phase14NullDriverTest extends TestCase
{
    private NullDriver $driver;

    public function setUp(): void
    {
        $this->driver = new NullDriver();
    }

    public function testIndexAndSearch(): void
    {
        $this->driver->index('A', 1, ['text' => 'hello world']);
        $results = $this->driver->search('hello');
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['id']);
    }

    public function testScoreHigherForMoreOccurrences(): void
    {
        $this->driver->index('A', 1, ['text' => 'php']);
        $this->driver->index('A', 2, ['text' => 'php php php']);

        $results = $this->driver->search('php');
        // id=2 should come first (higher score)
        $this->assertSame(2, $results[0]['id']);
    }

    public function testRemove(): void
    {
        $this->driver->index('A', 1, ['text' => 'delete me']);
        $this->driver->remove('A', 1);
        $this->assertCount(0, $this->driver->search('delete'));
    }

    public function testFlush(): void
    {
        $this->driver->index('A', 1, ['text' => 'x']);
        $this->driver->index('A', 2, ['text' => 'x']);
        $this->driver->flush('A');
        $this->assertCount(0, $this->driver->search('x'));
    }

    public function testCaseInsensitive(): void
    {
        $this->driver->index('A', 1, ['text' => 'PHP Framework']);
        $this->assertCount(1, $this->driver->search('php'));
        $this->assertCount(1, $this->driver->search('PHP'));
        $this->assertCount(1, $this->driver->search('framework'));
    }
}
