<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// Menu — hierarchical navigation registry with HTML rendering.

namespace Nemesis\CMS;

/**
 * Menu — register and render named navigation menus.
 *
 * Usage:
 *   Menu::register('main', [
 *       MenuItem::make('Home', '/'),
 *       MenuItem::make('Blog', '/blog')->order(2),
 *       MenuItem::make('About', '/about')->order(3)
 *           ->addChild(MenuItem::make('Team', '/about/team')),
 *   ]);
 *
 *   // Or fluent
 *   Menu::create('main')
 *       ->add(MenuItem::make('Home', '/'))
 *       ->add(MenuItem::make('Blog', '/blog'));
 *
 *   // Retrieve
 *   $nav = Menu::get('main');
 *   echo $nav->render('nav-list');
 *
 *   // Via helper
 *   echo menu('main', true);
 */
class Menu
{
    /** @var array<string, self> */
    private static array $registry = [];

    private string  $name;
    private string  $label = '';
    /** @var list<MenuItem> */
    private array   $items = [];

    private function __construct(string $name)
    {
        $this->name  = $name;
        $this->label = ucwords(str_replace(['-', '_'], ' ', $name));
    }

    // -------------------------------------------------------------------------
    // Static factory & registry
    // -------------------------------------------------------------------------

    /**
     * Register a named menu with an array of MenuItems.
     *
     * @param  string       $name   Menu slug, e.g. 'main', 'footer'
     * @param  MenuItem[]   $items  Top-level menu items
     */
    public static function register(string $name, array $items = []): static
    {
        if (!isset(static::$registry[$name])) {
            static::$registry[$name] = new static($name);
        }
        $m = static::$registry[$name];
        foreach ($items as $item) {
            if ($item instanceof MenuItem) {
                $m->add($item);
            }
        }
        return $m;
    }

    /**
     * Create (or retrieve) a Menu for fluent building.
     */
    public static function create(string $name): static
    {
        if (!isset(static::$registry[$name])) {
            static::$registry[$name] = new static($name);
        }
        return static::$registry[$name];
    }

    /** Retrieve a Menu by slug, or null. */
    public static function get(string $name): ?static
    {
        return static::$registry[$name] ?? null;
    }

    /** Return all registered menus. */
    public static function all(): array
    {
        return static::$registry;
    }

    /** Check if a menu is registered. */
    public static function exists(string $name): bool
    {
        return isset(static::$registry[$name]);
    }

    /** Unregister a specific menu. */
    public static function forget(string $name): void
    {
        unset(static::$registry[$name]);
    }

    /** Clear all menus (test helper). */
    public static function reset(): void
    {
        static::$registry = [];
    }

    // -------------------------------------------------------------------------
    // Instance API
    // -------------------------------------------------------------------------

    /** Add a MenuItem to this menu. */
    public function add(MenuItem $item): static
    {
        $this->items[] = $item;
        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getName(): string  { return $this->name; }
    public function getLabel(): string { return $this->label; }

    /** @return list<MenuItem> sorted by order ascending */
    public function items(): array
    {
        $items = $this->items;
        usort($items, fn(MenuItem $a, MenuItem $b) => $a->getOrder() <=> $b->getOrder());
        return $items;
    }

    public function count(): int { return count($this->items); }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Render the menu as a <ul> tree.
     *
     * @param  string $ulClass    CSS class(es) for the outer <ul>
     * @param  string $liClass    CSS class(es) for each <li>
     * @param  string $activeClass CSS class appended to active items
     */
    public function render(
        string $ulClass    = '',
        string $liClass    = '',
        string $activeClass = 'active'
    ): string {
        if (empty($this->items)) return '';

        $class = $ulClass ? " class=\"{$ulClass}\"" : '';
        $html  = "<ul{$class}>";

        foreach ($this->items() as $item) {
            $html .= $item->renderLi($liClass, $activeClass);
        }

        $html .= '</ul>';
        return $html;
    }

    public function toArray(): array
    {
        return [
            'name'  => $this->name,
            'label' => $this->label,
            'items' => array_map(fn(MenuItem $i) => $i->toArray(), $this->items()),
        ];
    }
}
