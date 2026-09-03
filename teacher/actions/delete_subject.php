<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$subjectId = (int) ($_POST['subject_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, subject_code, is_active FROM subjects WHERE id = ? AND teacher_id = ? AND is_deleted = 0');
$stmt->execute([$subjectId, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if (!$subject) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Subject not found.'];
    header('Location: ../dashboard.php');
    exit;
}

if ($subject['is_active']) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Deactivate the subject before deleting it.'];
    $_SESSION['reopen_subject_id'] = $subjectId;
    header('Location: ../dashboard.php');
    exit;
}

// Soft delete only: the subject row and its attendance records are kept intact,
// just hidden from the dashboard, so attendance history is never lost.
$update = $pdo->prepare('UPDATE subjects SET is_deleted = 1 WHERE id = ? AND teacher_id = ?');
$update->execute([$subjectId, $_SESSION['user_id']]);

$_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$subject['subject_code']}\" has been deleted. Its attendance records have been preserved."];
header('Location: ../dashboard.php');
exit;
