<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fa; color: #212529; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 8px; padding: 48px 56px; max-width: 480px; width: 90%; box-shadow: 0 2px 12px rgba(0,0,0,.08); text-align: center; }
        .code { font-size: 96px; font-weight: 700; color: #dee2e6; line-height: 1; }
        h1 { font-size: 22px; font-weight: 600; margin: 16px 0 8px; }
        p { color: #6c757d; font-size: 15px; line-height: 1.6; }
        a { display: inline-block; margin-top: 28px; padding: 10px 24px; background: #212529; color: #fff; border-radius: 5px; text-decoration: none; font-size: 14px; }
        a:hover { background: #343a40; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">404</div>
        <h1>Page Not Found</h1>
        <p><?= isset($message) ? htmlspecialchars($message, ENT_QUOTES) : 'The page you are looking for does not exist or has been moved.' ?></p>
        <a href="/">Go Home</a>
    </div>
</body>
</html>
