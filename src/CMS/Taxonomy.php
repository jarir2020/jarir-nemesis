<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// Taxonomy registry — categories, tags, and custom classification systems.

namespace Nemesis\CMS;

/**
 * Taxonomy — register classification systems and attach them to content types.
 *
 * Usage:
 *   Taxonomy::register('category', ['label' => 'Categories', 'hierarchical' => true]);
 *   Taxonomy::register('tag',      ['label' => 'Tags']);
 *   Taxonomy::attach('category', 'post');
 *   Taxonomy::attach('category', 'portfolio');
 *
 *   Taxonomy::for('portfolio');     // → ['category']
 *   Taxonomy::get('category');      // → Taxonomy instance
 *   Taxonomy::all();               // → array of Taxonomy instances
 */
class Taxonomy
{
    /** @var array<string, self> */
    private static array $registry = [];

    /** @var array<string, list<string>>  taxonomy → content types */
    private static array $attachments = [];

    // -------------------------------------------------------------------------
    // Core config
    // -------------------------------------------------------------------------

    private string $name;
    private string $label         = '';
    private string $singularLabel = '';
    private string $description   = '';
    private bool   $hierarchical  = false;   // true = category-style, false = tag-style
    private bool   $hasApi        = false;
    private array  $options       = [];

    private function __construct(string $name)
    {
        $this->name         = $name;
        $this->label        = ucwords(str_replace(['-', '_'], ' ', $name)) . 's';
        $this->singularLabel = ucwords(str_replace(['-', '_'], ' ', $name));
    }

    // -------------------------------------------------------------------------
    // Static factory & registry
    // -------------------------------------------------------------------------

    /**
     * Register a taxonomy (or update config on an existing one).
     *
     * @param  string $name   Slug, e.g. 'category'
     * @param  array  $config Optional: label, singular_label, description, hierarchical, has_api
     */
    public static function register(string $name, array $config = []): static
    {
        if (!isset(static::$registry[$name])) {
            static::$registry[$name] = new static($name);
        }
        $t = static::$registry[$name];

        if (isset($config['label']))          $t->label($config['label']);
        if (isset($config['singular_label'])) $t->singularLabel($config['singular_label']);
        if (isset($config['description']))    $t->description($config['description']);
        if (isset($config['hierarchical']))   $t->hierarchical((bool) $config['hierarchical']);
        if (!empty($config['has_api']))       $t->enableApi();

        // Attach to content types declared in config
        if (!empty($config['content_types'])) {
            foreach ((array) $config['content_types'] as $ct) {
                static::attach($name, $ct);
            }
        }

        return $t;
    }

    /**
     * Attach a taxonomy to a content type.
     * Registers the taxonomy first if it doesn't exist yet.
     */
    public static function attach(string $taxonomy, string $contentType): void
    {
        if (!isset(static::$registry[$taxonomy])) {
            static::register($taxonomy);
        }
        if (!in_array($contentType, static::$attachments[$taxonomy] ?? [], true)) {
            static::$attachments[$taxonomy][] = $contentType;
        }
        // Mirror on ContentType if it is registered
        if (ContentType::exists($contentType)) {
            ContentType::get($contentType)?->taxonomy($taxonomy);
        }
    }

    /** Return taxonomies attached to a specific content type. */
    public static function for(string $contentType): array
    {
        $result = [];
        foreach (static::$attachments as $tax => $types) {
            if (in_array($contentType, $types, true)) {
                $result[] = $tax;
            }
        }
        return $result;
    }

    /** Return a Taxonomy by name, or null. */
    public static function get(string $name): ?static
    {
        return static::$registry[$name] ?? null;
    }

    /** Return all registered Taxonomy instances. */
    public static function all(): array
    {
        return static::$registry;
    }

    /** Check if a taxonomy is registered. */
    public static function exists(string $name): bool
    {
        return isset(static::$registry[$name]);
    }

    /** Clear all registrations (test helper). */
    public static function reset(): void
    {
        static::$registry    = [];
        static::$attachments = [];
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function singularLabel(string $label): static
    {
        $this->singularLabel = $label;
        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function hierarchical(bool $value = true): static
    {
        $this->hierarchical = $value;
        return $this;
    }

    public function enableApi(bool $enable = true): static
    {
        $this->hasApi = $enable;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getName(): string          { return $this->name; }
    public function getLabel(): string         { return $this->label; }
    public function getSingularLabel(): string { return $this->singularLabel; }
    public function getDescription(): string   { return $this->description; }
    public function isHierarchical(): bool     { return $this->hierarchical; }
    public function isApiEnabled(): bool       { return $this->hasApi; }

    /** Return all content types this taxonomy is attached to. */
    public function getContentTypes(): array
    {
        return static::$attachments[$this->name] ?? [];
    }

    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'label'         => $this->label,
            'singular_label'=> $this->singularLabel,
            'description'   => $this->description,
            'hierarchical'  => $this->hierarchical,
            'has_api'       => $this->hasApi,
            'content_types' => $this->getContentTypes(),
        ];
    }
}
