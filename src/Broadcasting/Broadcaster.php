<?php
declare(strict_types=1);

namespace Nemesis\Broadcasting;

interface Broadcaster {
    public function broadcast(array $channels, $event, array $payload = []);
}
