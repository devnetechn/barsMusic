<?php
// Usage: php database/install.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 10,
]);
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

$pdo->exec('USE bars_music');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) echo "$t\n";
echo "Done.\n";
