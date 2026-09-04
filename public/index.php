<?php

// The project-root front controller is canonical. Keeping this tiny shim means
// both a root document root and public/ document root execute the same boot,
// middleware, route, and response pipeline.
require dirname(__DIR__) . '/index.php';
