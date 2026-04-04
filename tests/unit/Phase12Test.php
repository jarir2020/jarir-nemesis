<?php
declare(strict_types=1);

// Nemesis 5.0.0 — Phase 12 Test Suite | Created: 2026-04-03
// Tests: HookDispatcher, ContentType, Taxonomy, MetaStore, Menu/MenuItem, HasRevisions

use Nemesis\Testing\TestCase;
use Nemesis\Hooks\HookDispatcher;
use Nemesis\CMS\ContentType;
use Nemesis\CMS\Taxonomy;
use Nemesis\CMS\MetaStore;
use Nemesis\CMS\Menu;
use Nemesis\CMS\MenuItem;
use Nemesis\Core\Traits\HasRevisions;

// ===========================================================================
// Stubs
// ===========================================================================

/**
 * Minimal Model stub that uses HasRevisions without requiring a DB.
 */
class Phase12_Post
{
    use HasRevisions;

    private array $attributes = [];
    private string $primaryKey = 'id';

    public function __construct(array $attrs = [])
    {
        $this->attributes = $attrs;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function fill(array $attrs): void
    {
        $this->attributes = array_merge($this->attributes, $attrs);
    }
}

// ===========================================================================
// Phase12HookTest — HookDispatcher (Actions)
// ===========================================================================

class Phase12HookTest extends TestCase
{
    public function setUp(): void
    {
        HookDispatcher::reset();
    }

    public function testAddAndFireAction(): void
    {
        $fired = false;
        HookDispatcher::addAction('test.action', function () use (&$fired) {
            $fired = true;
        });
        HookDispatcher::doAction('test.action');
        $this->assertTrue($fired);
    }

    public function testActionReceivesArguments(): void
    {
        $received = [];
        HookDispatcher::addAction('test.args', function (string $a, int $b) use (&$received) {
            $received = [$a, $b];
        });
        HookDispatcher::doAction('test.args', 'hello', 42);
        $this->assertSame(['hello', 42], $received);
    }

    public function testMultipleActionsOnSameHook(): void
    {
        $log = [];
        HookDispatcher::addAction('multi', function () use (&$log) { $log[] = 'A'; });
        HookDispatcher::addAction('multi', function () use (&$log) { $log[] = 'B'; });
        HookDispatcher::doAction('multi');
        $this->assertSame(['A', 'B'], $log);
    }

    public function testPriorityOrderLowestFirst(): void
    {
        $log = [];
        HookDispatcher::addAction('priority', function () use (&$log) { $log[] = 'late'; },  20);
        HookDispatcher::addAction('priority', function () use (&$log) { $log[] = 'first'; }, 5);
        HookDispatcher::addAction('priority', function () use (&$log) { $log[] = 'mid'; },   10);
        HookDispatcher::doAction('priority');
        $this->assertSame(['first', 'mid', 'late'], $log);
    }

    public function testRemoveAction(): void
    {
        $fired = false;
        $cb = function () use (&$fired) { $fired = true; };
        HookDispatcher::addAction('remove.me', $cb);
        HookDispatcher::removeAction('remove.me', $cb);
        HookDispatcher::doAction('remove.me');
        $this->assertFalse($fired);
    }

    public function testHasActionTrue(): void
    {
        HookDispatcher::addAction('has.action', fn() => null);
        $this->assertTrue(HookDispatcher::hasAction('has.action'));
    }

    public function testHasActionFalseWhenEmpty(): void
    {
        $this->assertFalse(HookDispatcher::hasAction('no.action'));
    }

    public function testCurrentHookInsideCallback(): void
    {
        $captured = null;
        HookDispatcher::addAction('ctx.hook', function () use (&$captured) {
            $captured = HookDispatcher::currentHook();
        });
        HookDispatcher::doAction('ctx.hook');
        $this->assertSame('ctx.hook', $captured);
    }

    public function testCurrentHookIsNullOutsideCallback(): void
    {
        $this->assertNull(HookDispatcher::currentHook());
    }

