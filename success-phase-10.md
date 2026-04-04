# Phase 10 — Frontend / Asset Pipeline (Vite & Webpack) ✓ COMPLETE
**Completed:** 2026-04-03

## Summary
Phase 10 wires Nemesis to modern JS build tools. Developers can pick Vite (default, recommended) or Webpack and get versioned asset URLs, HMR support, and CLI scaffolding out of the box.

---

## Deliverables

### Core Asset Layer (`src/Assets/`)

| File | Purpose |
|------|---------|
| `ManifestInterface.php` | Contract: `url()`, `loaded()`, `all()` |
| `ViteManifest.php` | Reads Vite 5 `manifest.json`; HMR-aware (`@vite/client` injection); emits `<script type="module">` + `<link>` tags |
| `WebpackManifest.php` | Reads Webpack Mix `mix-manifest.json`; emits `<script defer>` + `<link>` tags |
| `AssetManager.php` | Unified facade — `url()`, `tags()`, `vite()`, `viteTags()`, `mix()`, `hasManifest()`, `driver()`, `boot()`, `flush()` |

### Configuration (`config/assets.php`)
- `driver` — `vite` / `webpack` / `none`
- `vite.manifest`, `vite.dev_url`, `vite.hot_file` — Vite settings
- `webpack.manifest`, `webpack.dev_url` — Webpack settings
- `entries` — default JS/CSS entry points
- `version` — manual cache-bust suffix when no manifest loaded

### Global Helpers (`src/Helpers/Helpers.php`)

| Helper | Description |
|--------|-------------|
| `asset(string $path)` | Versioned URL via active manifest; mtime fallback |
| `vite(string $path)` | Full Vite entry-point tags (with HMR) |
| `mix(string $path)` | Webpack Mix versioned URL |
| `asset_tags(string $path)` | Driver-appropriate `<script>`/`<link>` HTML |

### Resource Stubs
- `resources/js/app.js` — JS entry with CSRF bootstrap + axios interceptor comment
- `resources/css/app.css` — CSS entry with base resets

### Scaffolder Stubs (`src/Scaffolder/stubs/`)
- `vite.config.stub` — Vite 5 config (rollup inputs, outDir, HMR, hot-file)
- `webpack.config.stub` — Webpack 5 config (babel, CSS extract, manifest plugin)
- `package.vite.stub` — `package.json` for Vite projects
- `package.webpack.stub` — `package.json` for Webpack projects

### CLI Commands (`nemesis`)

| Command | Description |
|---------|-------------|
| `php nemesis vite:init [--force]` | Scaffold `vite.config.js` + `package.json`, create `resources/`, update `.env` |
| `php nemesis webpack:init [--force]` | Scaffold `webpack.config.js` + `package.json`, update `.env` |
| `php nemesis asset:publish` | Copy plugin `assets/` dirs into `public/vendor/{plugin}/` |
| `php nemesis frontend:install {stack}` | Install Alpine / Inertia / Livewire / jQuery / Ghost.js with bootstrap snippet |

---

## HMR (Hot Module Replacement) Flow
1. `npm run dev` starts Vite dev server on `:5173`
2. Vite writes `storage/framework/vite.hot`
3. `ViteManifest::isHot()` detects the file → all asset URLs point to dev server
4. `vite('resources/js/app.js')` emits `@vite/client` script + entry script
5. On `npm run build`, hot file is absent → manifest URLs used instead

---

## Tests

**`tests/unit/Phase10Test.php`** — 53 tests in 5 suites:

| Suite | Tests |
|-------|-------|
| `Phase10ManifestInterfaceTest` | 2 |
| `Phase10ViteManifestTest` | 10 |
| `Phase10WebpackManifestTest` | 8 |
| `Phase10AssetManagerTest` | 11 |
| `Phase10HelperFunctionTest` | 9 |
| `Phase10ConfigTest` | 13 |

**Full unit suite: 457 tests, 457 passed, 0 failed.**

---

## What's Next — Phase 11: Template Engine

The Blade-compatible template engine (Section O-1) unlocks the Admin Panel and all CMS features. It fills the empty `src/Tokenizer/` directory.

Key deliverables:
- `{{ $var }}` / `{!! $raw !!}` — escaped/raw output
- `@if` / `@elseif` / `@else` / `@endif`
- `@foreach` / `@for` / `@while`
- `@extends` / `@section` / `@yield` / `@include`
- `@csrf` / `@method` — form helpers
- `@vite` / `@asset` — asset tag directives
- Compiled → cached PHP in `storage/views/`
