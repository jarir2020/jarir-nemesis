<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — CommandBus | Created: 2026-04-03

namespace Nemesis\Reactor;

use Nemesis\Console\Command;
use Nemesis\Console\Input;
use Nemesis\Console\Output;

/**
 * CommandBus — registry and runner for all console Command classes.
 *
 * Registration (e.g. in app/Console/Kernel.php or a ServiceProvider):
 *   CommandBus::register(MigrateCommand::class);
 *   CommandBus::registerAll([MigrateCommand::class, SeedCommand::class]);
 *
 * Running (done automatically by the nemesis CLI binary):
 *   CommandBus::getInstance()->dispatch('migrate:run', $input, $output);
 *
 * Auto-discovery:
 *   CommandBus::getInstance()->discoverIn(base_path('app/Console/Commands'));
 */
class CommandBus
{
    /** @var array<string, class-string<Command>> signature → class map */
    private array $commands = [];

    private static ?self $instance = null;

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /** Reset singleton — useful in tests. */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /** @param class-string<Command> $commandClass */
    public static function register(string $commandClass): void
    {
        $bus = static::getInstance();
        $cmd = new $commandClass();
        $name = $cmd->getName();
        if ($name !== '') {
            $bus->commands[$name] = $commandClass;
        }
    }

    /** @param list<class-string<Command>> $classes */
    public static function registerAll(array $classes): void
    {
        foreach ($classes as $class) {
            static::register($class);
        }
    }

    /**
     * Scan a directory and auto-register all Command subclasses found.
     */
    public function discoverIn(string $directory): void
    {
        if (!is_dir($directory)) return;

        foreach (glob($directory . '/*.php') as $file) {
            require_once $file;

            // Extract FQCN
            $content = file_get_contents($file);
            $ns      = '';
            $class   = '';
            if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $m)) $ns = $m[1] . '\\';
            if (preg_match('/^class\s+(\w+)/m', $content, $m))               $class = $m[1];
            if ($class === '') continue;

            $fqcn = $ns . $class;
            if (!class_exists($fqcn)) continue;

            $ref = new \ReflectionClass($fqcn);
            if ($ref->isAbstract() || !$ref->isSubclassOf(Command::class)) continue;

            static::register($fqcn);
        }
    }

    // -------------------------------------------------------------------------
    // Lookup
    // -------------------------------------------------------------------------

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /** @return array<string, class-string<Command>> */
    public function all(): array
    {
        return $this->commands;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Resolve, instantiate, and run a command by name.
     * Returns the exit code (0 = success, 1 = failure).
     */
    public function run(string $name, Input $input, Output $output): int
    {
        if (!$this->has($name)) {
            $output->error("Command not found: {$name}");
            $this->listCommands($output);
            return Command::FAILURE;
        }

        $class   = $this->commands[$name];
        $command = new $class();
        return $command->run($input, $output);
    }

    /**
     * Convenience alias used by the nemesis binary.
     * Builds Input/Output from $argv and delegates to run().
     */
    public function dispatch(array $argv): int
    {
        $name   = $argv[1] ?? '';
        $input  = new Input($argv);
        $output = new Output();

        if ($name === '' || in_array($name, ['help', '--help', '-h'], true)) {
            $this->listCommands($output);
            return Command::SUCCESS;
        }

        return $this->run($name, $input, $output);
    }

    // -------------------------------------------------------------------------
    // Help
    // -------------------------------------------------------------------------

    private function listCommands(Output $output): void
    {
        $output->info('Available commands:');
        $rows = [];
        foreach ($this->commands as $name => $class) {
            $cmd    = new $class();
            $rows[] = [$name, $cmd->getDescription()];
        }
        if (empty($rows)) {
            $output->line('  (no commands registered)');
        } else {
            $output->table(['Command', 'Description'], $rows);
        }
    }
}