    public function testRegisteredActionsReturnsNames(): void
    {
        HookDispatcher::addAction('alpha', fn() => null);
        HookDispatcher::addAction('beta',  fn() => null);
        $names = HookDispatcher::registeredActions();
        $this->assertContains('alpha', $names);
        $this->assertContains('beta',  $names);
    }
}

// ===========================================================================
// Phase12FilterTest — HookDispatcher (Filters)
// ===========================================================================

class Phase12FilterTest extends TestCase
{
    public function setUp(): void
    {
        HookDispatcher::reset();
    }

    public function testAddAndApplyFilter(): void
    {
        HookDispatcher::addFilter('post.title', fn(string $t) => strtoupper($t));
        $result = HookDispatcher::applyFilters('post.title', 'hello world');
        $this->assertSame('HELLO WORLD', $result);
    }

    public function testFilterChain(): void
    {
        HookDispatcher::addFilter('chain', fn(string $v) => $v . '-A');
        HookDispatcher::addFilter('chain', fn(string $v) => $v . '-B');
        $result = HookDispatcher::applyFilters('chain', 'start');
        $this->assertSame('start-A-B', $result);
    }

    public function testFilterPriorityOrder(): void
    {
        HookDispatcher::addFilter('fprio', fn(string $v) => $v . '-late',  priority: 20);
        HookDispatcher::addFilter('fprio', fn(string $v) => $v . '-first', priority: 5);
        $result = HookDispatcher::applyFilters('fprio', '');
        $this->assertSame('-first-late', $result);
    }

    public function testFilterReturnsOriginalValueWhenNoCallbacks(): void
    {
        $result = HookDispatcher::applyFilters('no.filter', 'original');
        $this->assertSame('original', $result);
    }

    public function testRemoveFilter(): void
    {
        $cb = fn(string $v) => $v . '-modified';
        HookDispatcher::addFilter('removable', $cb);
        HookDispatcher::removeFilter('removable', $cb);
        $result = HookDispatcher::applyFilters('removable', 'clean');
        $this->assertSame('clean', $result);
    }

    public function testHasFilter(): void
    {
        HookDispatcher::addFilter('has.filter', fn($v) => $v);
        $this->assertTrue(HookDispatcher::hasFilter('has.filter'));
        $this->assertFalse(HookDispatcher::hasFilter('no.filter'));
    }

    public function testGlobalHelperAddHookAndDoHook(): void
    {
        $ran = false;
        addHook('global.test', function () use (&$ran) { $ran = true; });
        doHook('global.test');
        $this->assertTrue($ran);
    }

    public function testGlobalHelperAddFilterAndApplyFilters(): void
    {
        addFilter('global.filter', fn(int $n) => $n * 2);
        $result = applyFilters('global.filter', 5);
        $this->assertSame(10, $result);
    }
}

// ===========================================================================
// Phase12ContentTypeTest
// ===========================================================================

class Phase12ContentTypeTest extends TestCase
{
    public function setUp(): void
    {
        ContentType::reset();
    }

    public function testRegisterAndExists(): void
    {
        ContentType::register('post');
        $this->assertTrue(ContentType::exists('post'));
    }

    public function testRegisterWithConfig(): void
    {
        ContentType::register('portfolio', [
            'label'       => 'Portfolio',
            'description' => 'My work',
            'has_api'     => true,
            'has_meta'    => true,
        ]);
        $ct = ContentType::get('portfolio');
        $this->assertSame('Portfolio', $ct->getLabel());
        $this->assertSame('My work',   $ct->getDescription());
        $this->assertTrue($ct->isApiEnabled());
        $this->assertTrue($ct->isMetaEnabled());
    }

    public function testFluentChaining(): void
    {
        ContentType::register('event')
            ->label('Events')
            ->enableRevisions()
            ->taxonomy('category')
            ->addMetaField('venue', ['type' => 'text', 'label' => 'Venue']);

        $ct = ContentType::get('event');
        $this->assertTrue($ct->isRevisionsEnabled());
        $this->assertContains('category', $ct->getTaxonomies());
        $this->assertArrayHasKey('venue', $ct->getMetaFields());
    }

