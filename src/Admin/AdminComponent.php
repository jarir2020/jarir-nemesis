<?php
declare(strict_types=1);

namespace Nemesis\Admin;

/**
 * AdminComponent — reusable admin panel component with registry support.
 */
class AdminComponent
{
    /** @var array<string, self> */
    private static array $registry = [];

    private string $name;
    private string $title = '';
    private string $icon = '';
    private int $order = 0;
    private string $section = 'dashboard';
    private mixed $renderer;
    private array $meta = [];

    private function __construct(string $name, callable $renderer, array $meta = [])
    {
        $this->name = $name;
        $this->title = ucwords(str_replace(['-', '_'], ' ', $name));
        $this->renderer = $renderer;
        $this->meta = $meta;
        $this->icon = (string) ($meta['icon'] ?? '');
        $this->section = (string) ($meta['section'] ?? 'dashboard');
        $this->order = (int) ($meta['order'] ?? 0);
    }

    public static function register(string $name, callable $renderer, array $meta = []): static
    {
        $component = new static($name, $renderer, $meta);
        static::$registry[$name] = $component;
        return $component;
    }

    public static function make(string $name, callable $renderer, array $meta = []): static
    {
        return static::register($name, $renderer, $meta);
    }

    public static function all(): array
    {
        $components = array_values(static::$registry);
        usort($components, fn(self $a, self $b) => $a->order <=> $b->order);
        return $components;
    }

    public static function get(string $name): ?static
    {
        return static::$registry[$name] ?? null;
    }

    public static function exists(string $name): bool
    {
        return isset(static::$registry[$name]);
    }

    public static function forget(string $name): void
    {
        unset(static::$registry[$name]);
    }

    public static function reset(): void
    {
        static::$registry = [];
    }

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

    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function section(string $section): static
    {
        $this->section = $section;
        return $this;
    }

    public function render(): string
    {
        try {
            return (string) ($this->renderer)($this->meta);
        } catch (\Throwable $e) {
            return '<div class="admin-component admin-component-error">Admin component error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</div>';
        }
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'icon' => $this->icon,
            'order' => $this->order,
            'section' => $this->section,
            'meta' => $this->meta,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getSection(): string
    {
        return $this->section;
    }
}
