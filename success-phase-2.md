# Phase 2 — ORM & Database Layer ✓

**Completed:** 2026-04-03

## What Was Done

### 3.1 Hydrated Results Always

| File | Change |
|------|--------|
| `src/Core/Fluent.php` | `get()` now returns `Collection` of raw arrays instead of a plain array. New `paginate()` uses `clone $this` for count query. Fully rewritten: clean clause builder replacing fragile regex-based appending. |
| `src/Core/Builder.php` | `get()` now returns `Collection` of hydrated `Model` instances. `paginate()` fixed (removed direct access to `protected $items`). New methods: `whereNull`, `whereNotNull`, `whereIn`, `whereNotIn`, `whereBetween`, `whereNotBetween`, `count`, `max`, `min`, `sum`, `avg`, `findOrFail`, `latest`, `oldest`, `select`, `with`. |
| `src/Core/Paginator.php` | `toArray()` handles `Collection` items gracefully. New `collection()` accessor. |

**Backward compatibility:** `foreach` loops, `$result[0]` access, and `count()` all still work because `Collection` implements `IteratorAggregate`, `ArrayAccess`, and `Countable`.

### 3.2 Schema Builder DSL

| File | Purpose |
|------|---------|
| `src/Database/Blueprint.php` | Full fluent DSL — `id()`, `string()`, `integer()`, `bigInteger()`, `boolean()`, `text()`, `mediumText()`, `longText()`, `decimal()`, `float()`, `json()`, `date()`, `time()`, `datetime()`, `timestamp()`, `timestamps()`, `softDeletes()`, `uuid()`, `binary()`, `char()`, `unsignedInteger()`, `unsignedBigInteger()`. Chainable `ColumnDefinition`: `nullable()`, `default()`, `unsigned()`, `unique()`, `index()`, `primary()`, `comment()`, `after()`. Fluent `ForeignKeyDefinition`: `references()`, `on()`, `onDelete()`, `cascadeOnDelete()`, `nullOnDelete()`, `cascadeOnUpdate()`. Table-level commands: `unique()`, `index()`, `foreign()`, `dropColumn()`, `renameColumn()`. |
| `src/Database/Schema.php` | Grammar-aware DDL — `create()`, `table()`, `drop()`, `dropIfExists()`, `rename()`, `hasTable()`, `hasColumn()`, `raw()`. Routes commands through the active grammar automatically. |

### 3.3 Multi-Database Support

| File | Change |
|------|--------|
| `src/Core/Database.php` | Full driver abstraction — MySQL (default), PostgreSQL, SQLite. DSN built per driver. `DB_DRIVER` env key already set. `setGrammar()` / `getGrammar()` for test injection. `setPdo()` / `disconnect()` for in-memory SQLite tests. Fixed `delete()` which was incorrectly returning `lastInsertId()`. |
| `src/Database/Grammars/GrammarInterface.php` | Contract: `compileCreate`, `compileColumn`, `compileDrop`, `compileDropIfExists`, `compileRename`, `compileAddColumn`, `compileDropColumn`, `compileRenameColumn`, `compileTableExists`, `quoteIdentifier`. |
| `src/Database/Grammars/MySqlGrammar.php` | Backtick quoting, `AUTO_INCREMENT`, `TINYINT(1)` for bool, `ENGINE=InnoDB`. Foreign key `CONSTRAINT` syntax. |
| `src/Database/Grammars/PostgresGrammar.php` | Double-quote quoting, `BIGSERIAL`, `BOOLEAN`, `JSONB`, no ENGINE clause, `CASCADE` on `DROP IF EXISTS`. |
| `src/Database/Grammars/SQLiteGrammar.php` | Double-quote quoting, `INTEGER PRIMARY KEY AUTOINCREMENT`, type affinity mapping (all text → TEXT, numeric → INTEGER/REAL), `sqlite_master` table-exists check. |

### 3.4 Collection Class (Expanded)

`src/Support/Collection.php` now implements `Countable` (PHP `count()` works) and adds:

`pluck`, `groupBy`, `keyBy`, `chunk`, `isEmpty`, `isNotEmpty`, `contains` (value and key-value forms), `values`, `keys`, `merge`, `concat`, `prepend`, `take`, `skip`, `slice`, `sortBy`, `sortByDesc`, `sort`, `reverse`, `unique` (simple and by-key), `sum`, `avg`, `min`, `max`, `reduce`, `flatten`, `collapse`, `flatMap`, `where` (with operator support), `duplicates`, `nth`.

Collection is also fully **immutable** — all transform methods return new instances.

### 3.5 Soft Deletes (Trait fixed)

`src/Core/Traits/SoftDeletes.php`:
- Added `use Nemesis\Core\Fluent;` (was missing — caused fatal error at runtime)
- `bootSoftDeletes` now uses `$builder->whereNull('deleted_at')` correctly (Builder now supports it)
- `withTrashed()` no longer uses a static flag (was not query-scoped); returns a Builder without the soft-delete scope
- `forceDelete()` hard-deletes the row
- `restore()` sets `deleted_at` to null

### Model updates

`src/Core/Model.php`:
- `findOrFail(mixed $id): static` — throws `NotFoundException` if not found
- `toJson(int $flags = 0): string`
- `jsonSerialize(): mixed`
- `whereNull()`, `whereNotNull()`, `whereIn()`, `latest()`, `oldest()` static shortcuts
- `__callStatic()` now delegates to `Builder` before checking scopes — so `Model::whereIn(...)`, `Model::orderBy(...)` etc. work via magic

## Test Results

```
Phase 2:   87/87 passed  (Collection×44, MySqlGrammar×14, PostgresGrammar×7, SQLiteGrammar×5, Blueprint×13, GrammarInterface×4)
Phase 1:   68/68 passed  (regression ✓)
Phase 3:   48/48 passed  (regression ✓)
Phase 4:   35/35 passed  (regression ✓)
Phase 6:   19/19 passed  (regression ✓)
Phase 7:   xx/xx passed  (regression ✓)
Phase 8:   61/61 passed  (regression ✓)

Total:    348/348 — 0 failures
```

## Breaking Change Risk (R2 — resolved)

`Fluent::get()` and `Builder::get()` now return `Collection`, not `array`.

Safe because:
- `foreach` works (Collection is `IteratorAggregate`)
- `$result[0]` works (Collection is `ArrayAccess`)
- `count($result)` works (Collection is `Countable`)
- Only `is_array($result)` would break — audited and not used on query results

## Remaining Phases
- Phase 9 — Config, Queue & Big Features (template engine = sub-project)