    public function testAllReturnsRegistered(): void
    {
        ContentType::register('a');
        ContentType::register('b');
        $all = ContentType::all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('a', $all);
        $this->assertArrayHasKey('b', $all);
    }

    public function testForgetRemovesEntry(): void
    {
        ContentType::register('temp');
        ContentType::forget('temp');
        $this->assertFalse(ContentType::exists('temp'));
    }

    public function testDefaultLabelGeneratedFromName(): void
    {
        ContentType::register('blog_post');
        $this->assertSame('Blog Post', ContentType::get('blog_post')->getLabel());
    }
}

// ===========================================================================
// Phase12TaxonomyTest
// ===========================================================================

class Phase12TaxonomyTest extends TestCase
{
    public function setUp(): void
    {
        Taxonomy::reset();
        ContentType::reset();
    }

    public function testRegisterTaxonomy(): void
    {
        Taxonomy::register('category', ['label' => 'Categories', 'hierarchical' => true]);
        $tax = Taxonomy::get('category');
        $this->assertSame('Categories', $tax->getLabel());
        $this->assertTrue($tax->isHierarchical());
    }

    public function testAttachTaxonomyToContentType(): void
    {
        ContentType::register('post');
        Taxonomy::register('tag');
        Taxonomy::attach('tag', 'post');

        $forPost = Taxonomy::for('post');
        $this->assertContains('tag', $forPost);
    }

    public function testForReturnsMultipleTaxonomies(): void
    {
        ContentType::register('post');
        Taxonomy::attach('category', 'post');
        Taxonomy::attach('tag',      'post');

        $forPost = Taxonomy::for('post');
        $this->assertContains('category', $forPost);
        $this->assertContains('tag',      $forPost);
    }

    public function testGetContentTypesOnTaxonomy(): void
    {
        ContentType::register('post');
        ContentType::register('page');
        Taxonomy::register('category');
        Taxonomy::attach('category', 'post');
        Taxonomy::attach('category', 'page');

        $types = Taxonomy::get('category')->getContentTypes();
        $this->assertContains('post', $types);
        $this->assertContains('page', $types);
    }

    public function testTaxonomyExistsCheck(): void
    {
        Taxonomy::register('topic');
        $this->assertTrue(Taxonomy::exists('topic'));
        $this->assertFalse(Taxonomy::exists('nonexistent'));
    }
}

// ===========================================================================
// Phase12MetaStoreTest
// ===========================================================================

class Phase12MetaStoreTest extends TestCase
{
    public function setUp(): void
    {
        MetaStore::reset();
    }

