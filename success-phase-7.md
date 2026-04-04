# Phase 7 — Events, Plugin System & CLI ✓

**Completed:** 2026-04-03
**Tests:** 33 new tests | 263/263 total passing (0 failures)

---

## What Was Done

### Events System (`src/Events/`)

**`src/Events/Event.php`** — Base class for all application events
- Implements `EventInterface`
- `stopPropagation()` / `isPropagationStopped()` — halts listener chain mid-flight

**`src/Events/EventDispatcher.php`** — Typed application event bus
- Static facade + singleton instance
- `listen(class, callable|class-string)` — register listeners
- `dispatch(object): object` — fire event, walk up class hierarchy
- `forget(?class)` — remove listeners for one event or all
- `hasListeners(class): bool`
- `discover(directory)` — auto-scan `app/Listeners/` via reflection (reads handle() type-hint)
- Propagation stops if event calls `stopPropagation()`

---

### Console I/O (`src/Console/`)

**`src/Console/Input.php`** — Typed CLI input
- Parses `$argv` into named arguments and `--options`
- `argument(name)`, `option(name)`, `hasOption(name)`
- `bindSignature(string)` — maps positional args to named args from `{arg}` syntax

**`src/Console/Output.php`** — Typed CLI output with ANSI colours
- `info()`, `success()`, `warn()`, `error()`, `line()`, `newLine()`
- `table(headers, rows)` — ASCII table renderer
- `progressBar(total, callback)` — inline progress bar
- `ask()`, `confirm()`, `choice()` — interactive prompts (read from STDIN)
- Auto-disables colours on non-TTY / Windows without ANSICON

**`src/Console/Command.php`** — Upgraded base command
- Now implements `CommandInterface`
- `run(Input, Output): int` — boots I/O, binds signature, calls `handle()`
- `handle(): int` abstract — return `Command::SUCCESS` (0) or `Command::FAILURE` (1)
- `getName()` extracts command name from signature string

**`src/Console/Scheduler.php`** — Clean scheduler (replaces `Schedule.php` global-$argv hack)
- `command(signature, args)` — schedule a CLI command (uses CommandBus if registered)
- `call(callable)` — schedule a closure
- Returns `ScheduledTask` with fluent frequency API:
  `everyMinute/everyFiveMinutes/everyTenMinutes/everyFifteenMinutes/everyThirtyMinutes/hourly/hourlyAt/daily/dailyAt/weekly/monthly/yearly/cron(expr)`
- Full 5-field cron expression parser (step `*/n`, range `a-b`, list `a,b,c`)
- `schedule:run` CLI command updated to use new Scheduler

---

### CommandBus (`src/Reactor/CommandBus.php`)

- `register(class)`, `registerAll([...])` — register Command classes by signature name
- `discoverIn(directory)` — auto-scan a directory for Command subclasses
- `has(name)`, `all()` — lookup
- `run(name, Input, Output): int` — resolve + execute
- `dispatch(argv): int` — full bootstrap from raw `$argv`
- `resetInstance()` — test helper
- `nemesis` CLI `default:` case now tries CommandBus before the old class-scan fallback
- Plugins: `PluginManager::bootstrap()` now calls `discoverIn(pluginDir/Commands/)`

---

### Scaffolder (`src/Scaffolder/`)

**`src/Scaffolder/Scaffolder.php`** — File generator from stubs
- `generate(type, name, tokens): string` — generates from stub + token replacement
- `generateWidget(name): list<string>` — class + view file in `app/Widgets/Name/`
- `generatePlugin(name): string` — full v2 plugin scaffold with manifest

**22 stubs in `src/Scaffolder/stubs/`:**
`controller`, `model`, `middleware`, `event`, `listener`, `job`, `policy`,
`migration`, `seeder`, `trait`, `repository`, `entity`, `dto`, `transformer`,
`manager`, `handler`, `interface`, `factory`, `filter`, `library`, `helper`, `widget`

---

### Plugin Manifest v2 (`src/Core/PluginManifest.php`)

New optional fields (backwards-compatible — v1 manifests still work):
- `getProvides(): array` — service bindings `["Interface" => "Driver"]`
- `getTags(): array` — categorisation tags `["storage", "cloud"]`
- `getConflicts(): array` — incompatible plugin names
- `isV2(): bool` — true if any v2 field is present

**`src/Core/PluginManager.php`** — Conflict detection added
- `bootstrap()` now checks `$manifest->getConflicts()` and throws if a conflicting plugin is active
- Auto-discovers CLI commands from `plugin/Commands/` via CommandBus
- `getByTag(string): array` — filter loaded plugins by tag

---

### `nemesis` CLI Binary

**Fixed:**
- `make:middleware` pointed to deleted `src/Middleware/` → now uses Scaffolder → `app/Http/Middleware/`

**Upgraded to Scaffolder:** `make:controller`, `make:model`, `make:job`, `make:policy`

**17 new `make:` commands (Section 11.4):**
| Command | Output |
|---|---|
| `make:event` | `app/Events/NameEvent.php` |
| `make:listener` | `app/Listeners/NameListener.php` |
| `make:trait` | `app/Traits/NameTrait.php` |
| `make:repository` | `app/Repositories/NameRepository.php` |
| `make:entity` | `app/Entities/Name.php` |
| `make:dto` | `app/DTOs/NameDTO.php` |
| `make:transformer` | `app/Transformers/NameTransformer.php` |
| `make:manager` | `app/Managers/NameManager.php` |
| `make:handler` | `app/Handlers/NameHandler.php` |
| `make:interface` | `app/Interfaces/NameInterface.php` |
| `make:factory` | `app/Factory/NameFactory.php` |
| `make:filter` | `app/Filters/NameFilter.php` |
| `make:widget` | `app/Widgets/Name/` (class + view) |
| `make:library` | `app/Libraries/Name.php` |
| `make:helper` | `app/Helpers/name_helper.php` |
| `make:plugin` | `plugins/Name/` (v2 manifest + bootstrap) |

**`schedule:run`** updated to use new `Scheduler` class.

**`default:` fallback** now routes through `CommandBus::dispatch()` first.

---

## Test Summary

| Area | Tests |
|---|---|
| Events (dispatch, propagation, forget, class listener) | 7 |
| Console Input (args, options, binding) | 4 |
| Console Command (run, getName, getDescription) | 3 |
| Scheduler (add, isDue, cron, run, frequencies) | 5 |
| CommandBus (register, run, failure, discovery) | 4 |
| Scaffolder (stubs exist, token replacement, temp gen) | 4 |
| Plugin Manifest v2 (provides/tags/conflicts, v1 compat) | 2 |
| File/folder existence | 4 |
| **Total Phase 7** | **33** |
| **Cumulative** | **263/263** |
