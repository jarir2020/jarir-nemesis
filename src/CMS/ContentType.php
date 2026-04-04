<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// Fluent content-type registry — register custom post types, meta fields, and taxonomies.

namespace Nemesis\CMS;

/**
 * ContentType — registry for custom content types.
 *
 * Usage:
 *   ContentType::register('portfolio', [
 *       'label'       => 'Portfolio',
 *       'description' => 'Portfolio items',
 *       'has_api'     => true,
 *       'has_meta'    => true,
 *       'taxonomies'  => ['category', 'tag'],
 *   ]);
 *
 *   ContentType::exists('portfolio');  // true
 *   ContentType::get('portfolio');     // ContentType instance
 *   ContentType::all();               // array of ContentType instances
 *
 * Fluent chaining:
 *   ContentType::register('portfolio')
 *       ->label('Portfolio')
 *       ->description('My work')
 *       ->enableApi()
 *       ->enableMeta()
 *       ->taxonomy('category');
 */
class ContentType
{
    /** @var array<string, self> */
    private static array $registry = [];

    // -------------------------------------------------------------------------
    // Core config
    // -------------------------------------------------------------------------

    private string $name;
    private string $label        = '';
    private string $description  = '';
    private bool   $hasApi       = false;
    private bool   $hasMeta      = false;
    private bool   $hasRevisions = false;
    /** @var list<string> attached taxonomy names */
    private array  $taxonomies   = [];
    /** @var array<string, array> meta field definitions */
    private array  $metaFields   = [];
    /** Extra arbitrary options */
    private array  $options      = [];

    private function __construct(string $name)
    {
        $this->name  = $name;
        $this->label = ucwords(str_replace(['-', '_'], ' ', $name));
    }

    // -------------------------------------------------------------------------
    // Static factory & registry
    // -------------------------------------------------------------------------

    /**
     * Register a new content type (or retrieve an existing one for further config).
     *
     * @param  string $name    Slug, e.g. 'portfolio'
     * @param  array  $config  Optional config array (same keys as fluent setters)
     */
    public static function register(string $name, array $config = []): static
    {
        if (!isset(static::$registry[$name])) {
            static::$registry[$name] = new static($name);
        }
        $ct = static::$registry[$name];

        // Apply array config
        if (isset($config['label']))       $ct->label($config['label']);
        if (isset($config['description'])) $ct->description($config['description']);
        if (!empty($config['has_api']))    $ct->enableApi();
        if (!empty($config['has_meta']))   $ct->enableMeta();
        if (!empty($config['has_revisions'])) $ct->enableRevisions();
        if (!empty($config['taxonomies'])) {
            foreach ((array) $config['taxonomies'] as $tax) {
                $ct->taxonomy($tax);
            }
        }
        if (!empty($config['meta_fields'])) {
            foreach ($config['meta_fields'] as $key => $def) {
                $ct->addMetaField($key, $def);
            }
        }
        foreach ($config as $k => $v) {
            if (!in_array($k, ['label','description','has_api','has_meta','has_revisions','taxonomies','meta_fields'], true)) {
                $ct->options[$k] = $v;
            }
        }

        return $ct;
    }

    /** Return a registered ContentType by name, or null. */
    public static function get(string $name): ?static
    {
        return static::$registry[$name] ?? null;
    }

    /** Return all registered ContentType instances keyed by name. */
    public static function all(): array
    {
        return static::$registry;
    }

    /** Check if a content type is registered. */
    public static function exists(string $name): bool
    {
        return isset(static::$registry[$name]);
    }

    /** Unregister a content type (useful in tests). */
    public static function forget(string $name): void
    {
        unset(static::$registry[$name]);
    }

    /** Clear all registrations (test helper). */
    public static function reset(): void
    {
        static::$registry = [];
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function enableApi(bool $enable = true): static
    {
        $this->hasApi = $enable;
        return $this;
    }

    public function enableMeta(bool $enable = true): static
    {
        $this->hasMeta = $enable;
        return $this;
    }

    public function enableRevisions(bool $enable = true): static
    {
        $this->hasRevisions = $enable;
        return $this;
    }

    /** Attach a taxonomy name (e.g. 'category', 'tag'). */
    public function taxonomy(string $name): static
    {
        if (!in_array($name, $this->taxonomies, true)) {
            $this->taxonomies[] = $name;
        }
        return $this;
    }

    /**
     * Define a meta field on this content type.
     *
     * @param  string $key    Field key
     * @param  array  $def    ['type'=>'text','label'=>'...','required'=>false]
     */
    public function addMetaField(string $key, array $def = []): static
    {
        $this->metaFields[$key] = array_merge(['type' => 'text', 'label' => $key, 'required' => false], $def);
        return $this;
    }

    /** Set an arbitrary option. */
    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getName(): string        { return $this->name; }
    public function getLabel(): string       { return $this->label; }
    public function getDescription(): string { return $this->description; }
    public function isApiEnabled(): bool     { return $this->hasApi; }
    public function isMetaEnabled(): bool    { return $this->hasMeta; }
    public function isRevisionsEnabled(): bool { return $this->hasRevisions; }
    public function getTaxonomies(): array   { return $this->taxonomies; }
    public function getMetaFields(): array   { return $this->metaFields; }
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'label'         => $this->label,
            'description'   => $this->description,
            'has_api'       => $this->hasApi,
            'has_meta'      => $this->hasMeta,
            'has_revisions' => $this->hasRevisions,
            'taxonomies'    => $this->taxonomies,
            'meta_fields'   => $this->metaFields,
            'options'       => $this->options,
        ];
    }
}
