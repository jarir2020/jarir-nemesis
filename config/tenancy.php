<?php
// Nemesis 6.0 | Phase 21 — Multi-tenancy config | 2026-04-06

return [

    /*
    |--------------------------------------------------------------------------
    | Tenancy Strategy
    |--------------------------------------------------------------------------
    | 'shared_database'   — one DB, all tables carry a tenant_id column.
    |                       Mix the TenantScope trait into your models.
    | 'database_per_tenant' — each tenant gets its own PDO connection.
    |                         Call TenantManager::switchConnection($tenant).
    */
    'strategy' => env('TENANCY_STRATEGY', 'shared_database'),

    /*
    |--------------------------------------------------------------------------
    | Identification Drivers (checked left-to-right until one resolves)
    |--------------------------------------------------------------------------
    | Supported: 'subdomain', 'header', 'path', 'query'
    */
    'identification' => array_filter(
        explode(',', (string) (getenv('TENANT_IDENTIFICATION') ?: 'subdomain,header,path'))
    ),

    /*
    |--------------------------------------------------------------------------
    | Subdomain Strategy
    |--------------------------------------------------------------------------
    | acme.myapp.com  →  slug = 'acme'   (base = 'myapp.com')
    */
    'subdomain_base' => env('TENANT_SUBDOMAIN_BASE', 'example.com'),

    /*
    |--------------------------------------------------------------------------
    | Header Strategy
    |--------------------------------------------------------------------------
    | HTTP header used to pass the tenant slug:  X-Tenant-ID: acme
    */
    'header_key' => env('TENANT_HEADER', 'X-Tenant-ID'),

    /*
    |--------------------------------------------------------------------------
    | Path Strategy
    |--------------------------------------------------------------------------
    | Which URI segment carries the slug (1 = first segment after '/').
    | /acme/dashboard  →  segment 1 = 'acme'
    */
    'path_segment' => (int) (getenv('TENANT_PATH_SEGMENT') ?: 1),

    /*
    |--------------------------------------------------------------------------
    | Excluded Slugs
    |--------------------------------------------------------------------------
    | Slugs (subdomains / path segments / header values) that must NOT be
    | treated as tenants — reserved for system routes, CDN, API gateway, etc.
    */
    'excluded' => ['www', 'api', 'admin', 'app', 'mail', 'static', 'assets'],

    /*
    |--------------------------------------------------------------------------
    | DB-per-tenant Connection Settings
    |--------------------------------------------------------------------------
    | Used only when strategy = 'database_per_tenant'.
    | TenantManager::switchConnection() will build a PDO with these params.
    */
    'db_per_tenant' => [
        'driver' => env('TENANT_DB_DRIVER', 'sqlite'),   // 'sqlite' | 'mysql'
        'host'   => env('TENANT_DB_HOST',   '127.0.0.1'),
        'port'   => (int) (getenv('TENANT_DB_PORT') ?: 3306),
        'user'   => env('TENANT_DB_USER',   ''),
        'pass'   => env('TENANT_DB_PASS',   ''),
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),  // prefix_<slug> when no explicit DB name
    ],

];
