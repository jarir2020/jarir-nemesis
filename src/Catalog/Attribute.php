<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Catalog;

/**
 * Attribute — a named product attribute (e.g. Color, Size) with allowed values.
 *
 * Usage:
 *   $color = Attribute::make('Color', ['Red', 'Blue', 'Green']);
 *   $size  = Attribute::make('Size',  ['S', 'M', 'L', 'XL']);
 */
class Attribute
{
    /** @var array<int, self> */
    private static array $registry = [];
    private static int   $seq      = 0;

    private function __construct(
        private int    $id,
        private string $name,
        private array  $values,  // list<string> of allowed values
    ) {}

    public static function make(string $name, array $values = []): static
    {
        $id  = ++static::$seq;
        $att = new static($id, $name, $values);
        static::$registry[$id] = $att;
        return $att;
    }

    public static function find(int $id): ?static { return static::$registry[$id] ?? null; }
    public static function all(): array           { return array_values(static::$registry); }
    public static function reset(): void          { static::$registry = []; static::$seq = 0; }

    public function getId(): int      { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getValues(): array { return $this->values; }

    public function addValue(string $value): void
    {
        if (!in_array($value, $this->values, true)) {
            $this->values[] = $value;
        }
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'values' => $this->values];
    }
}
