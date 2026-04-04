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
            'model'       => ['ClassName' => $name, 'Namespace' => 'App\\Models',       'TableName' => $table],
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
}
