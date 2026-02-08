# Authorization & RBAC Documentation

Nemesis provides a robust authorization system through Gates, Policies, and Role-Based Access Control (RBAC).

---

## Gates

Gates are closures that determine if a user is authorized to perform a given action.

### Defining Gates
Define gates in a ServiceProvider or bootstrap file:

```php
use Nemesis\Auth\Gate;

Gate::define('update-post', function ($user, $post) {
    return $user->id === $post->user_id;
});
```

### Checking Gates

```php
if (Gate::allows('update-post', $user, $post)) {
    // The user can update the post...
}

if (Gate::denies('update-post', $user, $post)) {
    // The user cannot update the post...
}
```

---

## Policies

Policies are classes that organize authorization logic around a particular model or resource.

### Defining Policies

```php
class PostPolicy {
    public function update($user, $post) {
        return $user->id === $post->user_id;
    }
}

// Register the policy
Gate::policy(Post::class, PostPolicy::class);
```

### Checking Policies
Gates will automatically look for policies if an object is passed.

```php
if (Gate::allows('update', $post)) {
    //
}
```

---

## Role-Based Access Control (RBAC)

Nemesis includes a built-in RBAC system via the `HasRoles` trait.

### Setup
Add the trait to your `User` model:

```php
use Nemesis\Auth\Traits\HasRoles;

class User extends Model {
    use HasRoles;
}
```

### Database Schema
The RBAC system expects the following tables:
- `roles`: `id`, `name`, `slug`
- `permissions`: `id`, `name`, `slug`
- `user_roles`: `user_id`, `role_id`
- `role_permissions`: `role_id`, `permission_id`

### Usage

```php
$user = User::find(1);

// Checking Roles
if ($user->hasRole('admin')) {
    //
}

// Checking Permissions
if ($user->hasPermission('create-post')) {
    //
}
```

---

## Integration with Gates

Gates automatically fallback to checking the user's permissions if no direct ability or policy is found.

```php
// This will return true if the user has a permission with slug 'delete-user'
Gate::allows('delete-user', $user);
```
