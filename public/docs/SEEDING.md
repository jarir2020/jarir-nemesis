# Seeding Documentation

Database seeding allows you to populate your database with test or initial data. This is particularly useful for setting up administrative users, default settings, or test data for development.

---

## CLI Commands

Nemesis provides commands to create and run seeders:

```bash
# Create a new seeder
php nemesis make:seeder UserSeeder

# Run all seeders
php nemesis db:seed

# Run a specific seeder
php nemesis db:seed UserSeeder
```

---

## Seeder Structure

Seeders are stored in `database/seeders/`. Each seeder class extends `Nemesis\Database\Seeder` and implements the `run()` method.

### Example Seeder

```php
<?php

use Nemesis\Database\Seeder;
use Nemesis\Core\Database;

class UserSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run() {
        // Using Raw SQL
        Database::connect()->exec("INSERT INTO users (username, email, password) VALUES ('admin', 'admin@example.com', 'hashed_password')");

        // Using Query Builder
        Database::table('users')->insert([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT)
        ]);
    }
}
```

---

## Running Seeders

When you run `php nemesis db:seed`, the framework will look for all `.php` files in `database/seeders/` and execute their `run()` methods.

### Batch Seeding
If you want to control the order or run specific seeders, you can run them individually:

```bash
php nemesis db:seed RoleSeeder
php nemesis db:seed PermissionSeeder
php nemesis db:seed UserSeeder
```

---

## Best Practices

1. **Idempotency**: Try to write seeders that can be run multiple times without causing errors (e.g., using `INSERT IGNORE` or checking if data exists).
2. **Use Factories**: For large amounts of test data, consider using a factory pattern or a library like Faker.
3. **Environment Specific**: You can check the environment in your seeder to avoid seeding production databases with test data.
4. **Relationship Order**: Be mindful of foreign key constraints when seeding tables that depend on each other. Seed parents before children.
