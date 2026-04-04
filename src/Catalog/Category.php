<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Catalog;

/**
 * Category — product category value object with hierarchical support.
 */
class Category
{
    /** @var array<int, self> */
    private static array $registry = [];
    private static int   $seq      = 0;

    private function __construct(
        private int     $id,
        private string  $name,
        private string  $slug,
        private ?int    $parentId,
        private string  $description,
    ) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function make(string $name, ?int $parentId = null, array $extra = []): static
    {
        $id   = ++static::$seq;
        $slug = $extra['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $cat  = new static($id, $name, $slug, $parentId, $extra['description'] ?? '');
        static::$registry[$id] = $cat;
        return $cat;
    }

    public static function find(int $id): ?static       { return static::$registry[$id] ?? null; }
    public static function all(): array                 { return array_values(static::$registry); }
    public static function reset(): void                { static::$registry = []; static::$seq = 0; }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): int          { return $this->id; }
    public function getName(): string     { return $this->name; }
    public function getSlug(): string     { return $this->slug; }
    public function getParentId(): ?int   { return $this->parentId; }
    public function getDescription(): string { return $this->description; }
    public function isRoot(): bool        { return $this->parentId === null; }

    public function children(): array
    {
        return array_values(array_filter(
            static::$registry,
            fn(self $c) => $c->parentId === $this->id
        ));
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'parent_id'   => $this->parentId,
            'description' => $this->description,
        ];
    }
}
