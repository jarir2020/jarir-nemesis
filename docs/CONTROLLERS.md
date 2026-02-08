# Controllers

## Overview

Controllers can group related request handling logic into a single class. Controllers are stored in the `app/Http/Controllers` directory.

---

## Basic Controller

Here is an example of a basic controller class. Note that the controller extends the base controller class included with Nemesis:

```php
<?php

namespace App\Http\Controllers;

use Nemesis\Core\Controller;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Nemesis\Core\View
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        return view('user.profile', ['user' => $user]);
    }
}
```

You can define a route to this controller action like so:

```php
use App\Http\Controllers\UserController;
use Nemesis\Support\Facades\Route;

Route::get('/user/{id}', [UserController::class, 'show']);
```

---

## Resource Controllers

Resource routing assigns the typical "CRUD" routes to a controller with a single line of code.

### Actions Handled by Resource Controller

| Verb      | URI                 | Action  | Route Name     |
|-----------|---------------------|---------|----------------|
| GET       | `/photos`           | index   | photos.index   |
| GET       | `/photos/create`    | create  | photos.create  |
| POST      | `/photos`           | store   | photos.store   |
| GET       | `/photos/{photo}`   | show    | photos.show    |
| GET       | `/photos/{photo}/edit` | edit | photos.edit    |
| PUT/PATCH | `/photos/{photo}`   | update  | photos.update  |
| DELETE    | `/photos/{photo}`   | destroy | photos.destroy |

---

## Dependency Injection

The Nemesis service container is used to resolve all Nemesis controllers. As a result, you are able to type-hint any dependencies your controller may need in its constructor.

```php
use App\Repositories\UserRepository;

class UserController extends Controller
{
    protected $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }
}
```

---

## Authorization

Controllers can use the `authorize` method to verify user permissions (if using the `Gate` facade).

```php
public function update(Request $request, $id)
{
    $post = Post::findOrFail($id);
    
    $this->authorize('update', $post);
    
    // The current user can update the blog post...
}
```
