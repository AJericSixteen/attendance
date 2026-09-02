<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$code    = trim($_POST['subject_code'] ?? '');
$name    = trim($_POST['subject_name'] ?? '');
$section = trim($_POST['section'] ?? '');

if ($code === '' || $name === '' || $section === '') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Subject Code, Subject Name, and Section are required.'];
    header('Location: ../dashboard.php');
    exit;
}

$stmt = $pdo->prepare('INSERT INTO subjects (teacher_id, subject_code, subject_name, section) VALUES (?, ?, ?, ?)');
$stmt->execute([$_SESSION['user_id'], $code, $name, $section]);

$_SESSION['flash'] = ['type' => 'success', 'message' => "Subject \"$name\" created."];
header('Location: ../dashboard.php');
exit;
