<?php
require __DIR__ . '/../../includes/auth.php';
require_login('admin', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$teacherId       = (int) ($_POST['teacher_id'] ?? 0);
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$_SESSION['reopen_teacher_id'] = $teacherId;

$stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE id = ? AND role = "teacher"');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Teacher not found.'];
    header('Location: ../dashboard.php');
    exit;
}

if (strlen($newPassword) < 6 || $newPassword !== $confirmPassword) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Passwords must match and be at least 6 characters.'];
    header('Location: ../dashboard.php');
    exit;
}

$hashed = password_hash($newPassword, PASSWORD_BCRYPT);
$update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$update->execute([$hashed, $teacherId]);

$_SESSION['flash'] = ['type' => 'success', 'message' => "Password updated for \"{$teacher['full_name']}\"."];
header('Location: ../dashboard.php');
exit;
