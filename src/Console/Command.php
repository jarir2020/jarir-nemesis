<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Console I/O | Updated: 2026-04-03
// Previous: bare abstract with untyped handle($arguments = [])
// Now: implements CommandInterface, injected Input/Output

namespace Nemesis\Console;

use Nemesis\Contracts\CommandInterface;

/**
 * Base class for all console commands.
 *
 * Usage:
 *   class MyCommand extends Command
 *   {
 *       protected string $signature    = 'my:command {name} {--force}';
 *       protected string $description  = 'Does something useful';
 *
 *       public function handle(): int
 *       {
 *           $name = $this->input->argument('name');
 *           $this->output->success("Hello {$name}!");
 *           return self::SUCCESS;
 *       }
 *   }
 */
abstract class Command implements CommandInterface
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    protected string $signature   = '';
    protected string $description = '';

    protected Input  $input;
    protected Output $output;

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    /**
     * Called by the runner. Injects I/O objects, then calls handle().
     */
    public function run(Input $input, Output $output): int
    {
        $this->input  = $input;
        $this->output = $output;

        // Map positional args to named args from signature
        if ($this->signature !== '') {
            $input->bindSignature($this->signature);
        }

        return $this->handle();
    }

    // -------------------------------------------------------------------------
    // CommandInterface
    // -------------------------------------------------------------------------

    /** @return int EXIT_SUCCESS (0) or EXIT_FAILURE (1) */
    abstract public function handle(): int;

    public function getName(): string
    {
        // Extract "command:name" from the front of the signature
        return explode(' ', trim($this->signature))[0];
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }
}
