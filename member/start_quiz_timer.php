<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$quizId = isset($data['quiz_id']) ? intval($data['quiz_id']) : 0;
$timeLimit = isset($data['time_limit']) ? intval($data['time_limit']) : 0;

if (!$quizId || !$timeLimit) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$sessionKey = 'quiz_start_'.$quizId;
$endSessionKey = 'quiz_end_'.$quizId;

// Only set timer if it hasn't been set yet
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = time();
    $_SESSION[$endSessionKey] = time() + $timeLimit;
    
    echo json_encode([
        'success' => true,
        'started_at' => $_SESSION[$sessionKey],
        'ends_at' => $_SESSION[$endSessionKey],
        'remaining' => $timeLimit
    ]);
} else {
    // Timer already running, return current remaining time
    $remaining = max(0, $_SESSION[$endSessionKey] - time());
    echo json_encode([
        'success' => true,
        'already_started' => true,
        'started_at' => $_SESSION[$sessionKey],
        'ends_at' => $_SESSION[$endSessionKey],
        'remaining' => $remaining
    ]);
}
?>