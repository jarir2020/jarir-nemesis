<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for repository classes.
 * Implement this in app/Repositories/ to abstract data access.
 */
interface RepositoryInterface
{
    public function all(): mixed;
    public function find(int|string $id): mixed;
    public function create(array $data): mixed;
    public function update(int|string $id, array $data): bool;
    public function delete(int|string $id): bool;
}
