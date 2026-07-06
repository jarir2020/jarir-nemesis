<h1>Dashboard</h1>
<ul>
    <?php foreach ($metrics as $metric): ?>
        <li><?= e($metric['label']) ?>: <?= e($metric['value']) ?></li>
    <?php endforeach; ?>
</ul>

