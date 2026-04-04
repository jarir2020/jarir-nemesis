<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for Nemesis ORM models.
 */
interface ModelInterface
{
    public static function all(): mixed;
    public static function find(int|string $id): static|null;
    public static function create(array $attributes): static;
    public function save(): bool;
    public function delete(): bool;
    public function toArray(): array;
}
