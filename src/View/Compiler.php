<?php
declare(strict_types=1);
namespace Nemesis\View;

use Nemesis\Tokenizer\Lexer;
use Nemesis\Tokenizer\TokenType;

/**
 * Nemesis template Compiler.
 *
 * Converts a Nemesis/Blade-compatible template source string into
 * executable PHP via a Lexer token stream.
 *
 * Supported directives:
 *   Echo:        {{ }}, {!! !!}
 *   Control:     @if @elseif @else @endif @unless @endunless
 *   Loops:       @foreach @endforeach @for @endfor @while @endwhile @break @continue
 *   Layout:      @extends @section @endsection @stop @show @yield @parent @hasSection
 *   Includes:    @include @includeIf
 *   Forms:       @csrf @method
 *   Assets:      @vite @asset
 *   Components:  @component
 *   PHP:         @php @endphp
 *   Misc:        @empty @endempty @isset @endisset @dump @dd
 *   Comments:    {{-- --}}  (stripped)
 *   Custom:      DirectiveRegistry::register()
 *
 * Phase 11 | Added: 2026-04-03
 */
class Compiler
{
    private Lexer $lexer;

    public function __construct()
    {
        $this->lexer = new Lexer();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compile a template source string to PHP.
     *
     * @param  string $source  Raw template content (.blade.php or .php)
     * @return string          Compiled PHP source ready to require()
     */
    public function compile(string $source): string
    {
        $tokens = $this->lexer->tokenize($source);
        $output = '';

        foreach ($tokens as $token) {
            $output .= match ($token->type) {
                TokenType::TEXT         => $token->value,
                TokenType::COMMENT      => '',
                TokenType::ECHO_ESCAPED => $this->compileEchoEscaped($token->value),
                TokenType::ECHO_RAW     => $this->compileEchoRaw($token->value),
                TokenType::DIRECTIVE    => $this->compileDirective($token->value, $token->line),
            };
        }

        return $output;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Echo tokens
    // ─────────────────────────────────────────────────────────────────────────

    private function compileEchoEscaped(string $raw): string
    {
        // Strip {{ and }}
        $expr = trim(substr($raw, 2, strlen($raw) - 4));
        return "<?php echo htmlspecialchars((string)({$expr}), ENT_QUOTES, 'UTF-8'); ?>";
    }

    private function compileEchoRaw(string $raw): string
    {
        // Strip {!! and !!}
        $expr = trim(substr($raw, 3, strlen($raw) - 6));
        return "<?php echo {$expr}; ?>";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Directive dispatch
    // ─────────────────────────────────────────────────────────────────────────

    private function compileDirective(string $raw, int $line): string
    {
        // Split @name(args) into name and args
        if (!preg_match('/^@([a-zA-Z_]\w*)([\s\S]*)$/s', $raw, $m)) {
            return $raw;
        }

        $name = strtolower($m[1]);
        $args = trim($m[2]); // e.g. "('foo', $bar)" or ""

        // Custom directives take priority over built-ins
        if (DirectiveRegistry::has($name)) {
            return DirectiveRegistry::call($name, $args);
        }

        return match ($name) {
            // ── Control ──────────────────────────────────────────────────────
            'if'          => "<?php if{$args}: ?>",
            'elseif'      => "<?php elseif{$args}: ?>",
            'else'        => '<?php else: ?>',
            'endif'       => '<?php endif; ?>',
            'unless'      => "<?php if(!({$this->inner($args)})): ?>",
            'endunless'   => '<?php endif; ?>',

            // ── Loops ─────────────────────────────────────────────────────────
            'foreach'     => "<?php foreach{$args}: ?>",
            'endforeach'  => '<?php endforeach; ?>',
            'for'         => "<?php for{$args}: ?>",
            'endfor'      => '<?php endfor; ?>',
            'while'       => "<?php while{$args}: ?>",
            'endwhile'    => '<?php endwhile; ?>',
            'break'       => $args ? "<?php if{$args} { break; } ?>" : '<?php break; ?>',
            'continue'    => $args ? "<?php if{$args} { continue; } ?>" : '<?php continue; ?>',

            // ── Layout ────────────────────────────────────────────────────────
            'extends'     => "<?php \$__engine->setLayout({$this->inner($args)}); ?>",
            'section'     => $this->compileSection($args),
            'endsection',
            'stop'        => '<?php $__engine->endSection(); ?>',
            'show'        => '<?php $__engine->endSection(); ?>',
            'yield'       => $this->compileYield($args),
            'parent'      => '<?php echo $__engine->yieldParentSection(); ?>',
            'hassection'  => "<?php if(\$__engine->hasSection({$this->inner($args)})): ?>",

            // ── Includes ──────────────────────────────────────────────────────
            'include'     => $this->compileInclude($args),
            'includeif'   => $this->compileIncludeIf($args),

            // ── Forms ─────────────────────────────────────────────────────────
            'csrf'        => '<?php echo \'<input type="hidden" name="_token" value="\' . htmlspecialchars((string)(function_exists(\'csrf_token\') ? csrf_token() : \'\'), ENT_QUOTES, \'UTF-8\') . \'">\'; ?>',
            'method'      => "<?php echo '<input type=\"hidden\" name=\"_method\" value=\"' . htmlspecialchars(strtoupper({$this->inner($args)}), ENT_QUOTES, 'UTF-8') . '\">'; ?>",

            // ── Assets ────────────────────────────────────────────────────────
            'vite'        => "<?php echo function_exists('vite') ? vite({$this->inner($args)}) : ''; ?>",
            'asset'       => "<?php echo function_exists('asset') ? asset({$this->inner($args)}) : ''; ?>",

            // ── Components ────────────────────────────────────────────────────
            'component'   => $this->compileComponent($args),

            // ── Raw PHP ───────────────────────────────────────────────────────
            'php'         => $args ? "<?php {$this->inner($args)}; ?>" : '<?php',
            'endphp'      => '?>',

            // ── Misc ──────────────────────────────────────────────────────────
            'empty'       => "<?php if(empty{$args}): ?>",
            'endempty'    => '<?php endif; ?>',
            'isset'       => "<?php if(isset{$args}): ?>",
            'endisset'    => '<?php endif; ?>',
            'dump'        => "<?php var_dump{$args}; ?>",
            'dd'          => "<?php var_dump{$args}; exit(1); ?>",

            // ── Unknown — pass through verbatim ───────────────────────────────
            default       => $raw,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Directive helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Strip the outer parentheses from an args string.
     * "(foo, $bar)" → "foo, $bar"
     */
    private function inner(string $args): string
    {
        $args = trim($args);
        if (str_starts_with($args, '(') && str_ends_with($args, ')')) {
            return substr($args, 1, strlen($args) - 2);
        }
        return $args;
    }

    /**
     * Split "name, value" at the FIRST top-level comma (skipping nested parens/quotes).
     *
     * @return string[]  Two-element array [name, rest] or [full] if no top-level comma.
     */
    private function splitFirstComma(string $expr): array
    {
        $depth   = 0;
        $inStr   = false;
        $strChar = '';
        $len     = strlen($expr);

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];
            if ($inStr) {
                if ($ch === '\\') { $i++; continue; }
                if ($ch === $strChar) { $inStr = false; }
            } else {
                if ($ch === '"' || $ch === "'") { $inStr = true; $strChar = $ch; }
                elseif ($ch === '(' || $ch === '[') { $depth++; }
                elseif ($ch === ')' || $ch === ']') { $depth--; }
                elseif ($ch === ',' && $depth === 0) {
                    return [trim(substr($expr, 0, $i)), trim(substr($expr, $i + 1))];
                }
            }
        }

        return [$expr];
    }

    private function compileSection(string $args): string
    {
        $inner = $this->inner($args);
        $parts = $this->splitFirstComma($inner);

        if (count($parts) === 2) {
            // @section('name', 'inline content') — no @endsection needed
            [$name, $content] = $parts;
            return "<?php \$__engine->setSection({$name}, (string)({$content})); ?>";
        }

        // @section('name') … @endsection
        return "<?php \$__engine->startSection({$inner}); ob_start(); ?>";
    }

    private function compileYield(string $args): string
    {
        $inner = $this->inner($args);
        $parts = $this->splitFirstComma($inner);

        if (count($parts) === 2) {
            [$name, $default] = $parts;
            return "<?php echo \$__engine->yieldSection({$name}, (string)({$default})); ?>";
        }

        return "<?php echo \$__engine->yieldSection({$inner}); ?>";
    }

    private function compileInclude(string $args): string
    {
        $inner = $this->inner($args);
        $parts = $this->splitFirstComma($inner);

        if (count($parts) === 2) {
            [$view, $data] = $parts;
            return "<?php echo \$__engine->render({$view}, array_merge(\$__data, {$data})); ?>";
        }

        return "<?php echo \$__engine->render({$inner}, \$__data); ?>";
    }

    private function compileIncludeIf(string $args): string
    {
        $inner = $this->inner($args);
        $parts = $this->splitFirstComma($inner);

        if (count($parts) === 2) {
            [$view, $data] = $parts;
            return "<?php if(\$__engine->exists({$view})) { echo \$__engine->render({$view}, array_merge(\$__data, {$data})); } ?>";
        }

        return "<?php if(\$__engine->exists({$inner})) { echo \$__engine->render({$inner}, \$__data); } ?>";
    }

    private function compileComponent(string $args): string
    {
        $inner = $this->inner($args);
        $parts = $this->splitFirstComma($inner);

        if (count($parts) === 2) {
            [$view, $data] = $parts;
            return "<?php echo \$__engine->render({$view}, array_merge(\$__data, {$data})); ?>";
        }

        return "<?php echo \$__engine->render({$inner}, \$__data); ?>";
    }
}
