<?php

use Nemesis\Core\Plugin;
use Nemesis\Plugins\IdeHelper\Commands\GenerateCommand;

Plugin::register('IdeHelper', function ($plugin) {
    if (defined('NEMESIS_CONSOLE')) {
        // Register Command
        // We need a way to register commands in the kernel from plugins
        // Plugin::command method exists but Kernel needs to load it.
        
        $plugin->command(GenerateCommand::class);
        
        // We also need to map the command name 'ide-helper:generate' to this class
        // Nemesis Console usually maps commands in Kernel.php
        // If Plugin::command adds to a list, we need to ensure Kernel uses it.
    }
});
