# Phase 13 — Admin Panel + Media Library ✓

**Completed:** 2026-04-03
**Tests:** 57 new tests — all passing
**Cumulative suite:** 643/643 passing (0 failures)

---

## Deliverables

### Admin Panel

**`src/Admin/AdminPanel.php`** — Central admin facade

```php
AdminPanel::register('posts', [
    'label'   => 'Blog Posts',
    'model'   => Post::class,
    'columns' => ['title', 'status', 'created_at'],
    'roles'   => [AdminPanel::ROLE_EDITOR],
]);

AdminPanel::widget('stats', fn() => '<p>' . Post::count() . ' posts</p>');

AdminPanel::canAccess('editor');             // true
AdminPanel::canManage('posts', 'author');    // false
AdminPanel::getCrudRoutes();                 // 7 routes per entity + dashboard
```

- Role constants: `ROLE_SUBSCRIBER`, `ROLE_CONTRIBUTOR`, `ROLE_AUTHOR`, `ROLE_EDITOR`, `ROLE_ADMIN`
- Role hierarchy (higher = more access)
- Auto-populates admin nav from registered entities
- Configurable URL prefix (default `/admin`)

**`src/Admin/DashboardWidget.php`** — Dashboard panel registry

```php
DashboardWidget::register('posts_count', fn() => '<p>42 posts</p>')
    ->title('Posts')->icon('fa-file')->column(1)->order(10);

DashboardWidget::all();      // sorted by order
$widget->render();           // exception-safe rendering
```

**`views/admin/layout.php`** — Responsive admin shell (sidebar, topbar, content area)
**`views/admin/dashboard.php`** — Dashboard with widgets + content type table

### Media Library

**`src/Media/Attachment.php`** — Value object for stored files

```php
$att = new Attachment([...]);
$att->isImage();       // true
$att->isPdf();         // false
$att->extension();     // 'jpg'
$att->humanSize();     // '200 KB'
$att->imgTag(['class' => 'img-fluid']);  // <img src="..." alt="...">
```

**`src/Media/MediaLibrary.php`** — Upload/storage facade

```php
$att = MediaLibrary::upload($_FILES['photo']);
$att = MediaLibrary::store([...]);           // programmatic (no file move)
MediaLibrary::find($id);
MediaLibrary::all(['mime_type' => 'image/jpeg']);
MediaLibrary::url($att);
MediaLibrary::delete($att);
```

- In-memory fallback when DB unavailable (unit-test friendly)
- Auto-creates `attachments` table (SQLite + MySQL compatible)
- MIME type + file size validation via `setAllowedTypes()` / `setMaxSize()`

**`src/Media/ImageProcessor.php`** — Image transformation engine

```php
ImageProcessor::resize('/uploads/photo.jpg', 800, 600);
ImageProcessor::thumbnail('/uploads/photo.jpg', 'medium');
ImageProcessor::toWebP('/uploads/photo.jpg');
ImageProcessor::srcset('/uploads/photo.jpg', [480, 768, 1200]);
// → '/uploads/photo-480x0.jpg 480w, /uploads/photo-768x0.jpg 768w, ...'
```

- Named size registry: `thumb`(150²,crop), `medium`(300²), `large`(800×600), `full`(1920×1080)
- `registerSize()` / `forgetSize()` / `resetSizes()`
- Returns computed path even when source file is absent (dry-run / test safe)

**`config/media.php`** — disks, allowed_types, max_size, sizes, quality

---

## Test Coverage (57 tests)

| Group                        | Tests |
|------------------------------|-------|
| Phase13AdminPanelTest        | 14    |
| Phase13DashboardWidgetTest   | 6     |
| Phase13AttachmentTest        | 13    |
| Phase13MediaLibraryTest      | 10    |
| Phase13ImageProcessorTest    | 14    |

---

## Next: Phase 14 — Notifications + Full-Text Search
- Notification Center: mail, DB, broadcast, Slack, webhook channels
- Full-Text Search: DB FULLTEXT + MeiliSearch / Typesense drivers
