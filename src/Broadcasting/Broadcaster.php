<?php

namespace Nemesis\Broadcasting;

interface Broadcaster {
    public function broadcast(array $channels, $event, array $payload = []);
}
