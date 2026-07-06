# Admin Panel & Media Library

Nemesis includes a role-based admin panel with automatic CRUD route generation, a dashboard widget system, and a full-featured media library with image processing.

---

## Admin Panel

### Roles

```php
use Nemesis\Admin\AdminPanel;

// Role hierarchy (lowest → highest)
AdminPanel::ROLE_SUBSCRIBER   // 0
AdminPanel::ROLE_CONTRIBUTOR  // 1
AdminPanel::ROLE_AUTHOR       // 2
AdminPanel::ROLE_EDITOR       // 3
AdminPanel::ROLE_ADMIN        // 4
```

### Register Entities for Auto-CRUD

```php
AdminPanel::register('post', [
    'label'         => 'Posts',
    'model'         => \App\Models\Post::class,
    'required_role' => AdminPanel::ROLE_AUTHOR,
    'searchable'    => ['title', 'slug'],
    'per_page'      => 20,
]);

AdminPanel::register('user', [
    'label'         => 'Users',
    'model'         => \App\Models\User::class,
    'required_role' => AdminPanel::ROLE_ADMIN,
]);
```

### Dashboard and CRUD Helpers

```php
// Update dashboard metadata
AdminPanel::dashboard([
    'title' => 'Operations',
    'subtitle' => 'Keep the admin shell focused.',
    'columns' => 4,
]);

// Register reusable admin components
AdminPanel::component('stats_card', fn(array $meta) => '<section>' . ($meta['label'] ?? 'Stats') . '</section>', [
    'label' => 'Stats',
    'section' => 'dashboard',
]);

// Seed CRUD defaults from admin metadata
AdminPanel::register('post', [
    'columns' => ['title', 'status'],
    'form_fields' => ['title', 'status'],
    'table_columns' => ['title', 'status'],
]);

// Build a ready-to-render form or table from the registered entity
$form  = AdminPanel::formFor('post', ['title' => 'Hello']);
$table = AdminPanel::tableFor('post', [['title' => 'Hello', 'status' => 'draft']]);
```

### Access Checks

```php
$userRole = $currentUser->role;  // e.g. 'editor'

// Can the user access the admin panel at all?
AdminPanel::canAccess($userRole);                          // true if ≥ subscriber

// Can the user access a specific entity?
AdminPanel::canAccess($userRole, AdminPanel::ROLE_EDITOR); // true if ≥ editor

// Can the user manage a specific entity?
AdminPanel::canManage('user', $userRole);  // checks registered required_role
```

### Auto-Generated CRUD Routes

```php
// Returns all CRUD routes for all registered entities
$routes = AdminPanel::getCrudRoutes();

// Each entity generates 7 routes:
// GET    /admin/posts           → index
// GET    /admin/posts/create    → create
// POST   /admin/posts           → store
// GET    /admin/posts/{id}      → show
// GET    /admin/posts/{id}/edit → edit
// PUT    /admin/posts/{id}      → update
// DELETE /admin/posts/{id}      → destroy
```

Register these in `routes/web.php`:

```php
foreach (AdminPanel::getCrudRoutes() as $route) {
    $router->add($route['method'], $route['uri'], $route['action']);
}
```

### Route Prefix

```php
AdminPanel::setPrefix('control-panel');  // default is 'admin'
echo AdminPanel::getPrefix();            // 'control-panel'
```

---

## Dashboard Widgets

Widgets appear on the admin dashboard and are rendered in a grid.

```php
use Nemesis\Admin\DashboardWidget;

// Register a widget
DashboardWidget::register('recent-posts', function () {
    $posts = Post::latest()->limit(5)->get();
    $html  = '<ul>';
    foreach ($posts as $post) {
        $html .= "<li><a href='/admin/posts/{$post->id}'>{$post->title}</a></li>";
    }
    return $html . '</ul>';
})
->title('Recent Posts')
->icon('file-text')
->column(1)       // 1 or 2 column layout
->order(10);      // sort order

DashboardWidget::register('stats', fn() => '<p>Total users: ' . User::count() . '</p>')
    ->title('Site Stats')
    ->order(5);
```

### Admin Components

Admin components are lightweight reusable blocks for the dashboard shell and CRUD screens.

```php
use Nemesis\Admin\AdminComponent;

AdminComponent::register('quick-links', fn(array $meta) => '<div>' . ($meta['title'] ?? 'Links') . '</div>', [
    'title' => 'Quick Links',
    'section' => 'dashboard',
]);

foreach (AdminComponent::all() as $component) {
    echo $component->render();
}
```

### Render All Widgets

