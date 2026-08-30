<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Gap 12 — command registry abstraction
// Updated: 2026-08-30

namespace Nemesis\Console;

/**
 * In-memory registry of CLI commands.
 *
 * v7.1.1 (Gap 12): the bin/nemesis dispatcher now consults this registry
 * before falling back to the built-in `switch` statement. Plugins and apps
 * can register commands at boot time:
 *
 *     \Nemesis\Console\CommandRegistry::getInstance()->register(
 *         'demo:hello',
 *         'Print a greeting',
 *         function (array $args, \Nemesis\Console\Input $input, \Nemesis\Console\Output $output): int {
 *             $output->writeln('Hello!');
 *             return 0;
 *         },
 *         'demo:hello [name]'
 *     );
 *
 * Returning a non-zero exit code from a handler propagates to the shell.
 * Throwing an exception produces a CLI error and exit 1.
 */
class CommandRegistry
{
    private static ?self $instance = null;

    /** @var array<string, array{description: string, handler: callable, usage: string}> */
    private array $commands = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a command.
     */
    public function register(string $name, string $description, callable $handler, string $usage = ''): void
    {
        $this->commands[$name] = [
            'description' => $description,
            'handler'     => $handler,
            'usage'       => $usage !== '' ? $usage : $name,
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Run a registered command. Returns the exit code.
     */
    public function run(string $name, array $argv): int
    {
        if (!isset($this->commands[$name])) {
            throw new \RuntimeException("Unknown command: {$name}");
        }

        $entry  = $this->commands[$name];
        $input  = new \Nemesis\Console\Input($argv);
        $output = new \Nemesis\Console\Output();

        try {
            return (int) ($entry['handler']($argv, $input, $output) ?? 0);
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    /**
     * List all registered commands as a structured array
     * (name => description). Used by `nemesis list`.
     */
    public function list(): array
    {
        $out = [];
        foreach ($this->commands as $name => $entry) {
            $out[$name] = $entry['description'];
        }
        ksort($out);
        return $out;
    }

    /**
     * Show help for a single command.
     */
    public function help(string $name): ?array
    {
        return $this->commands[$name] ?? null;
    }
}
