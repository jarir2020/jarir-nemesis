<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Console I/O | Created: 2026-04-03

namespace Nemesis\Console;

/**
 * Typed CLI input — wraps $argv into named arguments and options.
 *
 * Signature format (same as existing Command::$signature):
 *   "command:name {arg1} {arg2?} {--flag} {--option=}"
 *
 * Usage inside a Command::handle():
 *   $this->input->argument('name')   // positional arg
 *   $this->input->option('force')    // --force flag (bool)
 *   $this->input->option('file')     // --file=value
 *   $this->input->hasOption('force') // bool
 */
class Input
{
    /** @var array<string, string|bool|null> */
    private array $arguments = [];

    /** @var array<string, string|bool> */
    private array $options = [];

    /** @var list<string> raw tokens after the command name */
    private array $tokens;

    public function __construct(array $argv = [])
    {
        // argv[0] = script, argv[1] = command — skip both
        $this->tokens = array_slice($argv, 2);
        $this->parse();
    }

    // -------------------------------------------------------------------------
    // Public accessors
    // -------------------------------------------------------------------------

    public function argument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }

    /** @return array<string, string|bool|null> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]) && $this->options[$name] !== false;
    }

    /** @return array<string, string|bool> */
    public function options(): array
    {
        return $this->options;
    }

    // -------------------------------------------------------------------------
    // Bind argument names from a signature string
    // e.g. "make:controller {name} {--force} {--connection=}"
    // -------------------------------------------------------------------------

    public function bindSignature(string $signature): void
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $argNames    = [];

        foreach ($matches[1] as $token) {
            $token = trim($token);
            if (str_starts_with($token, '--')) {
                // Option — already parsed
            } else {
                $argNames[] = rtrim($token, '?'); // strip optional marker
            }
        }

        // Re-map positional values to names
        $positional = array_values($this->arguments);
        $this->arguments = [];
        foreach ($argNames as $i => $name) {
            $this->arguments[$name] = $positional[$i] ?? null;
        }
    }

    // -------------------------------------------------------------------------
    // Parser
    // -------------------------------------------------------------------------

    private function parse(): void
    {
        $positional = [];

        foreach ($this->tokens as $token) {
            if (str_starts_with($token, '--')) {
                $token = substr($token, 2);
                if (str_contains($token, '=')) {
                    [$key, $val] = explode('=', $token, 2);
                    $this->options[$key] = $val;
                } else {
                    $this->options[$token] = true;
                }
            } else {
                $positional[] = $token;
            }
        }

        // Store positional by numeric index until bindSignature() names them
        foreach ($positional as $i => $val) {
            $this->arguments[(string) $i] = $val;
        }
    }
}
