<?php
namespace App\Console\Commands;

use Nemesis\Console\Command;

class HelloCommand extends Command {
    protected $signature = 'hello';
    protected $description = 'Greet the user';

    public function handle($arguments = []) {
        $name = $arguments[0] ?? 'World';
        echo "Hello, $name! This is a custom Nemesis command.\n";
    }
}
