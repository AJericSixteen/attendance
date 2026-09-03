<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require __DIR__ . '/../config/dify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($difyApiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'The chat assistant is not configured yet. Set DIFY_API_KEY in .env.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required.']);
    exit;
}

if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long.']);
    exit;
}

// Basic per-session throttle so a runaway page can't burn through API usage.
$windowStart = $_SESSION['dify_msg_window_start'] ?? time();
if (time() - $windowStart > 3600) {
    $windowStart = time();
    $_SESSION['dify_msg_count'] = 0;
}
$_SESSION['dify_msg_window_start'] = $windowStart;
$_SESSION['dify_msg_count'] = ($_SESSION['dify_msg_count'] ?? 0) + 1;

if ($_SESSION['dify_msg_count'] > 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many messages. Please try again in a bit.']);
    exit;
}

if (!empty($_SESSION['user_id'])) {
    $difyUser = 'user_' . $_SESSION['user_id'];
} else {
    if (empty($_SESSION['dify_visitor_id'])) {
        $_SESSION['dify_visitor_id'] = 'visitor_' . bin2hex(random_bytes(8));
    }
    $difyUser = $_SESSION['dify_visitor_id'];
}

$payload = [
    'inputs' => new stdClass(),
    'query' => $message,
    'response_mode' => 'blocking',
    'conversation_id' => $_SESSION['dify_conversation_id'] ?? '',
    'user' => $difyUser,
];

$ch = curl_init(rtrim($difyApiBaseUrl, '/') . '/chat-messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $difyApiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not reach the chat service: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['error' => $data['message'] ?? 'Chat service error.']);
    exit;
}

$_SESSION['dify_conversation_id'] = $data['conversation_id'] ?? ($_SESSION['dify_conversation_id'] ?? '');

echo json_encode([
    'answer' => $data['answer'] ?? '',
    'conversation_id' => $data['conversation_id'] ?? null,
]);
