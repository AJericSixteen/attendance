<?php

require __DIR__ . '/../includes/env.php';
load_env(__DIR__ . '/../.env');

$host    = env('DB_HOST', 'localhost');
$db      = env('DB_NAME', 'attendance_system');
$user    = env('DB_USER', 'root');
$pass    = env('DB_PASS', '');
$charset = env('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database connection failed. Have you imported database.sql and set up .env? (' . $e->getMessage() . ')');
}
