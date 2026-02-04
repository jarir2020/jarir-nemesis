<?php
require_once __DIR__ . '/index.php';
$stmt = Nemesis\Core\Database::connect()->query('SELECT COUNT(*) FROM users');
echo 'User count: ' . $stmt->fetchColumn() . PHP_EOL;
