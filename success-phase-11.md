# Phase 11 — Template Engine (Blade-Compatible) ✓

**Completed:** 2026-04-03  
**Tests:** 69 new tests, 526/526 total passing (0 failures)

---

## What Was Built

### Tokenizer Layer (`src/Tokenizer/`)

| File | Purpose |
|---|---|
| `TokenType.php` | PHP 8.1 enum — TEXT, ECHO_ESCAPED, ECHO_RAW, COMMENT, DIRECTIVE |
| `Token.php` | Immutable value object — type, value, line number |
| `Lexer.php` | Single-pass tokenizer; balanced-paren extraction for nested args |

### View Layer (`src/View/`)

| File | Purpose |
|---|---|
| `Compiler.php` | Token[] → PHP source; dispatches all directives |
| `Engine.php` | Compile + cache + render; sections, layout inheritance, $__engine scope |
| `DirectiveRegistry.php` | Extensible custom @directive registration |

### Updated
- `src/Core/View.php` — rewritten to delegate to Engine; `render()`, `make()`, `exists()`, `addPath()` retained
- `storage/views/` — compiled view cache directory (mtime-invalidated)

---

## Directives Supported

| Category | Directives |
|---|---|
| Echo | `{{ }}` (escaped), `{!! !!}` (raw), `{{-- --}}` (comment, stripped) |
| Control | `@if @elseif @else @endif @unless @endunless` |
| Loops | `@foreach @endforeach @for @endfor @while @endwhile @break @continue` |
| Layout | `@extends @section @endsection @stop @show @yield @parent @hasSection` |
| Includes | `@include @includeIf` |
| Forms | `@csrf @method` |
| Assets | `@vite @asset` |
| Components | `@component` |
| PHP | `@php @endphp` |
| Misc | `@empty @endempty @isset @endisset @dump @dd` |
| Custom | `DirectiveRegistry::register('name', fn)` |

---

## Key Design Decisions

- **Compile-time caching** — templates compiled once to `storage/views/{md5}.php`, recompiled only when source mtime changes
- **Layout inheritance** — child view runs first, captures sections, then parent is rendered; `$this->sections` persists across the child→layout chain
- **Isolated scope** — compiled views execute in a static closure; `$__engine`, `$__data`, and extracted data vars are the only injected names
- **Balanced paren extraction** — Lexer walks character-by-character to handle `@if(count($x) > 0)` and string literals with parens
- **Backwards compatible** — `View::render()` still echoes; `View::make()` returns string

---

## Test Coverage

- `Phase11LexerTest` (12 tests) — tokenization, line tracking, balanced parens
- `Phase11CompilerTest` (27 tests) — all directives, round-trip eval
- `Phase11EngineTest` (19 tests) — resolution, caching, rendering, layout inheritance, @include
- `Phase11DirectiveRegistryTest` (4 tests) — custom directives, case-insensitivity
- `Phase11ViewFacadeTest` (4 tests) — static facade, singleton
