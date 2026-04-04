<?php
declare(strict_types=1);
namespace Nemesis\Tokenizer;

/**
 * Token types produced by the Nemesis template Lexer.
 * Phase 11 | Added: 2026-04-03
 */
enum TokenType
{
    /** Raw HTML / plain text passthrough. */
    case TEXT;

    /** {{ $expr }} — HTML-escaped echo. */
    case ECHO_ESCAPED;

    /** {!! $expr !!} — raw (unescaped) echo. */
    case ECHO_RAW;

    /** {{-- comment --}} — stripped at compile time. */
    case COMMENT;

    /** @directive(...) — Blade-compatible directive. */
    case DIRECTIVE;
}
