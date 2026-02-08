# Migrations Documentation

Migrations are version control for your database schema. They allow you to define and share database structure changes with your team.

---

## CLI Commands

Nemesis provides several CLI commands to manage your migrations:

```bash
# Create a new migration
php nemesis make:migration create_users_table

# Run all pending migrations
php nemesis migrate:run

# Rollback the last migration batch
php nemesis migrate:rollback
```

---

## Migration Structure

Migrations are stored in `database/migrations/`. Each migration file contains a class that extends `Nemesis\Database\Migration`.

```php
<?php

use Nemesis\Database\Migration;
use Nemesis\Core\Database;

class create_users_table extends Migration {
    /**
     * Run the migrations.
     */
    public function up() {
        Database::connect()->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=INNODB;");
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Database::connect()->exec("DROP TABLE IF EXISTS users;");
    }
}
```

---

## Schema Builder (Experimental)

Nemesis includes a minimal Schema builder for modifying existing tables. This is an alternative to raw SQL for certain operations.

### Modifying Tables
Use `Schema::table` to alter existing tables.

```php
use Nemesis\Database\Schema;
use Nemesis\Database\Blueprint;

Schema::table('users', function(Blueprint $table) {
    $table->string('phone', 20); // Adds a VARCHAR(20) column
    $table->integer('age');      // Adds an INT column
});
```

### Dropping Columns
```php
Schema::table('users', function($table) {
    $table->dropColumn('phone');
});
```

---

## Best Practices

1. **Keep it Reversible**: Always implement the `down()` method so you can rollback changes.
2. **Version Controlled**: Always commit your migration files to your repository.
3. **One Change per File**: Avoid putting too many changes in one migration; it makes debugging hard.
4. **Use ENGINE=INNODB**: This is the default and recommended engine for foreign key support.
