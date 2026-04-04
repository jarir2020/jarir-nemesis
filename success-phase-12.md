# Phase 12 — Core CMS Foundation ✓

**Completed:** 2026-04-03
**Tests:** 60 new tests — all passing
**Cumulative suite:** 586/586 passing (0 failures)

---

## Deliverables

### Hook & Filter System — `src/Hooks/HookDispatcher.php`
WordPress-style named hook points with priority ordering.

- `HookDispatcher::addAction(string $hook, callable $cb, int $priority = 10)`
- `HookDispatcher::doAction(string $hook, ...$args)` — fires side-effects
- `HookDispatcher::addFilter(string $hook, callable $cb, int $priority = 10)`
- `HookDispatcher::applyFilters(string $hook, mixed $value, ...$args): mixed` — pipelines a value
- `HookDispatcher::removeAction/removeFilter/hasAction/hasFilter`
- `HookDispatcher::currentHook(): ?string` — introspect which hook is executing
- Singleton with `setInstance()` / `reset()` for test isolation

Global helpers added to `src/Helpers/Helpers.php`:
- `addHook()`, `removeHook()`, `doHook()`
- `addFilter()`, `removeFilter()`, `applyFilters()`
- `menu(string $name, bool $render = false)`

### Content Types — `src/CMS/ContentType.php`
Fluent registry for custom content types.

```php
ContentType::register('portfolio', ['label' => 'Portfolio', 'has_api' => true])
    ->taxonomy('category')
    ->addMetaField('client', ['type' => 'text'])
    ->enableRevisions();
```

- `ContentType::all()` / `get()` / `exists()` / `forget()` / `reset()`
- Auto-generates label from slug name

### Taxonomy System — `src/CMS/Taxonomy.php`
Classification systems attachable to any content type.

```php
Taxonomy::register('category', ['hierarchical' => true]);
Taxonomy::attach('category', 'post');
Taxonomy::for('post'); // → ['category']
```

- Mirrors attachment onto ContentType automatically
- `hierarchical` flag (category-style vs tag-style)

### Meta Store — `src/CMS/MetaStore.php`
Per-entity key-value metadata with DB persistence and in-memory fallback.

```php
MetaStore::set('post', 42, 'color', 'blue');
MetaStore::get('post', 42, 'color');    // 'blue'
MetaStore::all('post', 42);            // ['color' => 'blue', ...]
MetaStore::delete('post', 42, 'color');
```

- JSON serialisation — supports any serialisable PHP value
- Auto-creates `meta_items` table on first write (SQLite + MySQL compatible)
- `setDb()` / `reset()` for test isolation

### Menu Manager — `src/CMS/Menu.php` + `src/CMS/MenuItem.php`
Hierarchical navigation registry with HTML rendering.

```php
Menu::register('main', [
    MenuItem::make('Home', '/'),
    MenuItem::make('About', '/about')->order(2)
        ->addChild(MenuItem::make('Team', '/about/team')),
]);

echo Menu::get('main')->render('nav-list'); // → <ul class="nav-list"><li>...
echo menu('main', true);                    // via helper
```

- Items sorted by `order` at render time
- `isActive()` detection based on `$_SERVER['REQUEST_URI']`
- Icon support, `target`, arbitrary HTML attrs on `<a>`
- Nested children rendered as `<ul>` subtrees

### Revision System — `src/Core/Traits/HasRevisions.php`
Full version history for any Model.

```php
class Post extends Model { use HasRevisions; }

$post->saveRevision();
$post->getRevisions();          // newest first
$post->latestRevision();
$post->rollbackTo($revId);      // restores attrs, call save() to persist
$post->diffRevision($revId);    // ['title' => ['revision' => 'old', 'current' => 'new']]
```

- Auto-creates `nemesis_revisions` table (SQLite + MySQL compatible)
- In-memory fallback when DB unavailable
- `resetRevisions()` / `setRevisionDb()` for test isolation

---

## Test Coverage (60 tests)

| Group                   | Tests |
|-------------------------|-------|
| Phase12HookTest         | 10    |
| Phase12FilterTest       | 8     |
| Phase12ContentTypeTest  | 6     |
| Phase12TaxonomyTest     | 5     |
| Phase12MetaStoreTest    | 8     |
| Phase12MenuItemTest     | 7     |
| Phase12MenuTest         | 8     |
| Phase12HasRevisionsTest | 8     |

---

## Next: Phase 13 — Admin Panel + Media Library
Requires Phase 12 (hooks, content types, menus) and Phase 11 (template engine).
