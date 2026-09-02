<?php
require __DIR__ . '/../../includes/auth.php';
require_login('admin', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$teacherId     = (int) ($_POST['teacher_id'] ?? 0);
$adminPassword = $_POST['admin_password'] ?? '';

$stmt = $pdo->prepare('SELECT id, full_name, is_active FROM users WHERE id = ? AND role = "teacher"');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Teacher not found.'];
    header('Location: ../dashboard.php');
    exit;
}

if ($teacher['is_active']) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Deactivate the account before deleting it.'];
    header('Location: ../dashboard.php');
    exit;
}

$adminStmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$adminStmt->execute([$_SESSION['user_id']]);
$admin = $adminStmt->fetch();

if ($adminPassword === '' || !password_verify($adminPassword, $admin['password'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Incorrect admin password. Account was not deleted.'];
    $_SESSION['reopen_teacher_id'] = $teacherId;
    header('Location: ../dashboard.php');
    exit;
}

$delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
$delete->execute([$teacherId]);

$_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$teacher['full_name']}\" and all of their subjects and attendance records have been deleted."];
header('Location: ../dashboard.php');
exit;
