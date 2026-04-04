<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Catalog;

/**
 * Product — the core catalog entity.
 *
 * Usage:
 *   $product = Product::make('T-Shirt', 1999)
 *       ->sku('TS-001')
 *       ->category($clothingCat)
 *       ->description('A soft cotton t-shirt.')
 *       ->addAttribute('Color', 'Red')
 *       ->addVariant(new Variant(['sku'=>'TS-001-RED-M','attributes'=>['Color'=>'Red','Size'=>'M'],'price'=>1999,'stock'=>10]));
 */
class Product
{
    /** @var array<int, self> */
    private static array $registry = [];
    private static int   $seq      = 0;

    private int      $id;
    private string   $name;
    private int      $priceCents;   // base price in smallest currency unit
    private string   $sku          = '';
    private string   $description  = '';
    private string   $status       = 'draft';   // draft|published|archived
    private ?int     $categoryId   = null;
    /** @var array<string,list<string>> attribute_name → [values] */
    private array    $attributes   = [];
    /** @var list<Variant> */
    private array    $variants     = [];
    /** @var list<string> image URLs */
    private array    $images       = [];
    private array    $meta         = [];

    private function __construct(string $name, int $priceCents)
    {
        $this->id         = ++static::$seq;
        $this->name       = $name;
        $this->priceCents = $priceCents;
        static::$registry[$this->id] = $this;
    }

    // -------------------------------------------------------------------------
    // Static factory & registry
    // -------------------------------------------------------------------------

    public static function make(string $name, int $priceCents): static
    {
        return new static($name, $priceCents);
    }

    public static function find(int $id): ?static { return static::$registry[$id] ?? null; }

    public static function all(): array { return array_values(static::$registry); }

    public static function published(): array
    {
        return array_values(array_filter(static::$registry, fn(self $p) => $p->status === 'published'));
    }

    public static function reset(): void
    {
        static::$registry = [];
        static::$seq      = 0;
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function sku(string $sku): static            { $this->sku = $sku; return $this; }
    public function description(string $d): static      { $this->description = $d; return $this; }
    public function status(string $s): static           { $this->status = $s; return $this; }
    public function publish(): static                   { $this->status = 'published'; return $this; }
    public function archive(): static                   { $this->status = 'archived'; return $this; }
    public function image(string $url): static          { $this->images[] = $url; return $this; }
    public function meta(string $k, mixed $v): static   { $this->meta[$k] = $v; return $this; }

    public function category(Category $cat): static
    {
        $this->categoryId = $cat->getId();
        return $this;
    }

    public function price(int $cents): static
    {
        $this->priceCents = $cents;
        return $this;
    }

    /**
     * Add an attribute value (e.g. addAttribute('Color', 'Red')).
     */
    public function addAttribute(string $name, string $value): static
    {
        $this->attributes[$name][] = $value;
        return $this;
    }

    public function addVariant(Variant $variant): static
    {
        $this->variants[] = $variant;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): int           { return $this->id; }
    public function getName(): string      { return $this->name; }
    public function getPriceCents(): int   { return $this->priceCents; }
    public function getPrice(): string     { return number_format($this->priceCents / 100, 2); }
    public function getSku(): string       { return $this->sku; }
    public function getDescription(): string { return $this->description; }
    public function getStatus(): string    { return $this->status; }
    public function getCategoryId(): ?int  { return $this->categoryId; }
    public function getAttributes(): array { return $this->attributes; }
    public function getVariants(): array   { return $this->variants; }
    public function getImages(): array     { return $this->images; }
    public function getMeta(string $k, mixed $default = null): mixed { return $this->meta[$k] ?? $default; }
    public function isPublished(): bool    { return $this->status === 'published'; }
    public function hasVariants(): bool    { return !empty($this->variants); }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'sku'         => $this->sku,
            'price_cents' => $this->priceCents,
            'price'       => $this->getPrice(),
            'description' => $this->description,
            'status'      => $this->status,
            'category_id' => $this->categoryId,
            'attributes'  => $this->attributes,
            'variants'    => array_map(fn(Variant $v) => $v->toArray(), $this->variants),
            'images'      => $this->images,
        ];
    }
}
