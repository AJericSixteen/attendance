<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$subjectId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, subject_code, subject_name, section, created_at FROM subjects WHERE id = ? AND teacher_id = ?');
$stmt->execute([$subjectId, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if (!$subject) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Subject not found.']);
    exit;
}

$records = $pdo->prepare('SELECT id, student_number, surname, scanned_at FROM attendance WHERE subject_id = ? AND DATE(scanned_at) = CURDATE() ORDER BY scanned_at ASC');
$records->execute([$subjectId]);

echo json_encode([
    'success' => true,
    'subject' => $subject,
    'records' => $records->fetchAll(),
]);
