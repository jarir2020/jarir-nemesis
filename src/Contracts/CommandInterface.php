<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for console commands.
 */
interface CommandInterface
{
    public function handle(): int;
    public function getName(): string;
    public function getDescription(): string;
}