    public function testSetAndGet(): void
    {
        MetaStore::set('post', 1, 'color', 'blue');
        $this->assertSame('blue', MetaStore::get('post', 1, 'color'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('default', MetaStore::get('post', 99, 'missing', 'default'));
    }

    public function testSetOverwritesExisting(): void
    {
        MetaStore::set('post', 1, 'title', 'First');
        MetaStore::set('post', 1, 'title', 'Updated');
        $this->assertSame('Updated', MetaStore::get('post', 1, 'title'));
    }

    public function testAllReturnsAllMetaForEntity(): void
    {
        MetaStore::set('post', 5, 'color', 'red');
        MetaStore::set('post', 5, 'size',  'large');
        $all = MetaStore::all('post', 5);
        $this->assertSame('red',   $all['color']);
        $this->assertSame('large', $all['size']);
    }

    public function testDeleteRemovesMeta(): void
    {
        MetaStore::set('post', 2, 'key', 'val');
        MetaStore::delete('post', 2, 'key');
        $this->assertNull(MetaStore::get('post', 2, 'key'));
    }

    public function testExistsReturnsTrueAfterSet(): void
    {
        MetaStore::set('user', 7, 'bio', 'hello');
        $this->assertTrue(MetaStore::exists('user', 7, 'bio'));
    }

    public function testExistsReturnsFalseAfterDelete(): void
    {
        MetaStore::set('user', 8, 'token', 'abc');
        MetaStore::delete('user', 8, 'token');
        $this->assertFalse(MetaStore::exists('user', 8, 'token'));
    }

    public function testSupportsComplexValues(): void
    {
        $data = ['foo' => [1, 2, 3], 'nested' => ['key' => 'val']];
        MetaStore::set('post', 10, 'extra', $data);
        $this->assertSame($data, MetaStore::get('post', 10, 'extra'));
    }
}

// ===========================================================================
// Phase12MenuItemTest
// ===========================================================================

class Phase12MenuItemTest extends TestCase
{
    public function testMakeCreatesItem(): void
    {
        $item = MenuItem::make('Home', '/');
        $this->assertSame('Home', $item->getLabel());
        $this->assertSame('/',    $item->getUrl());
    }

    public function testFluentSetters(): void
    {
        $item = MenuItem::make('Blog', '/blog')
            ->icon('fa-rss')
            ->order(5)
            ->target('_blank')
            ->attr('data-track', 'blog-click');

        $this->assertSame('fa-rss',      $item->getIcon());
        $this->assertSame(5,             $item->getOrder());
        $this->assertSame('_blank',      $item->getTarget());
        $this->assertSame(['data-track' => 'blog-click'], $item->getAttrs());
    }

    public function testAddChild(): void
    {
        $parent = MenuItem::make('About', '/about');
        $child  = MenuItem::make('Team', '/about/team');
        $parent->addChild($child);

        $this->assertTrue($parent->hasChildren());
        $this->assertCount(1, $parent->getChildren());
        $this->assertSame('Team', $parent->getChildren()[0]->getLabel());
    }

    public function testChildrenSortedByOrder(): void
    {
        $parent = MenuItem::make('Parent', '/p');
        $parent->addChild(MenuItem::make('C', '/c')->order(3));
        $parent->addChild(MenuItem::make('A', '/a')->order(1));
        $parent->addChild(MenuItem::make('B', '/b')->order(2));

        $labels = array_map(fn(MenuItem $i) => $i->getLabel(), $parent->getChildren());
        $this->assertSame(['A', 'B', 'C'], $labels);
    }

    public function testRenderLiContainsLabelAndUrl(): void
    {
        $item = MenuItem::make('Contact', '/contact');
        $html = $item->renderLi();
        $this->assertStringContainsString('Contact', $html);
        $this->assertStringContainsString('/contact', $html);
        $this->assertStringContainsString('<li', $html);
        $this->assertStringContainsString('<a', $html);
    }

    public function testRenderLiWithIcon(): void
    {
        $item = MenuItem::make('Docs', '/docs')->icon('fa-book');
        $html = $item->renderLi();
        $this->assertStringContainsString('fa-book', $html);
    }

    public function testToArray(): void
    {
        $item = MenuItem::make('Shop', '/shop')->order(2);
        $arr  = $item->toArray();
        $this->assertSame('Shop',  $arr['label']);
        $this->assertSame('/shop', $arr['url']);
        $this->assertSame(2,       $arr['order']);
    }
}

// ===========================================================================
// Phase12MenuTest
// ===========================================================================

class Phase12MenuTest extends TestCase
{
    public function setUp(): void
    {
        Menu::reset();
    }

    public function testRegisterAndGet(): void
    {
        Menu::register('main', [MenuItem::make('Home', '/')]);
        $this->assertTrue(Menu::exists('main'));
        $m = Menu::get('main');
        $this->assertSame('main', $m->getName());
    }

    public function testMenuCountsItems(): void
    {
        Menu::register('nav', [
            MenuItem::make('A', '/a'),
            MenuItem::make('B', '/b'),
            MenuItem::make('C', '/c'),
        ]);
        $this->assertSame(3, Menu::get('nav')->count());
    }

    public function testFluentCreate(): void
    {
        Menu::create('footer')
            ->add(MenuItem::make('Privacy', '/privacy'))
            ->add(MenuItem::make('Terms',   '/terms'));
        $this->assertSame(2, Menu::get('footer')->count());
    }

    public function testItemsSortedByOrder(): void
    {
        Menu::register('sorted', [
            MenuItem::make('C', '/c')->order(3),
            MenuItem::make('A', '/a')->order(1),
            MenuItem::make('B', '/b')->order(2),
        ]);
        $labels = array_map(fn(MenuItem $i) => $i->getLabel(), Menu::get('sorted')->items());
        $this->assertSame(['A', 'B', 'C'], $labels);
    }

    public function testRenderProducesUl(): void
    {
        Menu::register('render', [MenuItem::make('Home', '/')]);
        $html = Menu::get('render')->render('nav-list');
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('nav-list', $html);
        $this->assertStringContainsString('<li', $html);
        $this->assertStringContainsString('Home', $html);
    }

    public function testRenderReturnsEmptyStringForEmpty(): void
    {
        Menu::register('empty');
        $this->assertSame('', Menu::get('empty')->render());
    }

    public function testAllReturnsAllMenus(): void
    {
        Menu::register('m1');
        Menu::register('m2');
        $this->assertCount(2, Menu::all());
    }

    public function testForgetRemovesMenu(): void
    {
        Menu::register('disposable');
        Menu::forget('disposable');
        $this->assertFalse(Menu::exists('disposable'));
    }
}

// ===========================================================================
// Phase12HasRevisionsTest
// ===========================================================================

class Phase12HasRevisionsTest extends TestCase
{
    public function setUp(): void
    {
        Phase12_Post::resetRevisions();
    }

    public function testSaveRevisionStoresSnapshot(): void
    {
        $post = new Phase12_Post(['id' => 1, 'title' => 'Hello']);
        $post->saveRevision();

        $revs = $post->getRevisions();
        $this->assertCount(1, $revs);
        $this->assertSame('Hello', $revs[0]['payload']['title'] ?? null);
    }

    public function testMultipleRevisions(): void
    {
        $post = new Phase12_Post(['id' => 2, 'title' => 'v1']);
        $post->saveRevision();

        $post->fill(['title' => 'v2']);
        $post->saveRevision();

        $revs = $post->getRevisions();
        $this->assertCount(2, $revs);
    }

    public function testLatestRevision(): void
    {
        $post = new Phase12_Post(['id' => 3, 'title' => 'first']);
        $post->saveRevision();

        $post->fill(['title' => 'second']);
        $post->saveRevision();

        $latest = $post->latestRevision();
        // getRevisions() returns newest first
        $this->assertSame('second', $latest['payload']['title'] ?? null);
    }

    public function testRollbackToRestorestAttributes(): void
    {
        $post = new Phase12_Post(['id' => 4, 'title' => 'original']);
        $post->saveRevision();

        $revs  = $post->getRevisions();
        $revId = $revs[0]['id'];

        $post->fill(['title' => 'changed']);
        $this->assertSame('changed', $post->getAttribute('title'));

        $ok = $post->rollbackTo($revId);
        $this->assertTrue($ok);
        $this->assertSame('original', $post->getAttribute('title'));
    }

    public function testRollbackReturnsFalseForUnknownId(): void
    {
        $post = new Phase12_Post(['id' => 5, 'title' => 'x']);
        $this->assertFalse($post->rollbackTo(9999));
    }

    public function testDiffRevisionShowsChanges(): void
    {
        $post = new Phase12_Post(['id' => 6, 'title' => 'old', 'body' => 'same']);
        $post->saveRevision();

        $revs  = $post->getRevisions();
        $revId = $revs[0]['id'];

        $post->fill(['title' => 'new', 'body' => 'same']);
        $diff = $post->diffRevision($revId);

        $this->assertArrayHasKey('title', $diff);
        $this->assertSame('old', $diff['title']['revision']);
        $this->assertSame('new', $diff['title']['current']);
        $this->assertArrayNotHasKey('body', $diff); // unchanged
    }

    public function testDiffReturnsEmptyForUnknownRevision(): void
    {
        $post = new Phase12_Post(['id' => 7, 'title' => 'x']);
        $this->assertSame([], $post->diffRevision(9999));
    }

    public function testLatestRevisionNullWhenNoRevisions(): void
    {
        $post = new Phase12_Post(['id' => 8, 'title' => 'fresh']);
        $this->assertNull($post->latestRevision());
    }
}
