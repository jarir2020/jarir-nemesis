<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 13 — Admin Panel | Created: 2026-04-03
// Dashboard widget value object — a named, renderable panel for the admin dashboard.

namespace Nemesis\Admin;

/**
 * DashboardWidget — register and render admin dashboard panels.
 *
 * Usage:
 *   // Register
 *   DashboardWidget::register('posts_count', function(): string {
 *       return '<div class="widget">Posts: ' . Post::count() . '</div>';
 *   });
 *
 *   // Retrieve & render
 *   foreach (DashboardWidget::all() as $widget) {
 *       echo $widget->render();
 *   }
 *
 * Fluent factory:
 *   DashboardWidget::make('posts_count', fn() => '<p>42 posts</p>')
 *       ->title('Posts')
 *       ->icon('fa-file')
 *       ->column(1);
 */
class DashboardWidget
{
    /** @var array<string, self> */
    private static array $registry = [];

    private string   $name;
    private string   $title  = '';
    private string   $icon   = '';
    private int      $column = 1;   // grid column (1–4)
    private int      $order  = 0;
    private mixed    $renderer;     // callable(): string

    private function __construct(string $name, callable $renderer)
    {
        $this->name     = $name;
        $this->title    = ucwords(str_replace(['-', '_'], ' ', $name));
        $this->renderer = $renderer;
    }

    // -------------------------------------------------------------------------
    // Static factory & registry
    // -------------------------------------------------------------------------

    /**
     * Create a widget and register it immediately.
     *
     * @param  string   $name      Unique widget slug
     * @param  callable $renderer  fn(): string — returns HTML
     */
    public static function register(string $name, callable $renderer): static
    {
        $w = new static($name, $renderer);
        static::$registry[$name] = $w;
        return $w;
    }

    /** Alias of register() for fluent usage. */
    public static function make(string $name, callable $renderer): static
    {
        return static::register($name, $renderer);
    }

    /** Return all registered widgets sorted by order. */
    public static function all(): array
    {
        $widgets = array_values(static::$registry);
        usort($widgets, fn(self $a, self $b) => $a->order <=> $b->order);
        return $widgets;
    }

    /** Return a widget by name, or null. */
    public static function get(string $name): ?static
    {
        return static::$registry[$name] ?? null;
    }

    /** Check if a widget is registered. */
    public static function exists(string $name): bool
    {
        return isset(static::$registry[$name]);
    }

    /** Remove a widget. */
    public static function forget(string $name): void
    {
        unset(static::$registry[$name]);
    }

    /** Clear all widgets (test helper). */
    public static function reset(): void
    {
        static::$registry = [];
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /** Set the dashboard grid column (1–4). */
    public function column(int $column): static
    {
        $this->column = max(1, min(4, $column));
        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getName(): string  { return $this->name; }
    public function getTitle(): string { return $this->title; }
    public function getIcon(): string  { return $this->icon; }
    public function getColumn(): int   { return $this->column; }
    public function getOrder(): int    { return $this->order; }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Invoke the renderer and return the HTML string.
     * Any exception thrown by the renderer is caught and displayed as an error card.
     */
    public function render(): string
    {
        try {
            return (string) ($this->renderer)();
        } catch (\Throwable $e) {
            return '<div class="widget widget-error">Widget error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</div>';
        }
    }

    public function toArray(): array
    {
        return [
            'name'   => $this->name,
            'title'  => $this->title,
            'icon'   => $this->icon,
            'column' => $this->column,
            'order'  => $this->order,
        ];
    }
}
