<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 13 — Admin Panel | Created: 2026-04-03
// AdminPanel — central facade for the Nemesis admin area.

namespace Nemesis\Admin;

use Nemesis\Support\Form;
use Nemesis\Support\Table;

/**
 * AdminPanel — register models/content-types for auto-CRUD and manage the admin area.
 *
 * Usage:
 *   // Register a model for auto-CRUD in the admin
 *   AdminPanel::register('posts', [
 *       'model'       => \App\Models\Post::class,
 *       'label'       => 'Blog Posts',
 *       'columns'     => ['title', 'status', 'created_at'],
 *       'searchable'  => ['title', 'body'],
 *       'roles'       => [AdminPanel::ROLE_EDITOR],
 *   ]);
 *
 *   // Register a dashboard widget
 *   AdminPanel::widget('posts_count', fn() => '<p>' . Post::count() . ' posts</p>');
 *
 *   // Check access
 *   AdminPanel::canAccess('editor');      // true
 *   AdminPanel::canAccess('subscriber');  // false
 */
class AdminPanel
{
    // -------------------------------------------------------------------------
    // Role hierarchy (higher index = more permissions)
    // -------------------------------------------------------------------------

    public const ROLE_SUBSCRIBER  = 'subscriber';
    public const ROLE_CONTRIBUTOR = 'contributor';
    public const ROLE_AUTHOR      = 'author';
    public const ROLE_EDITOR      = 'editor';
    public const ROLE_ADMIN       = 'admin';

    /** Ordered from least to most privileged. */
    private const ROLE_HIERARCHY = [
        self::ROLE_SUBSCRIBER,
        self::ROLE_CONTRIBUTOR,
        self::ROLE_AUTHOR,
        self::ROLE_EDITOR,
        self::ROLE_ADMIN,
    ];

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    /** @var array<string, array> Registered entity configs keyed by slug */
    private static array $registered = [];

    /** @var array<string, array> Admin nav items keyed by slug */
    private static array $navItems   = [];

    /** @var array<string, array> Dashboard configuration */
    private static array $dashboardConfig = [
        'title' => 'Admin Dashboard',
        'subtitle' => 'Manage content, users, and settings.',
        'columns' => 3,
        'layout' => 'views/admin/layout.php',
    ];

    /** @var array<string, array> CRUD configuration keyed by slug */
    private static array $crudConfig = [];

    /** Prefix for all admin routes (default '/admin'). */
    private static string $prefix    = '/admin';

    /** Minimum role required to access the admin area. */
    private static string $minRole   = self::ROLE_AUTHOR;

