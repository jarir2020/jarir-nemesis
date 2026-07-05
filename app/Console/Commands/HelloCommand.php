<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Nemesis\Console\Command;

class HelloCommand extends Command
{
    protected string $signature = 'hello {name?}';
    protected string $description = 'Greet the user';

    public function handle(): int
    {
        $name = $this->input->argument('name') ?? 'World';
        $this->output->line("Hello, {$name}! This is a custom Nemesis command.");
        return self::SUCCESS;
    }
}
