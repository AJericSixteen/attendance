<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$subjectId = (int) ($input['subject_id'] ?? 0);
$qrText    = trim($input['qr_text'] ?? '');

if ($subjectId <= 0 || $qrText === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing subject or QR data.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, is_active FROM subjects WHERE id = ? AND teacher_id = ? AND is_deleted = 0');
$stmt->execute([$subjectId, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if (!$subject) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have access to this subject.']);
    exit;
}

if (!$subject['is_active']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This subject is deactivated. Reactivate it to record new attendance.']);
    exit;
}

$parts = explode('_', $qrText, 2);
if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Unrecognized QR code format.']);
    exit;
}

$studentNumber = trim($parts[0]);
$surname       = strtoupper(trim($parts[1]));

// A student can only be marked present once per subject per day.
$dupCheck = $pdo->prepare('
    SELECT id, student_number, surname, scanned_at
    FROM attendance
    WHERE subject_id = ? AND student_number = ? AND DATE(scanned_at) = CURDATE()
    ORDER BY scanned_at DESC
    LIMIT 1
');
$dupCheck->execute([$subjectId, $studentNumber]);
$existing = $dupCheck->fetch();

if ($existing) {
    echo json_encode([
        'success'   => true,
        'duplicate' => true,
        'record'    => [
            'id'             => $existing['id'],
            'student_number' => $existing['student_number'],
            'surname'        => $existing['surname'],
            'scanned_at'     => $existing['scanned_at'],
        ],
    ]);
    exit;
}

$insert = $pdo->prepare('INSERT INTO attendance (subject_id, student_number, surname) VALUES (?, ?, ?)');
$insert->execute([$subjectId, $studentNumber, $surname]);

$record = $pdo->prepare('SELECT id, student_number, surname, scanned_at FROM attendance WHERE id = ?');
$record->execute([$pdo->lastInsertId()]);

echo json_encode([
    'success'   => true,
    'duplicate' => false,
    'record'    => $record->fetch(),
]);
