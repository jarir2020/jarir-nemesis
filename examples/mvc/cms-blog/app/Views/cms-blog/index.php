<h1>CMS Blog</h1>
<ul>
    <?php foreach ($posts as $post): ?>
        <li><?= e($post) ?></li>
    <?php endforeach; ?>
</ul>

