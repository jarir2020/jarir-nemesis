<?php

namespace App\Http\Middleware;

use Nemesis\Http\Session;

class StartSession {
    public function handle($request, $next) {
        new Session(); // Ensures session is started
        return $next($request);
    }
}
