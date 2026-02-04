<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nemesis View Test</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
        h1 { color: #0078D7; margin-bottom: 0.5rem; }
        p { color: #555; font-size: 1.1rem; }
        .badge { background: #e7f3ff; color: #0078D7; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: bold; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Hello, <?php echo htmlspecialchars($name); ?>!</h1>
        <p>The Nemesis View Engine is working perfectly.</p>
        <div class="badge">Version 1.0</div>
    </div>
</body>
</html>