    private static ?self $instance   = null;

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /** Clear all state (test helper). */
    public static function reset(): void
    {
        static::$registered = [];
        static::$navItems   = [];
        static::$crudConfig  = [];
        static::$dashboardConfig = [
            'title' => 'Admin Dashboard',
            'subtitle' => 'Manage content, users, and settings.',
            'columns' => 3,
            'layout' => 'views/admin/layout.php',
        ];
        static::$prefix     = '/admin';
        static::$minRole    = self::ROLE_AUTHOR;
        static::$instance   = null;
        DashboardWidget::reset();
        AdminComponent::reset();
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register a model or content type for admin CRUD.
     *
     * @param  string $slug    URL slug, e.g. 'posts'
     * @param  array  $config  Keys: model, label, columns, searchable, roles, icon
     */
    public static function register(string $slug, array $config = []): void
    {
        $normalized = array_merge([
            'slug'       => $slug,
            'label'      => ucwords(str_replace(['-', '_'], ' ', $slug)),
            'model'      => null,
            'columns'    => ['id', 'created_at'],
            'searchable' => [],
            'roles'      => [self::ROLE_EDITOR, self::ROLE_ADMIN],
            'icon'       => 'fa-list',
            'per_page'   => 20,
            'form_fields' => [],
            'table_columns' => [],
            'layout'     => static::$dashboardConfig['layout'],
            'component'  => null,
        ], $config, ['slug' => $slug]);

        static::$registered[$slug] = $normalized;
        static::$crudConfig[$slug] = array_merge([
            'per_page' => $normalized['per_page'],
            'columns' => $normalized['columns'],
            'searchable' => $normalized['searchable'],
            'form_fields' => $normalized['form_fields'],
            'table_columns' => $normalized['table_columns'],
            'layout' => $normalized['layout'],
        ], static::$crudConfig[$slug] ?? []);

        // Auto-add to nav
        static::$navItems[$slug] = [
            'label' => $normalized['label'],
            'url'   => static::$prefix . '/' . $slug,
            'icon'  => $normalized['icon'],
        ];
    }

    /**
     * Register a reusable admin component.
     */
    public static function component(string $name, callable $renderer, array $meta = []): AdminComponent
    {
        return AdminComponent::register($name, $renderer, $meta);
    }

    /**
     * Register a dashboard widget.
     *
     * @param  string   $name      Widget slug
     * @param  callable $renderer  fn(): string
     */
    public static function widget(string $name, callable $renderer): DashboardWidget
    {
        return DashboardWidget::register($name, $renderer);
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /** Return all registered entity configs. */
    public static function registered(): array
    {
        return static::$registered;
    }

    /** Return config for a specific registered entity. */
    public static function getConfig(string $slug): ?array
    {
        return static::$registered[$slug] ?? null;
    }

    /** Check if a slug is registered. */
    public static function has(string $slug): bool
    {
        return isset(static::$registered[$slug]);
    }

    /** Return the admin navigation items. */
    public static function navItems(): array
    {
        return static::$navItems;
    }

    /** Return all dashboard widgets (sorted by order). */
    public static function widgets(): array
    {
        return DashboardWidget::all();
    }

    /**
     * Return all registered admin components.
     */
    public static function components(): array
    {
        return AdminComponent::all();
    }

    /**
     * Get or update the dashboard configuration.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function dashboard(array $config = []): array
    {
        if ($config !== []) {
            static::$dashboardConfig = array_merge(static::$dashboardConfig, $config);
        }

        return static::$dashboardConfig;
    }

    /**
     * Get or update CRUD defaults for a registered entity.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function crud(string $slug, array $config = []): array
    {
        if (!isset(static::$crudConfig[$slug])) {
            static::$crudConfig[$slug] = [
                'per_page' => 20,
                'columns' => ['id', 'created_at'],
                'searchable' => [],
                'form_fields' => [],
                'table_columns' => [],
                'layout' => static::$dashboardConfig['layout'],
            ];
        }

        if ($config !== []) {
            static::$crudConfig[$slug] = array_merge(static::$crudConfig[$slug], $config);
        }

        return static::$crudConfig[$slug];
    }

    public static function formFor(string $slug, array $values = []): Form
    {
        $config = static::crud($slug);
        $form = Form::make(static::$prefix . '/' . $slug, 'POST')
            ->attr('class', 'admin-form admin-form-' . $slug);

        foreach ($config['form_fields'] ?: $config['columns'] as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $form->text($field, $values[$field] ?? '');
        }

        return $form;
    }

    public static function tableFor(string $slug, array $rows = []): Table
    {
        $config = static::crud($slug);
        $headers = $config['table_columns'] ?: $config['columns'];
        return Table::make($headers, $rows)->attr('class', 'admin-table admin-table-' . $slug);
    }

    // -------------------------------------------------------------------------
    // Role / access control
    // -------------------------------------------------------------------------

    /**
     * Check whether a given role has at least the minimum required access.
     *
     * @param  string $userRole     The role the user has (e.g. 'editor')
     * @param  string $requiredRole Minimum required role (defaults to admin-wide minimum)
     */
    public static function canAccess(string $userRole, string $requiredRole = null): bool
    {
        $required = $requiredRole ?? static::$minRole;
        $userIdx  = array_search($userRole, self::ROLE_HIERARCHY, true);
        $reqIdx   = array_search($required, self::ROLE_HIERARCHY, true);

        if ($userIdx === false || $reqIdx === false) return false;
        return $userIdx >= $reqIdx;
    }

    /**
     * Check whether a role can manage a specific registered entity.
     * Entity-level roles override the global minimum.
     */
    public static function canManage(string $slug, string $userRole): bool
    {
        $config = static::$registered[$slug] ?? null;
        if ($config === null) return false;

        $allowedRoles = $config['roles'] ?? [self::ROLE_EDITOR, self::ROLE_ADMIN];
        if (in_array($userRole, $allowedRoles, true)) return true;

        // Also accept if user role is higher than all allowed roles
        $userIdx = array_search($userRole, self::ROLE_HIERARCHY, true);
        foreach ($allowedRoles as $r) {
            $rIdx = array_search($r, self::ROLE_HIERARCHY, true);
            if ($rIdx !== false && $userIdx >= $rIdx) return true;
        }
        return false;
    }

    /** Return all valid role names. */
    public static function roles(): array
    {
        return self::ROLE_HIERARCHY;
    }

    /** Set the global minimum role to access the admin area. */
    public static function setMinRole(string $role): void
    {
        static::$minRole = $role;
    }

    // -------------------------------------------------------------------------
    // Route generation
    // -------------------------------------------------------------------------

    /**
     * Generate CRUD route definitions for all registered entities.
     * Returns an array of route specs, one per action per entity.
     *
     * Each entry: ['method','uri','action','slug','label']
     */
    public static function getCrudRoutes(): array
    {
        $routes = [];
        $prefix = static::$prefix;

        // Dashboard
        $routes[] = ['method' => 'GET', 'uri' => $prefix,             'action' => 'dashboard', 'slug' => '_dashboard', 'label' => 'Dashboard'];

        foreach (static::$registered as $slug => $config) {
            $base = $prefix . '/' . $slug;
            $routes[] = ['method' => 'GET',    'uri' => $base,              'action' => 'index',   'slug' => $slug, 'label' => $config['label']];
            $routes[] = ['method' => 'GET',    'uri' => $base . '/create',  'action' => 'create',  'slug' => $slug, 'label' => $config['label']];
            $routes[] = ['method' => 'POST',   'uri' => $base,              'action' => 'store',   'slug' => $slug, 'label' => $config['label']];
            $routes[] = ['method' => 'GET',    'uri' => $base . '/{id}',    'action' => 'show',    'slug' => $slug, 'label' => $config['label']];
            $routes[] = ['method' => 'GET',    'uri' => $base . '/{id}/edit','action' => 'edit',   'slug' => $slug, 'label' => $config['label']];
            $routes[] = ['method' => 'PUT',    'uri' => $base . '/{id}',    'action' => 'update',  'slug' => $slug, 'label' => $config['label']];
            $routes[] = ['method' => 'DELETE', 'uri' => $base . '/{id}',    'action' => 'destroy', 'slug' => $slug, 'label' => $config['label']];
        }

        return $routes;
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public static function setPrefix(string $prefix): void
    {
        static::$prefix = rtrim($prefix, '/');
    }

    public static function getPrefix(): string
    {
        return static::$prefix;
    }

    /**
     * Return the registered CRUD config snapshot.
     *
     * @return array<string, array>
     */
    public static function crudConfig(): array
    {
        return static::$crudConfig;
    }
}
