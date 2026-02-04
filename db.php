<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$db = $config['db'];

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = null;
$error = null;

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
} catch (PDOException $exception) {
    $error = $exception->getMessage();
}

return [
    'pdo' => $pdo,
    'error' => $error,
];
