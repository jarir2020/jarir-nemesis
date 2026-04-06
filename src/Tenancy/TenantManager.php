<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy: TenantManager | 2026-04-06

namespace Nemesis\Tenancy;

use Nemesis\Core\Database;

/**
 * TenantManager — central hub for multi-tenancy.
 *
 * Strategies:
 *   A. shared_database    — one DB, every table has tenant_id. Use TenantScope trait.
 *   B. database_per_tenant — per-tenant PDO connection via switchConnection().
 *
 * Usage:
 *   $tenant = TenantManager::identify($host, $path, $headers);
 *   $tenant = TenantManager::current();
 *   $id     = TenantManager::id();
 *   $tenant = TenantManager::create(['name' => 'Acme', 'slug' => 'acme']);
 */
class TenantManager
{
    private static ?Tenant         $current  = null;
    private static ?TenantResolver $resolver = null;

    // -------------------------------------------------------------------------
    // Identification
    // -------------------------------------------------------------------------

    public static function identify(
        string $host    = '',
        string $path    = '',
        array  $headers = [],
        array  $query   = [],
    ): ?Tenant {
        $resolver = self::$resolver ?? new TenantResolver(self::loadConfig());
        $slug     = $resolver->resolve($host, $path, $headers, $query);

        if ($slug === null) return null;

        $tenant = self::findBySlug($slug) ?? self::findByDomain($host);

        if ($tenant && $tenant->active) {
            self::setCurrent($tenant);
            return $tenant;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Current tenant context
    // -------------------------------------------------------------------------

    public static function setCurrent(Tenant $tenant): void { self::$current = $tenant; }
    public static function current(): ?Tenant               { return self::$current; }
    public static function id(): int|string|null            { return self::$current?->id; }
    public static function check(): bool                    { return self::$current !== null; }
    public static function forget(): void                   { self::$current = null; }

    // -------------------------------------------------------------------------
    // DB-per-tenant strategy
    // -------------------------------------------------------------------------

    public static function switchConnection(Tenant $tenant): void
    {
        $cfg    = self::loadConfig();
        $driver = $cfg['db_per_tenant']['driver'] ?? 'sqlite';
        $dbName = $tenant->database ?: (($cfg['db_per_tenant']['prefix'] ?? 'tenant_') . $tenant->slug);

        if ($driver === 'sqlite') {
            $pdo = new \PDO("sqlite:{$dbName}");
        } else {
            $host = $cfg['db_per_tenant']['host'] ?? '127.0.0.1';
            $port = $cfg['db_per_tenant']['port'] ?? 3306;
            $user = $cfg['db_per_tenant']['user'] ?? '';
            $pass = $cfg['db_per_tenant']['pass'] ?? '';
            $pdo  = new \PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        Database::setPdo($pdo);
    }

    // -------------------------------------------------------------------------
    // Tenant-aware helpers
    // -------------------------------------------------------------------------

    public static function cachePrefix(): string     { return self::$current ? self::$current->cachePrefix() : ''; }
    public static function storagePath(string $base = ''): string { return self::$current ? self::$current->storagePath($base) : ''; }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public static function create(array $attributes): Tenant
    {
        self::ensureTable();

        $name     = trim($attributes['name']     ?? '');
        $slug     = trim($attributes['slug']     ?? self::slugify($name));
        $domain   = trim($attributes['domain']   ?? '');
        $database = trim($attributes['database'] ?? '');
        $meta     = json_encode($attributes['meta'] ?? []);
        $now      = date('Y-m-d H:i:s');

        Database::statement(
            "INSERT INTO tenants (name, slug, domain, database, active, meta, created_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)",
            [$name, $slug, $domain, $database, $meta, $now]
        );

        $id = (int) Database::connect()->lastInsertId();
        return self::find($id) ?? new Tenant($id, $name, $slug, $domain, $database, true, [], $now);
    }

    public static function find(int|string $id): ?Tenant
    {
        self::ensureTable();
        $row = Database::view("SELECT * FROM tenants WHERE id = ? LIMIT 1", [(int) $id]);
        return !empty($row) ? Tenant::fromRow($row[0]) : null;
    }

    public static function findBySlug(string $slug): ?Tenant
    {
        self::ensureTable();
        $row = Database::view("SELECT * FROM tenants WHERE slug = ? LIMIT 1", [$slug]);
        return !empty($row) ? Tenant::fromRow($row[0]) : null;
    }

    public static function findByDomain(string $domain): ?Tenant
    {
        self::ensureTable();
        $row = Database::view("SELECT * FROM tenants WHERE domain = ? LIMIT 1", [$domain]);
        return !empty($row) ? Tenant::fromRow($row[0]) : null;
    }

    /** @return list<Tenant> */
    public static function all(): array
    {
        self::ensureTable();
        $rows = Database::view("SELECT * FROM tenants ORDER BY id ASC");
        return array_map(fn($r) => Tenant::fromRow($r), $rows);
    }

    public static function delete(int $id): bool
    {
        self::ensureTable();
        Database::statement("DELETE FROM tenants WHERE id = ?", [$id]);
        return true;
    }

    public static function activate(int $id): void
    {
        Database::statement("UPDATE tenants SET active = 1 WHERE id = ?", [$id]);
    }

    public static function deactivate(int $id): void
    {
        Database::statement("UPDATE tenants SET active = 0 WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------------------
    // Config & resolver
    // -------------------------------------------------------------------------

    public static function setResolver(TenantResolver $resolver): void { self::$resolver = $resolver; }

    private static function loadConfig(): array
    {
        $paths = [
            defined('NEMESIS_BASE_PATH') ? NEMESIS_BASE_PATH . '/config/tenancy.php' : '',
            __DIR__ . '/../../../config/tenancy.php',
        ];
        foreach ($paths as $p) {
            if ($p && file_exists($p)) return (array) require $p;
        }
        return [];
    }

    // -------------------------------------------------------------------------
    // DB scaffold (auto-creates tenants table)
    // -------------------------------------------------------------------------

    public static function ensureTable(): void
    {
        $pdo    = Database::getPdo();
        $driver = $pdo ? strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) : 'sqlite';

        if ($driver === 'sqlite') {
            Database::unprepared("CREATE TABLE IF NOT EXISTS tenants (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                slug        TEXT NOT NULL UNIQUE,
                domain      TEXT NOT NULL DEFAULT '',
                database    TEXT NOT NULL DEFAULT '',
                active      INTEGER NOT NULL DEFAULT 1,
                meta        TEXT NOT NULL DEFAULT '{}',
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            Database::unprepared("CREATE TABLE IF NOT EXISTS tenants (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                slug        VARCHAR(100) NOT NULL UNIQUE,
                domain      VARCHAR(255) NOT NULL DEFAULT '',
                `database`  VARCHAR(255) NOT NULL DEFAULT '',
                active      TINYINT(1) NOT NULL DEFAULT 1,
                meta        TEXT NOT NULL DEFAULT '{}',
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB");
        }
    }

    // -------------------------------------------------------------------------
    // Testing
    // -------------------------------------------------------------------------

    public static function reset(): void
    {
        self::$current  = null;
        self::$resolver = null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
