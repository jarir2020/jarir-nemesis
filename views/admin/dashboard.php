<?php
// Nemesis 5.0.0 | Phase 13 — Admin Panel | Created: 2026-04-03
$pageTitle = 'Dashboard';
ob_start();
?>
<div class="widgets">
<?php foreach (\Nemesis\Admin\DashboardWidget::all() as $widget): ?>
    <div class="widget" data-widget="<?= htmlspecialchars($widget->getName(), ENT_QUOTES) ?>">
        <?php if ($widget->getTitle()): ?>
        <h3 style="font-size:.875rem;font-weight:600;color:#718096;margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">
            <?php if ($widget->getIcon()): ?><i><?= htmlspecialchars($widget->getIcon(), ENT_QUOTES) ?></i> <?php endif ?>
            <?= htmlspecialchars($widget->getTitle(), ENT_QUOTES) ?>
        </h3>
        <?php endif ?>
        <?= $widget->render() ?>
    </div>
<?php endforeach ?>
<?php if (empty(\Nemesis\Admin\DashboardWidget::all())): ?>
    <div class="widget" style="grid-column:1/-1;text-align:center;color:#a0aec0">
        <p>No widgets registered. Use <code>AdminPanel::widget()</code> to add dashboard panels.</p>
    </div>
<?php endif ?>
</div>

<div class="card">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:16px">Registered Content Types</h2>
    <?php $types = \Nemesis\Admin\AdminPanel::registered(); ?>
    <?php if (empty($types)): ?>
        <p style="color:#718096;font-size:.875rem">No content types registered yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Slug</th><th>Label</th><th>Model</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($types as $slug => $cfg): ?>
            <tr>
                <td><code><?= htmlspecialchars($slug, ENT_QUOTES) ?></code></td>
                <td><?= htmlspecialchars($cfg['label'], ENT_QUOTES) ?></td>
                <td><small style="color:#718096"><?= htmlspecialchars($cfg['model'] ?? '—', ENT_QUOTES) ?></small></td>
                <td>
                    <a href="/admin/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="btn btn-primary" style="font-size:.75rem;padding:4px 10px">Manage</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
