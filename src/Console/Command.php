<?php
namespace Nemesis\Console;

abstract class Command {
    protected $signature;
    protected $description;

    abstract public function handle($arguments = []);

    public function getSignature() {
        return $this->signature;
    }

    public function getDescription() {
        return $this->description;
    }
}
