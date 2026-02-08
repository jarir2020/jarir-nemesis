<?php
namespace Nemesis\Plugins\Swagger;

use OpenApi\Generator as OpenApiGenerator;

class Generator {
    public static function generate() {
        // Scan app directory for annotations
        // We might want to configure paths in bootstrap or config
        $paths = [
            base_path('app/Controllers'),
            base_path('app/Models'),
            // Add other paths if needed
        ];

        $openapi = OpenApiGenerator::scan($paths);
        return $openapi->toJson();
    }
}
