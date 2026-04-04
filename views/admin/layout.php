<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin', ENT_QUOTES) ?> — Nemesis Admin</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f6f9;color:#333}
        .admin-wrap{display:flex;min-height:100vh}
        .sidebar{width:240px;background:#1e2a3b;color:#c8d3e0;flex-shrink:0;padding:0}
        .sidebar-brand{padding:20px 24px;font-size:1.2rem;font-weight:700;color:#fff;border-bottom:1px solid #2d3d52}
        .sidebar-brand span{color:#4e9af1}
        .sidebar nav a{display:flex;align-items:center;gap:10px;padding:12px 24px;color:#c8d3e0;text-decoration:none;transition:background .15s}
        .sidebar nav a:hover,.sidebar nav a.active{background:#2d3d52;color:#fff}
        .sidebar nav a i{width:16px;text-align:center;opacity:.7}
        .main{flex:1;display:flex;flex-direction:column}
        .topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between}
        .topbar h1{font-size:1.1rem;font-weight:600;color:#1a202c}
        .topbar .user{font-size:.875rem;color:#718096}
        .content{padding:24px;flex:1}
        .card{background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:24px;margin-bottom:20px}
        .widgets{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:24px}
        .widget{background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:20px}
        .widget-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:6px;border:none;cursor:pointer;font-size:.875rem;text-decoration:none}
        .btn-primary{background:#4e9af1;color:#fff}.btn-primary:hover{background:#3182ce}
        .btn-danger{background:#e53e3e;color:#fff}.btn-danger:hover{background:#c53030}
        table{width:100%;border-collapse:collapse}
        th{text-align:left;padding:10px 12px;font-size:.75rem;font-weight:600;text-transform:uppercase;color:#718096;border-bottom:2px solid #e2e8f0}
        td{padding:10px 12px;border-bottom:1px solid #f0f4f8;font-size:.875rem}
        tr:hover td{background:#f7fafc}
        .badge{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:.75rem;font-weight:600}
        .badge-green{background:#c6f6d5;color:#276749}
        .badge-gray{background:#e2e8f0;color:#4a5568}
        .pagination{display:flex;gap:4px;justify-content:center;margin-top:16px}
        .pagination a{padding:6px 12px;border-radius:4px;border:1px solid #e2e8f0;text-decoration:none;font-size:.875rem;color:#4a5568}
        .pagination a.active,.pagination a:hover{background:#4e9af1;color:#fff;border-color:#4e9af1}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:6px;font-weight:500;font-size:.875rem;color:#4a5568}
        .form-group input,.form-group textarea,.form-group select{width:100%;padding:8px 12px;border:1px solid #cbd5e0;border-radius:6px;font-size:.875rem}
        .form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:#4e9af1;box-shadow:0 0 0 3px rgba(78,154,241,.15)}
        .alert{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:.875rem}
        .alert-success{background:#c6f6d5;color:#276749;border:1px solid #9ae6b4}
        .alert-error{background:#fed7d7;color:#c53030;border:1px solid #feb2b2}
    </style>
</head>
<body>
<div class="admin-wrap">
    <aside class="sidebar">
        <div class="sidebar-brand">Nemesis <span>Admin</span></div>
        <nav>
            <a href="/admin" <?= (($_SERVER['REQUEST_URI'] ?? '') === '/admin') ? 'class="active"' : '' ?>>
                <i>⊞</i> Dashboard
            </a>
            <?php foreach (\Nemesis\Admin\AdminPanel::navItems() as $item): ?>
            <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>"
               <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', $item['url']) ? 'class="active"' : '' ?>>
                <i><?= htmlspecialchars($item['icon'] ?? '▶', ENT_QUOTES) ?></i>
                <?= htmlspecialchars($item['label'], ENT_QUOTES) ?>
            </a>
            <?php endforeach ?>
            <a href="/admin/media"><i>🖼</i> Media Library</a>
        </nav>
    </aside>

    <div class="main">
        <div class="topbar">
            <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES) ?></h1>
            <div class="user"><?= htmlspecialchars($currentUser ?? 'Admin', ENT_QUOTES) ?></div>
        </div>
        <div class="content">
            <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES) ?></div>
            <?php endif ?>
            <?php if (!empty($flashError)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES) ?></div>
            <?php endif ?>
            <?= $content ?? '' ?>
        </div>
    </div>
</div>
</body>
</html>
