<?php
declare(strict_types=1);
namespace Nemesis\Tokenizer;

/**
 * Nemesis template Lexer.
 *
 * Splits a template source string into a flat Token[] stream.
 * Handles (in priority order when overlapping):
 *   {{-- comment --}}      → TokenType::COMMENT
 *   {!! raw echo !!}       → TokenType::ECHO_RAW
 *   {{ escaped echo }}     → TokenType::ECHO_ESCAPED
 *   @directive(args)       → TokenType::DIRECTIVE  (balanced-paren extraction)
 *   everything else        → TokenType::TEXT
 *
 * Phase 11 | Added: 2026-04-03
 */
class Lexer
{
    /**
     * Tokenize a template source string.
     *
     * @param  string   $source
     * @return Token[]
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($source);
        $line   = 1;

        while ($offset < $length) {
            $next = $this->findNext($source, $offset);

            if ($next === null) {
                // Remaining source is plain text
                $text = substr($source, $offset);
                if ($text !== '') {
                    $tokens[] = new Token(TokenType::TEXT, $text, $line);
                }
                break;
            }

            [$type, $matchPos, $full] = $next;

            // Emit TEXT token for everything before this match
            if ($matchPos > $offset) {
                $text = substr($source, $offset, $matchPos - $offset);
                $tokens[] = new Token(TokenType::TEXT, $text, $line);
                $line += substr_count($text, "\n");
            }

            $tokens[] = new Token($type, $full, $line);
            $line     += substr_count($full, "\n");
            $offset    = $matchPos + strlen($full);
        }

        return $tokens;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find the next special token starting at $offset.
     *
     * Returns the earliest (leftmost) match among all candidate types,
     * or null if the rest of the source is plain text.
     *
     * @return array{TokenType, int, string}|null  [type, position, fullMatch]
     */
    private function findNext(string $source, int $offset): ?array
    {
        $best    = null;
        $bestPos = PHP_INT_MAX;

        // ── {{-- comment --}} ────────────────────────────────────────────────
        $pos = strpos($source, '{{--', $offset);
        if ($pos !== false && $pos < $bestPos) {
            $end = strpos($source, '--}}', $pos + 4);
            if ($end !== false) {
                $bestPos = $pos;
                $best    = [TokenType::COMMENT, $pos, substr($source, $pos, $end + 4 - $pos)];
            }
        }

        // ── {!! raw echo !!} ─────────────────────────────────────────────────
        $pos = strpos($source, '{!!', $offset);
        if ($pos !== false && $pos < $bestPos) {
            $end = strpos($source, '!!}', $pos + 3);
            if ($end !== false) {
                $bestPos = $pos;
                $best    = [TokenType::ECHO_RAW, $pos, substr($source, $pos, $end + 3 - $pos)];
            }
        }

        // ── {{ escaped echo }} (skip if it's actually {{--) ──────────────────
        $pos = strpos($source, '{{', $offset);
        if ($pos !== false && $pos < $bestPos && substr($source, $pos, 4) !== '{{--') {
            $end = strpos($source, '}}', $pos + 2);
            if ($end !== false) {
                $bestPos = $pos;
                $best    = [TokenType::ECHO_ESCAPED, $pos, substr($source, $pos, $end + 2 - $pos)];
            }
        }

        // ── @directive ───────────────────────────────────────────────────────
        if (preg_match('/@[a-zA-Z_]\w*/', $source, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $pos  = (int) $m[0][1];
            $name = $m[0][0];
            if ($pos < $bestPos) {
                $after = $pos + strlen($name);
                $args  = '';
                if ($after < strlen($source) && $source[$after] === '(') {
                    [$args] = $this->extractParens($source, $after);
                }
                $bestPos = $pos;
                $best    = [TokenType::DIRECTIVE, $pos, $name . $args];
            }
        }

        return $best;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extract a balanced parenthesised expression starting at $source[$pos].
     *
     * Correctly handles:
     *   - Nested parens:   @if(count($x) > 0)
     *   - String literals: @if($x === "(test)")  and  @if($x === '(ok)')
     *   - Escaped quotes:  @if($x === "say \"hi\"")
     *
     * @param  string $source
     * @param  int    $pos     Index of the opening '('
     * @return array{string, int}  [extracted_string_including_parens, bytes_consumed]
     */
    public function extractParens(string $source, int $pos): array
    {
        $depth   = 0;
        $i       = $pos;
        $len     = strlen($source);
        $inStr   = false;
        $strChar = '';

        while ($i < $len) {
            $ch = $source[$i];

            if ($inStr) {
                if ($ch === '\\') {
                    $i += 2; // skip escaped character
                    continue;
                }
                if ($ch === $strChar) {
                    $inStr = false;
                }
            } else {
                if ($ch === '"' || $ch === "'") {
                    $inStr   = true;
                    $strChar = $ch;
                } elseif ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
            }
            $i++;
        }

        $consumed = $i - $pos;
        return [substr($source, $pos, $consumed), $consumed];
    }
}
