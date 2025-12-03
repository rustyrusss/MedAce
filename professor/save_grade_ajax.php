<?php
session_start();
require_once '../config/db_conn.php';

header('Content-Type: application/json');

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

$professorId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$answerId = isset($input['answer_id']) ? intval($input['answer_id']) : 0;
$attemptId = isset($input['attempt_id']) ? intval($input['attempt_id']) : 0;
$points = isset($input['points']) ? floatval($input['points']) : 0;
$feedback = isset($input['feedback']) ? trim($input['feedback']) : null;

if ($answerId <= 0 || $attemptId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    // Verify the answer belongs to a quiz owned by this professor
    $stmt = $conn->prepare("
        SELECT sa.id, q.points as max_points, quiz.professor_id
        FROM student_answers sa
        JOIN quiz_attempts qa ON sa.attempt_id = qa.id
        JOIN questions q ON sa.question_id = q.id
        JOIN quizzes quiz ON qa.quiz_id = quiz.id
        WHERE sa.id = ? AND sa.attempt_id = ? AND quiz.professor_id = ?
    ");
    $stmt->execute([$answerId, $attemptId, $professorId]);
    $answer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$answer) {
        echo json_encode(['success' => false, 'error' => 'Answer not found or access denied']);
        exit();
    }
    
    // Validate points don't exceed max
    if ($points > $answer['max_points']) {
        $points = $answer['max_points'];
    }
    
    if ($points < 0) {
        $points = 0;
    }
    
    // Update the grade
    $stmt = $conn->prepare("
        UPDATE student_answers 
        SET points_earned = ?, feedback = ?, graded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$points, $feedback, $answerId]);
    
    echo json_encode([
        'success' => true,
        'points' => $points,
        'feedback' => $feedback
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}