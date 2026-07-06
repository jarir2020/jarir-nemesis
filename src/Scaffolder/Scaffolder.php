<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Scaffolder | Created: 2026-04-03

namespace Nemesis\Scaffolder;

/**
 * Scaffolder — generates files from stub templates.
 *
 * Usage:
 *   Scaffolder::make('controller', 'ProductController', ['namespace' => 'App\\Controllers']);
 *
 * Stubs live in src/Scaffolder/stubs/*.stub
 * Tokens: {{ClassName}}, {{Namespace}}, {{TableName}}, {{EventName}} etc.
 */
class Scaffolder
{
    private string $stubsDir;

    public function __construct(?string $stubsDir = null)
    {
        $this->stubsDir = $stubsDir ?? __DIR__ . '/stubs';
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a file from a stub.
     *
     * @param  string               $type     e.g. 'controller', 'model', 'event'
     * @param  string               $name     Class name / file name (PascalCase)
     * @param  array<string,string> $tokens   Extra token overrides
     * @return string               Absolute path of generated file
     * @throws \RuntimeException    If stub not found or destination already exists
     */
    public function generate(string $type, string $name, array $tokens = []): string
    {
        $stub = $this->loadStub($type);

        $defaults = $this->defaultTokens($type, $name);
        $tokens   = array_merge($defaults, $tokens);

        $content = $this->replaceTokens($stub, $tokens);

        $path = $this->resolvePath($type, $name, $tokens);
        $this->write($path, $content);

        return $path;
    }

    /**
     * Generate a widget (folder + class file + view file).
     * Returns an array of created paths.
     *
     * @return list<string>
     */
    public function generateWidget(string $name): array
    {
        $dir   = base_path("app/Widgets/{$name}");
        $paths = [];

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Class
        $stub    = $this->loadStub('widget');
        $content = $this->replaceTokens($stub, $this->defaultTokens('widget', $name));
        $classPath = $dir . "/{$name}Widget.php";
        $this->write($classPath, $content);
        $paths[] = $classPath;

        // View
        $viewPath = $dir . "/view.php";
        $this->write($viewPath, "<div class=\"widget-{$name}\">\n    <!-- {$name} widget view -->\n</div>\n");
        $paths[] = $viewPath;

        return $paths;
    }

    /**
     * Generate a plugin scaffold (manifest + bootstrap).
     * Returns the plugin directory path.
     */
    public function generatePlugin(string $name): string
    {
        $dir = base_path("plugins/{$name}");
        if (is_dir($dir)) {
            throw new \RuntimeException("Plugin [{$name}] already exists at {$dir}");
        }

        mkdir($dir . '/src', 0755, true);

        $manifest = [
            'name'        => $name,
            'version'     => '1.0.0',
            'description' => "{$name} plugin for Nemesis",
            'entry'       => 'bootstrap.php',
            'provides'    => [],
            'tags'        => [],
            'conflicts'   => [],
            'autoload'    => ['psr-4' => ["{$name}\\\\" => 'src/']],
            'permissions' => [],
            'requires'    => ['php' => '>=8.2'],
        ];

        file_put_contents(
            $dir . '/plugin.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        $bootstrap = "<?php\n// {$name} plugin bootstrap\n// Register services, routes, listeners here.\n";
        file_put_contents($dir . '/bootstrap.php', $bootstrap);

        return $dir;
    }

    /**
     * Generate a shared layout shell.
     *
     * When $framework is null, the layout is created under the plain Blade
     * views directory so both server-rendered pages and framework pages can
     * reuse the same shell.
     */
    public function generateLayout(?string $framework = null, string $name = 'app'): string
    {
        $framework = $framework !== null ? strtolower(trim($framework)) : null;
        if ($framework === 'server' || $framework === '') {
            $framework = null;
        }

        $this->ensureFrameworkRoots($framework);
        $baseDir   = $framework === null ? 'views' : "resources/views/{$framework}";
        $path      = base_path(rtrim($baseDir, '/\\') . '/layouts/' . trim($name, '/\\') . '.blade.php');
        $label     = $framework === null ? 'server' : $framework;

        $content = <<<BLADE
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \$title ?? 'Nemesis' }}</title>
    <style>
        body.layout-shell { font-family: Inter, ui-sans-serif, system-ui, sans-serif; margin: 0; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); color: #0f172a; }
        .app-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .app-nav { display: flex; gap: 1rem; align-items: center; padding: 1rem 1.5rem; background: rgba(15, 23, 42, 0.96); color: #fff; }
        .app-brand { font-weight: 700; letter-spacing: .04em; text-transform: uppercase; font-size: .8rem; opacity: .9; }
        .app-nav__links { display: flex; gap: .9rem; flex-wrap: wrap; }
        .app-nav a { color: inherit; text-decoration: none; opacity: .88; }
        .app-nav a:hover { opacity: 1; }
        .app-nav .spacer { flex: 1; }
        .app-nav__status { display: flex; align-items: center; gap: .75rem; font-size: .9rem; }
        .app-nav__status form { margin: 0; }
        .app-nav__status button { border: 0; border-radius: 999px; padding: .45rem .85rem; background: #38bdf8; color: #082f49; cursor: pointer; font-weight: 700; }
        main { flex: 1; padding: 2rem 1.5rem 3rem; }
        .shell-badge { font-size: .72rem; text-transform: uppercase; opacity: .75; margin-left: .35rem; }
    </style>
</head>
<body class="layout-shell layout-{$label}">
    <nav class="app-nav">
        <div class="app-brand">Nemesis <span class="shell-badge">{{ \$framework ?? '{$label}' }}</span></div>
        <div class="app-nav__links">
            <a href="{{ function_exists('route') ? route('dashboard.page') : '/dashboard' }}">Dashboard</a>
            <a href="{{ function_exists('route') ? route('profile.page') : '/profile' }}">Profile</a>
            <a href="{{ function_exists('route') ? route('settings.page') : '/settings' }}">Settings</a>
            @if(!empty(\$canSeeAdmin))
                <a href="{{ function_exists('route') ? route('admin.dashboard') : '/admin' }}">Admin</a>
            @endif
        </div>
        <span class="spacer"></span>
        <div class="app-nav__status">
            <span>{{ !empty(\$isAuthenticated) ? 'Signed in' : 'Guest' }}</span>
            @if(!empty(\$isAuthenticated))
                <form method="POST" action="{{ function_exists('route') ? route('logout') : '/logout' }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            @else
                <a href="{{ function_exists('route') ? route('login.page') : '/login' }}">Login</a>
            @endif
        </div>
    </nav>
    <main>
        @yield('content')
    </main>
</body>
</html>
BLADE;

        $this->write($path, $content . "\n");
        return $path;
    }

    /**
     * Generate a plain PHP or Blade-style view file.
     *
     * @param  string $name     Dot-path style name, e.g. "admin/dashboard"
     * @param  bool   $blade    When true, write .blade.php; otherwise .php
     * @param  string $baseDir  Root directory for views, relative to project root
     */
    public function generateView(string $name, bool $blade = false, string $baseDir = 'views'): string
    {
        $ext = $blade ? '.blade.php' : '.php';
        $path = base_path(rtrim($baseDir, '/\\') . '/' . trim($name, '/\\') . $ext);

        $this->write($path, "<?php /* {$name} view */ ?>\n<div>{$name}</div>\n");
        return $path;
    }

    /**
     * Generate a page view that uses the shared shell layout.
     */
    public function generatePageView(string $framework, string $path, string $title, string $body): string
    {
        $framework = strtolower(trim($framework));
        $this->ensureFrameworkRoots($framework);
        $baseDir   = $framework === 'server' ? 'views' : "resources/views/{$framework}";
        $viewPath  = base_path(rtrim($baseDir, '/\\') . '/' . trim($path, '/\\') . '.blade.php');

        $slug = trim(str_replace(['/', '\\'], '-', $path), '-');
        $content = <<<BLADE
@extends(\$layout ?? 'layouts.app')

@section('content')
<section class="page page-{$framework} page-{$slug}">
    <h1>{$title}</h1>
    <p>{$body}</p>
</section>
@endsection
BLADE;

        $this->write($viewPath, $content . "\n");
        return $viewPath;
    }

    public function generateAdminView(string $framework = 'server'): string
    {
        return $this->generatePageView($framework, 'admin/dashboard', 'Admin Dashboard', 'Manage admin users, roles, and settings.');
    }

    public function generateProfileView(string $framework = 'react'): string
    {
        return $this->generatePageView($framework, 'profile', 'Profile', 'Edit user profile data and preferences.');
    }

    public function generateSettingsView(string $framework = 'vue'): string
    {
        return $this->generatePageView($framework, 'settings', 'Settings', 'Update application and account settings.');
    }

    /**
     * Generate a framework-aware reusable component scaffold.
     *
     * Creates:
     * - resources/js/{framework}/components/{Name}.js
     * - resources/views/{framework}/components/{name}.blade.php
     */
    public function generateFrontendComponent(string $framework, string $name): array
    {
        return $this->generateNamedComponent($framework, $name);
    }

    /**
     * Generate a reusable admin component scaffold.
     *
     * @return array{0:string,1:string}
     */
    public function generateAdminComponent(string $framework = 'server'): array
    {
        return $this->generateNamedComponent($framework, 'Admin');
    }

    /**
     * Convenience wrapper for a profile component scaffold.
     *
     * @return array{0:string,1:string}
     */
    public function generateProfileComponent(string $framework = 'react'): array
    {
        return $this->generateNamedComponent($framework, 'Profile');
    }

    /**
     * Convenience wrapper for a settings component scaffold.
     *
     * @return array{0:string,1:string}
     */
    public function generateSettingsComponent(string $framework = 'vue'): array
    {
        return $this->generateNamedComponent($framework, 'Settings');
    }

    /**
     * Generate a beginner-friendly starter bundle for a framework.
     *
     * @return array<int, string>
     */
    public function generateFrontendStarter(string $framework, string $name = 'starter'): array
    {
        $framework = strtolower(trim($framework));
        $name = trim($name) === '' ? 'starter' : preg_replace('/[^A-Za-z0-9_-]/', '-', trim($name));
        $componentBase = preg_replace('/[^A-Za-z0-9]+/', ' ', $name);
        $componentName = str_replace(' ', '', ucwords(trim((string) $componentBase)));
        $componentName = $componentName === '' ? 'Starter' : $componentName;

        $paths = [];
        $paths[] = $this->generateLayout($framework, 'app');
        $paths[] = $this->generatePageView($framework, $name, ucfirst(str_replace(['-', '_'], ' ', $name)), "Starter scaffold for {$framework}.");
        $paths = array_merge($paths, $this->generateFrontendComponent($framework, $componentName . 'Card'));

        $starterDir = base_path("resources/js/views/{$framework}");
        if (!is_dir($starterDir)) {
            mkdir($starterDir, 0755, true);
        }

        $starterPath = $starterDir . '/' . $name . '.js';
        $starterContent = <<<JS
export default {
    framework: '{$framework}',
    name: '{$name}',
    layout: 'layouts.app',
    pages: ['{$name}'],
    note: 'Beginner starter scaffold generated by Nemesis',
};
JS;

        $this->write($starterPath, $starterContent . "\n");
        $paths[] = $starterPath;

        return $paths;
    }

    /**
     * Shared named-component generator used by the framework shortcuts.
     *
     * @return array{0:string,1:string}
     */
    private function generateNamedComponent(string $framework, string $name): array
    {
        $framework = strtolower(trim($framework));
        $className = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        $slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));

        $this->ensureFrameworkRoots($framework);
        $jsDir = base_path("resources/js/{$framework}/components");
        $jsViewDir = base_path("resources/js/views/{$framework}");
        $jsViewComponentDir = base_path("resources/js/views/{$framework}/components");
        $viewDir = base_path("resources/views/{$framework}/components");
        if (!is_dir($jsDir)) mkdir($jsDir, 0755, true);
        if (!is_dir($jsViewDir)) mkdir($jsViewDir, 0755, true);
        if (!is_dir($jsViewComponentDir)) mkdir($jsViewComponentDir, 0755, true);
        if (!is_dir($viewDir)) mkdir($viewDir, 0755, true);

        $jsPath = $jsDir . "/{$className}.js";
        $viewPath = $viewDir . "/{$slug}.blade.php";

        $jsContent = <<<JS
export function {$className}Component(props = {}) {
    return {
        tag: 'div',
        props: { class: '{$framework}-component {$slug}' },
        children: [props.label ?? '{$className} component']
    };
}
JS;

        $viewContent = <<<BLADE
<div class="component {$framework}-component {$slug}">
    <h3>{{ \$title ?? '{$className}' }}</h3>
    <p>{{ \$slot ?? 'Reusable component' }}</p>
</div>
BLADE;

        $this->write($jsPath, $jsContent . "\n");
        $this->write($viewPath, $viewContent . "\n");

        return [$jsPath, $viewPath];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function loadStub(string $type): string
    {
        $path = $this->stubsDir . "/{$type}.stub";
        if (!file_exists($path)) {
            throw new \RuntimeException("Stub not found: {$path}");
        }
        return file_get_contents($path);
    }

    /** @param array<string,string> $tokens */
    private function replaceTokens(string $stub, array $tokens): string
    {
        $search  = array_map(fn(string $k) => '{{' . $k . '}}', array_keys($tokens));
        $replace = array_values($tokens);
        return str_replace($search, $replace, $stub);
    }

    /**
     * @param  array<string,string> $tokens
     * @return array<string,string>
     */
    private function defaultTokens(string $type, string $name): array
    {
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';

        return match ($type) {
            'controller'  => ['ClassName' => $name, 'Namespace' => 'App\\Controllers'],
            'model'       => ['ClassName' => $name, 'Namespace' => 'App\\Models',       'TableName' => $table, 'ConnectionProperty' => '    protected ?string $connection = null;'],
            'middleware'  => ['ClassName' => $name, 'Namespace' => 'App\\Http\\Middleware'],
            'event'       => ['ClassName' => $name . 'Event',    'Namespace' => 'App\\Events'],
            'listener'    => ['ClassName' => $name . 'Listener', 'Namespace' => 'App\\Listeners',   'EventName' => $name . 'Event', 'EventNamespace' => 'App\\Events'],
            'job'         => ['ClassName' => $name, 'Namespace' => 'App\\Jobs'],
            'policy'      => ['ClassName' => $name . 'Policy',   'Namespace' => 'App\\Policies',    'ModelName' => $name],
            'migration'   => ['ClassName' => $name, 'TableName' => $table],
            'seeder'      => ['ClassName' => $name, 'Namespace' => ''],
            'trait'       => ['TraitName' => $name . 'Trait',    'Namespace' => 'App\\Traits'],
            'repository'  => ['ClassName' => $name . 'Repository', 'Namespace' => 'App\\Repositories', 'ModelName' => $name],
            'entity'      => ['ClassName' => $name, 'Namespace' => 'App\\Entities'],
            'dto'         => ['ClassName' => $name . 'DTO',      'Namespace' => 'App\\DTOs'],
            'transformer' => ['ClassName' => $name . 'Transformer', 'Namespace' => 'App\\Transformers', 'ModelName' => $name],
            'manager'     => ['ClassName' => $name . 'Manager',  'Namespace' => 'App\\Managers'],
            'handler'     => ['ClassName' => $name . 'Handler',  'Namespace' => 'App\\Handlers'],
            'interface'   => ['InterfaceName' => $name . 'Interface', 'Namespace' => 'App\\Interfaces'],
            'factory'     => ['ClassName' => $name . 'Factory',  'Namespace' => 'App\\Factory',      'ModelName' => $name],
            'filter'      => ['ClassName' => $name . 'Filter',   'Namespace' => 'App\\Filters'],
            'library'     => ['ClassName' => $name, 'Namespace' => 'App\\Libraries'],
            'widget'      => ['ClassName' => $name . 'Widget',   'Namespace' => 'App\\Widgets\\' . $name],
            'helper'      => ['FunctionPrefix' => strtolower($name)],
            default       => ['ClassName' => $name, 'Namespace' => 'App'],
        };
    }

    /**
     * @param array<string,string> $tokens
     */
    private function resolvePath(string $type, string $name, array $tokens): string
    {
        return match ($type) {
            'controller'  => base_path("app/Controllers/{$name}.php"),
            'model'       => base_path("app/Models/{$name}.php"),
            'middleware'  => base_path("app/Http/Middleware/{$name}.php"),
            'event'       => base_path("app/Events/{$name}Event.php"),
            'listener'    => base_path("app/Listeners/{$name}Listener.php"),
            'job'         => base_path("app/Jobs/{$name}.php"),
            'policy'      => base_path("app/Policies/{$name}Policy.php"),
            'migration'   => base_path('database/migrations/' . date('Y_m_d_His') . "_{$name}.php"),
            'seeder'      => base_path("database/seeders/{$name}.php"),
            'trait'       => base_path("app/Traits/{$name}Trait.php"),
            'repository'  => base_path("app/Repositories/{$name}Repository.php"),
            'entity'      => base_path("app/Entities/{$name}.php"),
            'dto'         => base_path("app/DTOs/{$name}DTO.php"),
            'transformer' => base_path("app/Transformers/{$name}Transformer.php"),
            'manager'     => base_path("app/Managers/{$name}Manager.php"),
            'handler'     => base_path("app/Handlers/{$name}Handler.php"),
            'interface'   => base_path("app/Interfaces/{$name}Interface.php"),
            'factory'     => base_path("app/Factory/{$name}Factory.php"),
            'filter'      => base_path("app/Filters/{$name}Filter.php"),
            'library'     => base_path("app/Libraries/{$name}.php"),
            'helper'      => base_path("app/Helpers/" . strtolower($name) . "_helper.php"),
            default       => base_path("app/{$name}.php"),
        };
    }

    private function write(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_exists($path)) {
            throw new \RuntimeException("File already exists: {$path}");
        }
        file_put_contents($path, $content);
    }

    private function ensureFrameworkRoots(?string $framework): void
    {
        if ($framework === null || $framework === '' || $framework === 'server') {
            if (!is_dir(base_path('views/layouts'))) {
                mkdir(base_path('views/layouts'), 0755, true);
            }
            return;
        }

        $framework = strtolower(trim($framework));
        foreach ([
            base_path("resources/js/{$framework}"),
            base_path("resources/js/views/{$framework}"),
            base_path("resources/views/{$framework}"),
        ] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
