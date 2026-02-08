# Module System Documentation

## Overview

The Nemesis Module System allows you to organize your application into self-contained, feature-based modules. Each module has its own controllers, models, views, routes, and configurations.

## Module vs Plugin

| Feature | Module | Plugin |
|---------|--------|--------|
| **Purpose** | Extend application | Extend framework |
| **Scope** | Business logic | Core behavior |
| **Load Time** | After framework boot | During framework boot |
| **Example** | Blog, Shop, Forum | Auth2FA, Cache, ORM |

---

## Creating a Module

### Using CLI

```bash
php nemesis make:module Blog
```

This generates:

```
app/Modules/Blog/
├── Controllers/
│   └── BlogController.php
├── Models/
├── Views/
│   └── index.php
├── Migrations/
├── Config/
├── Services/
└── routes.php
```

---

## Module Structure

### Controllers

Located in `app/Modules/{ModuleName}/Controllers/`

```php
<?php
namespace App\Modules\Blog\Controllers;

use Nemesis\Core\Controller;

class BlogController extends Controller {
    public function index() {
        return $this->render('blog::index', ['posts' => $posts]);
    }
}
```

### Routes

Located in `app/Modules/{ModuleName}/routes.php`

```php
<?php

// Register module routes
$router->add('GET', '/blog', [\App\Modules\Blog\Controllers\BlogController::class, 'index']);
$router->add('GET', '/blog/{id}', [\App\Modules\Blog\Controllers\BlogController::class, 'show']);

// Register view namespace
\Nemesis\Core\View::addNamespace('blog', base_path('app/Modules/Blog/Views'));
```

### Views

Located in `app/Modules/{ModuleName}/Views/`

Use namespaced syntax: `module::view`

```php
// In controller
$this->render('blog::index', $data);

// Using helper
view('blog::posts.list', $data);
```

### Models

Located in `app/Modules/{ModuleName}/Models/`

```php
<?php
namespace App\Modules\Blog\Models;

use Nemesis\Database\Model;

class Post extends Model {
    protected $table = 'posts';
    protected $fillable = ['title', 'content', 'author_id'];
}
```

---

## Auto-Discovery

Modules are automatically discovered and loaded from `app/Modules/*/routes.php`

```php
// In routes/route.php (automatic)
foreach (glob(base_path('app/Modules/*/routes.php')) as $routeFile) {
    require $routeFile;
}
```

---

## Example: Building a Blog Module

### Step 1: Create Module

```bash
php nemesis make:module Blog
```

### Step 2: Create Model

```bash
php nemesis make:model Post --module=Blog
```

### Step 3: Add Routes

Edit `app/Modules/Blog/routes.php`:

```php
$router->add('GET', '/blog', [\App\Modules\Blog\Controllers\BlogController::class, 'index']);
$router->add('GET', '/blog/{id}', [\App\Modules\Blog\Controllers\BlogController::class, 'show']);
$router->add('POST', '/blog', [\App\Modules\Blog\Controllers\BlogController::class, 'store']);

\Nemesis\Core\View::addNamespace('blog', base_path('app/Modules/Blog/Views'));
```

### Step 4: Implement Controller

```php
<?php
namespace App\Modules\Blog\Controllers;

use Nemesis\Core\Controller;
use App\Modules\Blog\Models\Post;

class BlogController extends Controller {
    public function index() {
        $posts = Post::all();
        return $this->render('blog::index', ['posts' => $posts]);
    }
    
    public function show($id) {
        $post = Post::find($id);
        if (!$post) {
            return ApiResponse::notFound('Post not found');
        }
        return $this->render('blog::show', ['post' => $post]);
    }
    
    public function store() {
        $request = new Request();
        $data = $request->validate([
            'title' => 'required',
            'content' => 'required'
        ]);
        
        $post = Post::create($data);
        return ApiResponse::success($post, 'Post created', 201);
    }
}
```

### Step 5: Create Views

`app/Modules/Blog/Views/index.php`:

```php
<h1>Blog Posts</h1>
<?php foreach ($posts as $post): ?>
    <article>
        <h2><?= e($post->title) ?></h2>
        <p><?= e($post->content) ?></p>
    </article>
<?php endforeach; ?>
```

---

## Module Configuration

Create module-specific config in `app/Modules/{ModuleName}/Config/`

```php
// app/Modules/Blog/Config/blog.php
return [
    'posts_per_page' => 10,
    'allow_comments' => true,
    'moderation' => true
];
```

Load in controller:

```php
$config = require base_path('app/Modules/Blog/Config/blog.php');
```

---

## Module Services

Create reusable services in `app/Modules/{ModuleName}/Services/`

```php
<?php
namespace App\Modules\Blog\Services;

class PostService {
    public function getPublishedPosts() {
        return Post::where('status', 'published')->get();
    }
    
    public function publishPost($id) {
        $post = Post::find($id);
        $post->status = 'published';
        $post->save();
        return $post;
    }
}
```

---

## Best Practices

1. **Keep modules self-contained** - Each module should be independent
2. **Use namespaced views** - Always use `module::view` syntax
3. **Organize by feature** - Group related functionality together
4. **Follow naming conventions** - Use PascalCase for module names
5. **Document your modules** - Add README.md to each module

---

## Benefits

✅ **Modular architecture** - Organize code by feature/domain  
✅ **Auto-discovery** - Routes automatically loaded  
✅ **Namespace isolation** - Views scoped to modules  
✅ **Rapid development** - Generate structure with one command  
✅ **Maintainability** - Clear separation of concerns  
✅ **Scalability** - Add modules without touching core
