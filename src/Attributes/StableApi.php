<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Attributes;

/**
 * Marks a class or method as part of the stable Nemesis public API.
 * Stable APIs follow semantic versioning — no breaking changes in minor versions.
 * Safe to depend on in application code and third-party plugins.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class StableApi {}
