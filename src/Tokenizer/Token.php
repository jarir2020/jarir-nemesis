<?php
declare(strict_types=1);
namespace Nemesis\Tokenizer;

/**
 * Immutable token produced by the Nemesis template Lexer.
 * Phase 11 | Added: 2026-04-03
 */
final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string    $value,
        public readonly int       $line = 0,
    ) {}
}
