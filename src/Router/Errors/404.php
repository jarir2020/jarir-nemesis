<?php
declare(strict_types=1);
$routeDiagnostics = $routeDiagnostics ?? null;
$showDiagnostics = false;
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Page Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f2f2f2;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .container {
            padding: 40px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            font-size: 80px;
            margin: 0;
            color: #e74c3c;
        }
        p {
            font-size: 20px;
            color: #555;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .debug {
            margin-top: 24px;
            text-align: left;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            max-width: 760px;
            font-size: 14px;
            color: #374151;
        }
        .debug h2 {
            margin: 0 0 12px;
            font-size: 18px;
            color: #111827;
        }
        .debug code, .debug pre {
            font-family: Consolas, monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .debug ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>Oops! The page you are looking for doesn't exist.</p>
        <a href="/" class="btn">Go Back to Home</a>
HTML;

if (is_array($routeDiagnostics)) {
    $showDiagnostics = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
}

if (!empty($showDiagnostics) && is_array($routeDiagnostics)) {
    $requested = $routeDiagnostics['requested'] ?? [];
    $checked = $routeDiagnostics['checked_routes'] ?? [];
    echo '<div class="debug">';
    echo '<h2>Route Diagnostics</h2>';
    echo '<pre>' . htmlspecialchars(json_encode($requested, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES) . '</pre>';
    echo '<ul>';
    foreach ($checked as $candidate) {
        $status = !empty($candidate['matched']) ? 'matched' : 'missed';
        $reasons = !empty($candidate['reasons']) ? implode(', ', (array) $candidate['reasons']) : 'match';
        $label = trim(($candidate['method'] ?? 'GET') . ' ' . ($candidate['uri'] ?? '/') . ' - ' . $status . ' (' . $reasons . ')');
        echo '<li><code>' . htmlspecialchars($label, ENT_QUOTES) . '</code></li>';
    }
    echo '</ul>';
    echo '</div>';
}

echo <<<HTML
    </div>
</body>
</html>
HTML;
?>
