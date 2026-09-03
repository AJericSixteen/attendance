<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$subjectId = (int) ($_POST['subject_id'] ?? 0);
$newStatus = $_POST['new_status'] ?? '';

$_SESSION['reopen_subject_id'] = $subjectId;

$stmt = $pdo->prepare('SELECT id, subject_code FROM subjects WHERE id = ? AND teacher_id = ? AND is_deleted = 0');
$stmt->execute([$subjectId, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if (!$subject || !in_array($newStatus, ['activate', 'deactivate'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request.'];
    header('Location: ../dashboard.php');
    exit;
}

$isActive = $newStatus === 'activate' ? 1 : 0;
$update = $pdo->prepare('UPDATE subjects SET is_active = ? WHERE id = ? AND teacher_id = ?');
$update->execute([$isActive, $subjectId, $_SESSION['user_id']]);

$verb = $newStatus === 'activate' ? 'activated' : 'deactivated';
$_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$subject['subject_code']}\" has been $verb."];
header('Location: ../dashboard.php');
exit;
