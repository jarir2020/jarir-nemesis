<h1>Blog</h1>
<?php foreach ($posts as $post): ?>
    <article>
        <h2><?= e($post->title) ?></h2>
        <p><?= e($post->body) ?></p>
    </article>
<?php endforeach; ?>

