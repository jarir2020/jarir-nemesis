<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Attributes;

/**
 * Marks a class or method as internal to the Nemesis framework.
 * Internal APIs have no stability guarantee and may change between minor versions.
 * Do NOT depend on classes marked with this attribute in application code.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class InternalApi {}
