<?php
require __DIR__ . '/../../includes/auth.php';
require_login('admin', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$teacherId   = (int) ($_POST['teacher_id'] ?? 0);
$newStatus   = $_POST['new_status'] ?? '';
$adminPassword = $_POST['admin_password'] ?? '';

$_SESSION['reopen_teacher_id'] = $teacherId;

$stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE id = ? AND role = "teacher"');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher || !in_array($newStatus, ['activate', 'deactivate'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request.'];
    header('Location: ../dashboard.php');
    exit;
}

if ($newStatus === 'deactivate') {
    $adminStmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $adminStmt->execute([$_SESSION['user_id']]);
    $admin = $adminStmt->fetch();

    if ($adminPassword === '' || !password_verify($adminPassword, $admin['password'])) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Incorrect admin password. Account was not deactivated.'];
        header('Location: ../dashboard.php');
        exit;
    }
}

$isActive = $newStatus === 'activate' ? 1 : 0;
$update = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
$update->execute([$isActive, $teacherId]);

$verb = $newStatus === 'activate' ? 'activated' : 'deactivated';
$_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$teacher['full_name']}\" has been $verb."];
header('Location: ../dashboard.php');
exit;
