<?php

namespace Nemesis\Queue;

abstract class Job {
    protected $tries = 3;
    protected $delay = 0;

    abstract public function handle();

    public function getTries() {
        return $this->tries;
    }

    public function getDelay() {
        return $this->delay;
    }
}
