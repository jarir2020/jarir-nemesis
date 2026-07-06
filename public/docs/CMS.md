# CMS — Hooks, Content Types, Menus, Revisions

Nemesis ships a WordPress-style CMS foundation: a hook/filter system, content type registry, hierarchical menus, and a model revision system.

---

## Hook & Filter System

The `HookDispatcher` is a singleton that enables loose coupling between framework components and application code.

### Actions (fire-and-forget)

```php
use Nemesis\Hooks\HookDispatcher;

// Register a listener
HookDispatcher::getInstance()->addAction('user.registered', function (array $user) {
    // send welcome email
}, priority: 10);

// Fire the action
HookDispatcher::getInstance()->doAction('user.registered', $userData);

// Global helpers
addHook('post.published', function ($post) { /* ... */ });
doHook('post.published', $post);
```

### Filters (transform a value)

```php
// Register a filter
HookDispatcher::getInstance()->addFilter('post.title', function (string $title): string {
    return strtoupper($title);
}, priority: 10);

// Apply the filter
$title = HookDispatcher::getInstance()->applyFilters('post.title', $rawTitle);

// Global helpers
addFilter('post.excerpt', fn($text) => mb_substr($text, 0, 160) . '…');
$excerpt = applyFilters('post.excerpt', $rawExcerpt);
```

### Priority & Multiple Listeners

Lower numbers run first. Multiple listeners on the same hook are sorted and all fired.

```php
addHook('app.boot', fn() => /* runs first  */ , priority: 1);
addHook('app.boot', fn() => /* runs second */ , priority: 20);
```

### Remove a Listener

```php
$cb = function ($v) { return $v; };
HookDispatcher::getInstance()->addFilter('my.hook', $cb);
HookDispatcher::getInstance()->removeFilter('my.hook', $cb);
```

---

## Content Types

Register custom post types (like WordPress CPT) with metadata support.

```php
use Nemesis\CMS\ContentType;

// Register
ContentType::register('product', [
    'label'       => 'Products',
    'supports'    => ['title', 'editor', 'thumbnail'],
    'public'      => true,
    'has_archive' => true,
]);

ContentType::register('testimonial', [
    'label'   => 'Testimonials',
    'public'  => false,
]);

// Query
ContentType::get('product');      // returns config array
ContentType::exists('product');   // true
ContentType::all();               // all registered types
ContentType::forget('product');   // unregister
```

### Attach Taxonomies

```php
use Nemesis\CMS\Taxonomy;

// Register a taxonomy and attach it to content types
Taxonomy::register('genre', ['label' => 'Genre']);
Taxonomy::attach('genre', 'product');   // product now has a 'genre' taxonomy

// Query
Taxonomy::for('product');  // ['genre', ...]
```

---

## Meta Store

Per-entity key/value storage backed by a `meta_items` table (auto-created on first use). Falls back to in-memory when no DB is available.

```php
use Nemesis\CMS\MetaStore;

// Store
MetaStore::set('post', 42, 'featured_image', 'https://example.com/img.jpg');
MetaStore::set('post', 42, 'view_count', 1500);

// Retrieve
$image = MetaStore::get('post', 42, 'featured_image');
$count = MetaStore::get('post', 42, 'view_count', default: 0);

// All meta for an entity
$all = MetaStore::all('post', 42);

// Delete
MetaStore::delete('post', 42, 'view_count');

// Check existence
MetaStore::exists('post', 42, 'featured_image');  // true
```

Values are JSON-encoded internally — any scalar, array, or object is supported.

---

## Menu System

Build hierarchical navigation menus with full HTML rendering.

### Define a Menu

```php
use Nemesis\CMS\Menu;
use Nemesis\CMS\MenuItem;

// Register a menu location
Menu::register('main-nav');

// Build the menu
$nav = Menu::create('main-nav');
$nav->add(MenuItem::make('Home',     '/'));
$nav->add(MenuItem::make('About',    '/about'));

$blog = MenuItem::make('Blog', '/blog')->order(3);
$blog->addChild(MenuItem::make('News',    '/blog/news'));
$blog->addChild(MenuItem::make('Guides',  '/blog/guides')->order(1));
$nav->add($blog);

$nav->add(MenuItem::make('Contact', '/contact')->order(4));
```

### Render in a Template

```php
// Via helper (returns rendered HTML by default)
echo menu('main-nav');

// Get the Menu object
$menu = menu('main-nav', render: false);

// Custom classes
echo $menu->render(
    ulClass:     'nav navbar-nav',
    liClass:     'nav-item',
    activeClass: 'active',
);
```

**Rendered output:**
```html
<ul class="nav navbar-nav">
  <li class="nav-item active"><a href="/">Home</a></li>
  <li class="nav-item"><a href="/about">About</a></li>
  <li class="nav-item">
    <a href="/blog">Blog</a>
    <ul>
      <li><a href="/blog/guides">Guides</a></li>
      <li><a href="/blog/news">News</a></li>
    </ul>
  </li>
  <li class="nav-item"><a href="/contact">Contact</a></li>
</ul>
```

Active detection is based on `$_SERVER['REQUEST_URI']`.

### MenuItem Options

```php
MenuItem::make('Dashboard', '/admin')
    ->icon('dashboard')          // icon class/name
    ->order(5)                   // sort weight (lower = first)
    ->target('_blank')           // link target
    ->attr('class', 'featured')  // arbitrary HTML attributes
    ->addChild(/* MenuItem */);
```

---

## Revision System

Add `HasRevisions` to any Model to get automatic version history.

### Setup

```php
use Nemesis\Core\Model;
use Nemesis\Core\Traits\HasRevisions;

class Post extends Model
{
    use HasRevisions;
    protected string $table = 'posts';
}
```

### Usage

```php
$post = Post::find(1);

// Save a revision snapshot before mutating
$post->saveRevision(createdBy: auth()->id());
$post->title = 'Updated Title';
$post->save();

// List all revisions (newest first)
$revisions = $post->getRevisions();
// [['id'=>2, 'data'=>[...], 'created_by'=>5, 'created_at'=>...], ...]

// Get the latest revision
$latest = $post->latestRevision();

// Roll back to revision #3
$post->rollbackTo(3);

// Diff against a revision
$diff = $post->diffRevision(3);
// ['title' => ['old'=>'Draft Title', 'new'=>'Updated Title']]

// Wipe all revisions
$post->resetRevisions();
```

Revisions are stored in the `model_revisions` table (auto-created). Each snapshot captures the full `$attributes` array.
