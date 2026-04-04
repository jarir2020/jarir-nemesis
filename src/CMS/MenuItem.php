<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// Menu item value object — one node in a hierarchical navigation tree.

namespace Nemesis\CMS;

/**
 * MenuItem — immutable-ish value object for a single navigation item.
 *
 * Usage:
 *   $item = MenuItem::make('Home', '/')
 *               ->icon('home')
 *               ->order(1);
 *
 *   $item->addChild(MenuItem::make('About', '/about'));
 */
class MenuItem
{
    private string  $label;
    private string  $url;
    private string  $icon   = '';
    private int     $order  = 0;
    private ?string $target = null;  // '_blank', '_self', etc.
    private array   $attrs  = [];    // extra HTML attributes for <a>
    /** @var list<MenuItem> */
    private array   $children = [];

    private function __construct(string $label, string $url)
    {
        $this->label = $label;
        $this->url   = $url;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function make(string $label, string $url): static
    {
        return new static($label, $url);
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

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

    public function target(string $target): static
    {
        $this->target = $target;
        return $this;
    }

    /** Add an arbitrary HTML attribute to the <a> element. */
    public function attr(string $key, string $value): static
    {
        $this->attrs[$key] = $value;
        return $this;
    }

    public function addChild(self $child): static
    {
        $this->children[] = $child;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getLabel(): string    { return $this->label; }
    public function getUrl(): string      { return $this->url; }
    public function getIcon(): string     { return $this->icon; }
    public function getOrder(): int       { return $this->order; }
    public function getTarget(): ?string  { return $this->target; }
    public function getAttrs(): array     { return $this->attrs; }

    /** @return list<MenuItem> sorted by order ascending */
    public function getChildren(): array
    {
        $children = $this->children;
        usort($children, fn(self $a, self $b) => $a->getOrder() <=> $b->getOrder());
        return $children;
    }

    public function hasChildren(): bool { return !empty($this->children); }

    /**
     * Detect whether this item is "active" (URL matches current request URI).
     * Exact match, or prefix match for non-root URLs.
     */
    public function isActive(): bool
    {
        $current = $_SERVER['REQUEST_URI'] ?? '/';
        $url     = $this->url;

        if ($url === '/' || $url === '') {
            return $current === '/';
        }
        // Strip query string for comparison
        $current = strtok($current, '?') ?: $current;
        return $current === $url || str_starts_with($current, rtrim($url, '/') . '/');
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Render this item as an <li> element (with optional nested <ul> for children).
     */
    public function renderLi(string $liClass = '', string $activeClass = 'active'): string
    {
        $active   = $this->isActive() ? " {$activeClass}" : '';
        $liClass  = $liClass ? " class=\"{$liClass}{$active}\"" : ($active ? " class=\"{$active}\"" : '');
        $target   = $this->target ? " target=\"{$this->target}\"" : '';
        $icon     = $this->icon ? "<i class=\"{$this->icon}\"></i> " : '';

        $extraAttrs = '';
        foreach ($this->attrs as $k => $v) {
            $extraAttrs .= ' ' . htmlspecialchars($k, ENT_QUOTES) . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }

        $url   = htmlspecialchars($this->url, ENT_QUOTES);
        $label = htmlspecialchars($this->label, ENT_QUOTES);

        $html = "<li{$liClass}><a href=\"{$url}\"{$target}{$extraAttrs}>{$icon}{$label}</a>";

        if ($this->hasChildren()) {
            $html .= '<ul>';
            foreach ($this->getChildren() as $child) {
                $html .= $child->renderLi($liClass, $activeClass);
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }

    public function toArray(): array
    {
        return [
            'label'    => $this->label,
            'url'      => $this->url,
            'icon'     => $this->icon,
            'order'    => $this->order,
            'target'   => $this->target,
            'attrs'    => $this->attrs,
            'children' => array_map(fn(self $c) => $c->toArray(), $this->getChildren()),
        ];
    }
}
