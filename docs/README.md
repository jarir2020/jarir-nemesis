# Nemesis Framework Documentation

**Version 4.0.0** — PHP 8.2+ | 769 tests passing

---

## Table of Contents

### Getting Started
- **[Installation](INSTALLATION.md)** — Setup, environment, and configuration
- **[Directory Structure](STRUCTURE.md)** — Application organisation
- **[CLI Commands](CLI_COMMANDS.md)** — Full command reference, including `vendor:compress`
- **[Examples Gallery](EXAMPLES.md)** — Optional starter packs for MVC, API, plugins, extensions, and modules

### Architecture
- **[Module System](MODULES.md)** — Build self-contained feature modules
- **[Plugin System](PLUGINS.md)** — Extend the framework via sandboxed plugins
- **[Routing](ROUTING.md)** — Web, API, named routes, model binding, subdomain
- **[Middleware](MIDDLEWARE.md)** — Request pipeline and middleware groups
- **[Dependency Injection](DEPENDENCY_INJECTION.md)** — PSR-11 service container
- **[API Standards](API_STANDARDS.md)** — Consistent JSON response format and CORS

### Views & Frontend
- **[Template Engine](TEMPLATE_ENGINE.md)** — Blade-compatible templates, directives, layouts
- **[Controllers](CONTROLLERS.md)** — Controller basics and response helpers

### Database
- **[Database](DATABASE.md)** — Connection, query builder, multi-driver
- **[Models (ORM)](MODELS.md)** — Fluent ORM, scopes, relationships
- **[Relationships](RELATIONSHIPS.md)** — HasOne, HasMany, BelongsTo, BelongsToMany
- **[Migrations](MIGRATIONS.md)** — Schema version control
- **[Seeding](SEEDING.md)** — Seed test and sample data
- **[Query Builder](QUERY_BUILDER.md)** — Raw query builder reference
- **[Scopes](SCOPES.md)** — Reusable query constraints

### Security
- **[Authentication](AUTHENTICATION.md)** — JWT, sessions, OAuth2, TOTP
- **[Authorization](AUTHORIZATION.md)** — Roles, permissions, policies (RBAC)
- **[Security](SECURITY.md)** — CSRF, encryption, input sanitisation
- **[CSRF Protection](CSRF.md)** — Token generation and verification

### CMS & Content
- **[CMS](CMS.md)** — Hooks, filters, content types, menus, revisions
- **[Admin Panel & Media Library](ADMIN.md)** — Role-based admin UI, image processing

### Advanced Features
- **[Notifications](NOTIFICATIONS.md)** — Multi-channel: log, database, mail, broadcast, Slack, webhook
- **[Full-Text Search](SEARCH.md)** — NullDriver, database LIKE, MeiliSearch
- **[Queues](QUEUES.md)** — Background job processing, retry, batch
- **[Task Scheduling](SCHEDULING.md)** — Cron-based task scheduler
- **[WebSockets](WEBSOCKETS.md)** — Real-time broadcasting
- **[Multi-Tenancy](MULTI_TENANCY.md)** — SaaS database-per-tenant support
- **[Media & Files](MEDIA.md)** — Uploads, archive/zip drivers
- **[Validation](VALIDATION.md)** — Input validation rules and custom validators
- **[Encryption](ENCRYPTION.md)** — AES-256-GCM encryption helpers

### E-Commerce
- **[E-Commerce](ECOMMERCE.md)** — Payment gateways, catalog, cart, orders, inventory

### Development
- **[Testing](TESTING.md)** — TestCase, HTTP testing, fakes, database assertions
- **[Dependency Injection](DEPENDENCY_INJECTION.md)** — Container and service providers

### Maintenance
- **[Vendor Compression](CLI_COMMANDS.md#vendorcompress)** — Safe vendor tree reduction for release maintenance

### Plugins (Bundled)
- **[DebugBar Plugin](PLUGIN_DEBUGBAR.md)**
- **[Cloud Storage Plugin](PLUGIN_CLOUD.md)**
- **[Swagger / OpenAPI Plugin](PLUGIN_SWAGGER.md)**
- **[IDE Helper Plugin](PLUGIN_IDE.md)**
- **[Audit Log Plugin](PLUGIN_AUDIT.md)**

---

## Quick Examples

### Route → Controller → View

```php
// routes/web.php
$router->add('GET', '/posts', [\App\Controllers\PostController::class, 'index']);
```

```php
// app/Controllers/PostController.php
class PostController extends Controller {
    public function index() {
        return $this->render('posts.index', ['posts' => Post::published()->get()]);
    }
}
```

```
{{-- views/posts/index.nemesis.php --}}
@extends('layouts.app')
@section('content')
    @foreach ($posts as $post)
        <h2>{{ $post->title }}</h2>
    @endforeach
@endsection
```

### Notifications

```php
$user->notify(new OrderShipped($order));  // mail + database channels
```

### Full-Text Search

```php
$results = Post::search('nemesis framework')->limit(10)->get();
```

### Cart & Checkout

```php
$cart = Cart::instance();
$cart->add(Product::find(1), qty: 2);
Cart::registerCoupon('SAVE10', 'percent', 10);
$cart->applyCoupon('SAVE10');

$order  = Order::createFromCart($cart, auth()->id());
$charge = PaymentManager::charge($order->grandTotalCents(), $token);
$order->recordPayment($charge)->process();
```

### CMS Hooks

```php
addHook('post.published', fn($post) => SearchEngine::index(Post::class, $post->id, $post->toSearchArray()));
```

---

## Architecture Overview

```
Nemesis 5.0.0
├── Core              — DI container (PSR-11), bootstrap, config, router
├── ORM               — Fluent models, relationships, soft deletes, revisions
├── HTTP              — Request/Response, pipeline, middleware groups
├── Template Engine   — Blade-compatible compiler + view cache
├── Auth              — JWT, sessions, OAuth2, TOTP, roles, policies
├── Events            — Typed event dispatcher + hook/filter system
├── Plugin System     — Sandboxed plugins with manifest v2
├── CLI               — 40+ commands, Scaffolder, CommandBus, Scheduler
├── Testing           — TestCase, HTTP client, fakes, DB assertions
├── Assets            — Vite + Webpack manifests, HMR
├── Queue             — Sync, database, Redis drivers; retry, batch, chain
├── CMS               — Content types, taxonomies, menus, meta store
├── Admin             — RBAC admin panel, dashboard widgets
├── Media             — Upload, image resize, WebP, srcset
├── Notifications     — 6 channels: log, database, mail, broadcast, Slack, webhook
├── Search            — NullDriver, database, MeiliSearch
└── E-Commerce        — Payments, catalog, cart, orders, inventory
```

---

**PHP Requirement:** >= 8.2
**Test Suite:** 769 / 769 passing
