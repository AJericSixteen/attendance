<?php
require __DIR__ . '/../../includes/auth.php';
require_login('admin', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($fullName === '' || $username === '' || $email === '' || strlen($password) < 6) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please fill in all fields. Password must be at least 6 characters.'];
    $_SESSION['reopen_modal'] = true;
    header('Location: ../dashboard.php');
    exit;
}

$check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$check->execute([$username, $email]);

if ($check->fetch()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A user with that username or email already exists.'];
    $_SESSION['reopen_modal'] = true;
    header('Location: ../dashboard.php');
    exit;
}

$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, "teacher")');
$stmt->execute([$fullName, $username, $email, $hashed]);

$_SESSION['flash'] = ['type' => 'success', 'message' => "Teacher account for \"$fullName\" created successfully."];
header('Location: ../dashboard.php');
exit;
