<?php

namespace Nemesis\Queue;

interface QueueDriver {
    public function push(Job $job);
    public function pop();
    public function delete($id);
    public function release($id, $delay = 0);
}
