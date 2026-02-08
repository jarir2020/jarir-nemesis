# 📘 Nemesis Framework - The Ultimate Developer Guide

Welcome to **Nemesis**, a lightweight, enterprise-ready PHP framework built from scratch to rival modern giants like Laravel. This guide covers every single feature of the framework, from basic routing to advanced multi-tenancy.

---

## 🚀 Part 1: Getting Started

### 1. Installation & Configuration
Clone the repository and install dependencies:
```bash
git clone https://github.com/jarir/nemesis.git
cd nemesis
composer install
```

**Environment Setup:**
1. Copy `.env.example` to `.env`.
2. Generate your secure application key:
   ```bash
   php nemesis key:generate
   ```
3. Configure your database in `.env`:
   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=nemesis
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### 2. Serving the Application
Start the built-in development server:
```bash
php nemesis serve
```
Visit `http://localhost:8000`.

### 3. Verification
Check if your environment is healthy:
```bash
php nemesis env:doctor
```

---

## 🛣️ Part 2: Core Architecture

### 1. Routing (`routes/route.php`)
Nemesis uses a performant tree-based router.

**Basic Routes:**
```php
$router->get('/', function() { return 'Home'; });
$router->post('/submit', [Controller::class, 'method']);
$router->any('/webhook', 'App\Controllers\Webhook@handle');
```

**Route Parameters:**
```php
$router->get('/user/{id}', function($id) {
    return "User ID: " . $id;
});
```

### 2. Controllers & Dependency Injection
Controllers reside in `app/Controllers`. The Service Container handles dependency injection automatically.

**Generate a Controller:**
```bash
php nemesis make:controller UserController
```

**Dependency Injection:**
Any type-hinted class in the constructor or method is auto-injected.
```php
class UserController extends Controller {
    public function __construct(Request $request, UserService $service) {
        // ...
    }
}
```

### 3. Request & Response
**Request Object:**
```php
$name = $request->input('name');
$all = $request->all();
$file = $request->file('avatar');
```

**Response Object:**
```php
return response()->json(['status' => 'ok'], 200);
return response()->view('welcome', ['name' => 'Jarir']);
```

---

## 🗄️ Part 3: Database & Fluent ORM

### 1. Migrations
Version control for your database schema.

**Commands:**
- `php nemesis make:migration create_users`
- `php nemesis migrate:run`
- `php nemesis migrate:rollback`

**Schema Builder (Supports ALTER):**
```php
Schema::create('users', function($table) {
    $table->id();
    $table->string('email')->unique();
    $table->timestamps();
});
```

### 2. Fluent Query Builder
Chainable interface for database operations.
```php
$users = DB::table('users')
    ->where('active', 1)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

### 3. Eloquent-style Models
Models reside in `app/Models`.

**Generate:** `php nemesis make:model User`

**CRUD Operations:**
```php
// Create
$user = User::create(['name' => 'John']);

// Read
$user = User::find(1);

// Update
$user->name = 'Jane';
$user->save();

// Delete
$user->delete();
```

**Relationships:**
```php
class User extends Model {
    public function posts() { return $this->hasMany(Post::class); }
}
// Usage: $user->posts; (Lazy loaded)
```

**New: Global Scopes:**
Protect data automatically (e.g., Soft Deletes).
```php
User::addGlobalScope('active', function($builder) {
    $builder->where('active', 1);
});
```

---

## 🛡️ Part 4: Security Features

### 1. Authentication
Native auth system.
```php
if (Auth::attempt(['email' => $e, 'password' => $p])) {
    return redirect('/dashboard');
}
```

### 2. RBAC (Roles & Permissions)
Built-in Role-Based Access Control.
```php
$user->assignRole('admin');
if ($user->can('delete-users')) { ... }
```

### 3. Cryptography (New!)
AES-256-CBC encryption service.
```php
$secret = Crypt::encrypt('my-secret');
$original = Crypt::decrypt($secret);
```

### 4. CSRF Protection
Automatic for all POST/PUT/DELETE requests via `VerifyCsrfToken` middleware.
Form helper: `<input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">`

---

## ⚡ Part 5: Modern SaaS Features

### 1. Multi-Tenancy
Build SaaS apps with single-database isolation.
```php
// Set Tenant Context
TenantManager::setTenant($tenantId);

// All queries are now scoped to this tenant automatically!
$orders = Order::all(); // WHERE tenant_id = ?
```

### 2. Real-time WebSockets
Native WebSocket server for live updates.
**Start Server:** `php nemesis websockets:serve`
**Broadcast Event:**
```php
$broadcaster->broadcast(['channel-1'], 'NewOrder', ['id' => 123]);
```

### 3. API Resources (New!)
Transform properties for JSON output.
```php
class UserResource extends JsonResource {
    public function toArray() {
        return [
            'id' => $this->resource->id,
            'fullname' => $this->resource->first . ' ' . $this->resource->last,
        ];
    }
}
```

---

## 🛠️ Part 6: Developer Tools (DevTools)

### 1. The "Super Command" (Resource Generator)
Generate CRUD stack in one go.
```bash
php nemesis make:resource Product --fields="name:string,price:decimal"
```
Creates: Model, Migration, Controller, Views, Routes, and Policy.

### 2. Database Tools
- **Dump:** `php nemesis db:dump backup.sql`
- **Restore:** `php nemesis db:restore backup.sql`
- **Seed:** `php nemesis db:seed`

### 3. Debugging & Insights
- **Healh Check:** `php nemesis model:health` (Finds N+1 queries)
- **Query Log:** `DB::enableQueryLog(); ... DB::getQueryLog();`
- **Tinker:** `php nemesis tinker` (Interactive Shell)

---

## 📋 Part 7: Testing

Nemesis includes a PHPUnit-compatible testing suite with zero dependencies.

**Run Tests:**
```bash
php nemesis test
```

**Write Tests:**
```php
class UserTest extends TestCase {
    public function test_login() {
        $this->post('/login', ['email' => 'test@test.com'])
             ->assertStatus(200);
    }
}
```

---

## 📦 Part 8: Advanced Services

### 1. Queues
Offload heavy tasks.
```php
Queue::push(new SendEmailJob($user));
```
**Worker:** `php nemesis queue:work`

### 2. Task Scheduling
Cron replacement. Define in `app/Console/Kernel.php`.
```php
$schedule->call(function() { ... })->dailyAt('13:00');
```
**Run:** `php nemesis schedule:run`

### 3. PDF & Image & Excel
Native libraries included.
- `PDF::loadHTML('<h1>Hi</h1>')->download('doc.pdf');`
- `Image::load('img.jpg')->resize(100, 100)->save('thumb.jpg');`
- `Spreadsheet::create(['Name', 'Age'], $data)->download('export.csv');`

---

**This framework is 100% written in native PHP components. Dive into `src/` to see how it works!**