```php
$widgets = DashboardWidget::all();  // sorted by order

foreach ($widgets as $widget) {
    echo $widget->render();  // exception in renderer = .widget-error div, not a crash
}
```

### Dashboard View

The admin shell is in `views/admin/layout.php` and `views/admin/dashboard.php`. Include them from your admin controller:

```php
class AdminController extends Controller
{
    public function dashboard()
    {
        return $this->render('admin.dashboard');
    }
}
```

---

## Media Library

### Upload a File

```php
use Nemesis\Media\MediaLibrary;

// $_FILES['avatar'] → uploaded file array
$attachment = MediaLibrary::upload($_FILES['avatar'], disk: 'public');

echo $attachment->url();       // https://example.com/storage/avatars/photo.jpg
echo $attachment->humanSize(); // "2.4 MB"
echo $attachment->isImage();   // true
```

### Store from Existing Data

```php
$attachment = MediaLibrary::store([
    'filename'  => 'report.pdf',
    'path'      => 'documents/report.pdf',
    'disk'      => 'local',
    'mime_type' => 'application/pdf',
    'size'      => 204800,
    'title'     => 'Annual Report 2026',
]);
```

### Query Attachments

```php
// Find by ID
$att = MediaLibrary::find(42);

// Filter
$images = MediaLibrary::all(['mime_type' => 'image/jpeg']);
$pdfs   = MediaLibrary::all(['mime_type' => 'application/pdf']);
```

### Delete

```php
MediaLibrary::delete($attachment);   // removes file and DB record
MediaLibrary::delete(42);            // by ID
```

### Attachment Object

```php
$att = MediaLibrary::find(1);

$att->getId();         // int
$att->getFilename();   // 'photo.jpg'
$att->getMimeType();   // 'image/jpeg'
$att->getSize();       // bytes as int
$att->getTitle();      // string
$att->getDisk();       // 'public'
$att->getPath();       // 'uploads/photo.jpg'

$att->url();           // full public URL
$att->extension();     // 'jpg'
$att->humanSize();     // '2.4 MB'

$att->isImage();  $att->isVideo();
$att->isAudio();  $att->isPdf();

// Generate an <img> tag
echo $att->imgTag(['class' => 'hero-img', 'alt' => 'Banner']);
```

---

## Image Processor

### Named Sizes

```php
use Nemesis\Media\ImageProcessor;

// Register sizes (name → [width, height, crop, quality])
ImageProcessor::registerSize('thumbnail', 150, 150, crop: true,  quality: 85);
ImageProcessor::registerSize('medium',    600, 400, crop: false, quality: 80);
ImageProcessor::registerSize('large',    1200, 800, crop: false, quality: 75);
// 'full' is always registered (original dimensions)

// Check registered sizes
ImageProcessor::allSizes();   // ['thumbnail'=>[...], 'medium'=>[...], 'large'=>[...], 'full'=>[...]]
ImageProcessor::getSize('thumbnail');  // ['width'=>150,'height'=>150,'crop'=>true,'quality'=>85]
```

### Resize

```php
$processor = new ImageProcessor();

// Generate a specific named size
$path = $processor->thumbnail('/uploads/photo.jpg', 'medium');
// → '/uploads/photo-medium.jpg'

// Manual resize
$path = $processor->resize(
    src:     '/uploads/photo.jpg',
    width:   800,
    height:  600,
    crop:    false,
    quality: 80,
);
```

### WebP Conversion

```php
$webpPath = $processor->toWebP('/uploads/photo.jpg', quality: 85);
// → '/uploads/photo.webp'
```

### Responsive srcset

```php
$srcset = $processor->srcset('/uploads/photo.jpg', [400, 800, 1200]);
// → '/uploads/photo-400w.jpg 400w, /uploads/photo-800w.jpg 800w, ...'
```

```html
<img src="{{ $att->url() }}"
     srcset="{{ $srcset }}"
     sizes="(max-width: 600px) 400px, 800px">
```

---

## Storage Configuration

`config/media.php`:

```php
return [
    'disks' => [
        'public' => [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => env('APP_URL') . '/storage',
        ],
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],
    ],
    'allowed_types' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'video/mp4',
    ],
    'max_size' => 10 * 1024 * 1024,  // 10 MB
    'sizes' => [
        'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
        'medium'    => ['width' => 600, 'height' => 400],
        'large'     => ['width' => 1200, 'height' => 800],
    ],
    'quality' => 80,
];
```

### Storage Link

```bash
# Create public/storage → storage/app/public symlink
php nemesis storage:link
```
