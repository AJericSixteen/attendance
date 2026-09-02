<?php
session_start();
require __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter your username and password.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: index.php');
    exit;
}

if (!$user['is_active']) {
    $_SESSION['login_error'] = 'This account has been deactivated. Contact your administrator.';
    header('Location: index.php');
    exit;
}

$_SESSION['user_id']   = $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'teacher/dashboard.php'));
exit;
