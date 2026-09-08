<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$attendanceId = (int) ($input['attendance_id'] ?? 0);

if ($attendanceId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing attendance record.']);
    exit;
}

$stmt = $pdo->prepare('
    SELECT a.id
    FROM attendance a
    INNER JOIN subjects s ON s.id = a.subject_id
    WHERE a.id = ? AND s.teacher_id = ? AND s.is_deleted = 0
');
$stmt->execute([$attendanceId, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attendance record not found.']);
    exit;
}

$delete = $pdo->prepare('DELETE FROM attendance WHERE id = ?');
$delete->execute([$attendanceId]);

echo json_encode(['success' => true]);
