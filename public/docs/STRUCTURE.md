# Directory Structure

Nemesis Framework follows a clean and logical directory structure designed for scalability and ease of use.

Vendor compression stores archives and manifests under `.nemesis/vendor-compress/` so cleanup data stays separate from the application tree.

## Core Directories

### `app/`
The heart of your application.
- `Controllers/`: HTTP Controllers that handle incoming requests.
- `Models/`: Eloquent-style ORM models for data interaction.
- `Middleware/`: Request filters and security layers.
- `Policies/`: Authorization logic for resources.
- `Resources/`: standard API response transformers.

### `config/`
All framework and application configuration files. Nemesis is fully configurable via `.env` and PHP arrays.

### `database/`
Database-related files.
- `migrations/`: Version-controlled schema changes.
- `seeds/`: Initial data for development and production.
- `factories/`: Database factory classes for testing data.

### `docs/`
Framework documentation in Markdown format.

### `examples/`
Optional copy-only starter packs for learners and fast coders.
- `mvc/`: MVC page/controller/model/view samples
- `api/`: API controller/resource samples
- `plugins/`: Framework plugin samples
- `extensions/`: Extension and integration samples
- `modules/`: Self-contained application module samples

### `plugins/`
The enterprise sidecar architecture. Each plugin is self-contained with its own manifest, bootstrap, and source code.
- `Audit/`
- `CloudStorage/`
- `DebugBar/`
- `IdeHelper/`
- `Swagger/`

### `public/`
The web entry point. Only these files should be accessible by the browser.
- `index.php`: The front controller.
- `docs/`: The premium documentation renderer.
- `assets/`: CSS, JS, and image files.

### `routes/`
Where all your application routes are defined.
- `route.php`: The main routing file.

### `src/`
The Nemesis Framework core code (Internal).

### `storage/`
Compiled views, logs, caches, and file uploads. Must be writable by the web server.

### `tests/`
The native unit and integration testing suite.

---

## Key Files

- `.env`: Environment-specific configuration.
- `nemesis`: The powerful Artisan-inspired CLI tool.
- `composer.json`: Application dependencies and metadata.
- `_ide_helper.php`: Generated IDE autocomplete helpers.

---

## 🏗️ Architecture Design

Nemesis uses a **Modular sidecar architecture**:
1. **The Core**: Lightweight, zero-dependency engine.
2. **Modules**: Self-contained application logic (Blog, Auth, etc.).
3. **Plugins**: Framework extensions that hook into the core lifecycle.
