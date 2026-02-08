# Relationships Documentation

Nemesis supports the most common database relationships, allowing you to easily link models together and access related data.

---

## One-to-One

A one-to-one relationship is a very basic relation. For example, a `User` model might be associated with one `Account`.

```php
// User.php
public function account() {
    return $this->hasOne(Account::class);
}

// Account.php
public function user() {
    return $this->belongsTo(User::class);
}
```

**Usage:**
```php
$user = User::find(1);
$account = $user->account; // Returns Account model instance
```

---

## One-to-Many

A one-to-many relationship is used to define relationships where a single model is the parent to one or more child models. For example, a `Post` that has an infinite number of `Comments`.

```php
// Post.php
public function comments() {
    return $this->hasMany(Comment::class);
}

// Comment.php
public function post() {
    return $this->belongsTo(Post::class);
}
```

**Usage:**
```php
$post = Post::find(1);
$comments = $post->comments; // Returns array of Comment models

foreach ($comments as $comment) {
    echo $comment->content;
}
```

---

## Many-to-Many

Many-to-many relations are slightly more complicated than `hasOne` and `hasMany`. An example of such a relationship is a `User` who has many `Roles`, where the roles are also shared by other users.

Many-to-many relationships require a "pivot" table (e.g., `role_user`).

```php
// User.php
public function roles() {
    return $this->belongsToMany(Role::class);
}

// Role.php
public function users() {
    return $this->belongsToMany(User::class);
}
```

**Usage:**
```php
$user = User::find(1);
$roles = $user->roles; // Returns array of Role models
```

### Custom Pivot Tables
You may customize the pivot table name and the foreign key names:
```php
return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
```

---

## Loading Relationships

### Dynamic Properties
Relationships are accessed as "dynamic properties". Nemesis will automatically load the relationship the first time it is accessed.

### Method Access
If you want to add further constraints to the relationship query, you can call the relationship method:

```php
$activeComments = $post->comments()->where('active', 1)->get();
```
