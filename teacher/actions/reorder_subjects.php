<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? null;

if (!is_array($order) || empty($order)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing subject order.']);
    exit;
}

$ids = array_map('intval', $order);

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$check = $pdo->prepare("SELECT id FROM subjects WHERE id IN ($placeholders) AND teacher_id = ? AND is_deleted = 0");
$check->execute(array_merge($ids, [$_SESSION['user_id']]));
$owned = array_column($check->fetchAll(), 'id');

$update = $pdo->prepare('UPDATE subjects SET sort_order = ? WHERE id = ? AND teacher_id = ?');
$position = 1;
foreach ($ids as $id) {
    if (!in_array($id, $owned, true)) {
        continue;
    }
    $update->execute([$position, $id, $_SESSION['user_id']]);
    $position++;
}

echo json_encode(['success' => true]);
