<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$subjectId   = (int) ($_POST['subject_id'] ?? 0);
$subjectCode = trim($_POST['subject_code'] ?? '');
$subjectName = trim($_POST['subject_name'] ?? '');
$section     = trim($_POST['section'] ?? '');

$_SESSION['reopen_subject_id'] = $subjectId;

if ($subjectCode === '' || $subjectName === '' || $section === '') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please fill in all fields.'];
    header('Location: ../dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND teacher_id = ? AND is_deleted = 0');
$stmt->execute([$subjectId, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Subject not found.'];
    header('Location: ../dashboard.php');
    exit;
}

$update = $pdo->prepare('UPDATE subjects SET subject_code = ?, subject_name = ?, section = ? WHERE id = ? AND teacher_id = ?');
$update->execute([$subjectCode, $subjectName, $section, $subjectId, $_SESSION['user_id']]);

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Subject updated.'];
header('Location: ../dashboard.php');
exit;
