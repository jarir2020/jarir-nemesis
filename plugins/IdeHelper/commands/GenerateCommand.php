<?php
namespace Nemesis\Plugins\IdeHelper\Commands;

use Nemesis\Core\Console\Command;
use Nemesis\Plugins\IdeHelper\Generator;

class GenerateCommand {
    public $signature = 'ide-helper:generate';

    public function handle($args) {
        $generator = new Generator();
        echo $generator->generate() . "\n";
    }
}
