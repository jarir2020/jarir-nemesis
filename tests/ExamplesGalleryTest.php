<?php
declare(strict_types=1);

$catalog = require __DIR__ . '/../examples/catalog.php';

$categories = array_values(array_unique(array_map(static fn (array $entry): string => $entry['category'], $catalog)));
$requiredCategories = ['mvc', 'api', 'plugin', 'extension', 'module'];

foreach ($requiredCategories as $category) {
    if (!in_array($category, $categories, true)) {
        fwrite(STDERR, "Missing examples category: {$category}\n");
        exit(1);
    }
}

if (count($catalog) < 20) {
    fwrite(STDERR, "Expected at least 20 examples, found " . count($catalog) . "\n");
    exit(1);
}

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/nemesis') . ' examples:list';
$output = shell_exec($cmd);

if (!is_string($output) || !str_contains($output, 'Nemesis Examples Gallery')) {
    fwrite(STDERR, "CLI gallery output missing.\n");
    exit(1);
}

foreach ($requiredCategories as $category) {
    if (!str_contains($output, strtoupper($category) . ' EXAMPLES')) {
        fwrite(STDERR, "CLI gallery output missing category heading: {$category}\n");
        exit(1);
    }
}

echo "✅ Examples gallery checks passed (" . count($catalog) . " starter packs).\n";
