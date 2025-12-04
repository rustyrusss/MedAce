<?php
// actions/save_flashcard_attempt.php
session_start();
header('Content-Type: application/json');

// Require DB connection (adjust path if different)
require_once __DIR__ . '/../config/db_conn.php';

// Authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_SESSION['user_id'];

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !is_array($input)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

// Map expected fields
$quizId = isset($input['quiz_id']) && $input['quiz_id'] > 0 ? intval($input['quiz_id']) : null;
$moduleId = isset($input['module_id']) && $input['module_id'] > 0 ? intval($input['module_id']) : null;
$sourceTitle = isset($input['source_title']) ? trim($input['source_title']) : null;
$sourceType = isset($input['source_type']) ? trim($input['source_type']) : null;
$totalQuestions = isset($input['total_questions']) ? intval($input['total_questions']) : 0;

// Prefer the keys matching your DB column names: correct_answers / incorrect_answers
$correctAnswers = isset($input['correct_answers']) ? intval($input['correct_answers']) : (isset($input['correct_count']) ? intval($input['correct_count']) : 0);
$incorrectAnswers = isset($input['incorrect_answers']) ? intval($input['incorrect_answers']) : (isset($input['incorrect_count']) ? intval($input['incorrect_count']) : 0);

$scorePercentage = isset($input['score_percentage']) ? floatval($input['score_percentage']) : (isset($input['score']) ? floatval($input['score']) : null);
$mode = isset($input['mode']) ? $input['mode'] : 'multiple_choice';
$timeSpent = isset($input['time_spent_seconds']) ? intval($input['time_spent_seconds']) : null;

// Basic validation
if ($totalQuestions <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid total_questions']);
    exit();
}

if ($correctAnswers < 0 || $incorrectAnswers < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid answer counts']);
    exit();
}

// Mode validation
$validModes = ['multiple_choice', 'definition'];
if (!in_array($mode, $validModes)) {
    $mode = 'multiple_choice';
}

// Source type validation
$validTypes = ['quiz', 'module'];
if (!in_array($sourceType, $validTypes)) {
    $sourceType = $sourceType ? $sourceType : 'quiz';
}

// Insert into DB - using columns from your table
try {
    $stmt = $conn->prepare("
        INSERT INTO flashcard_attempts
        (student_id, quiz_id, module_id, source_title, source_type, total_questions, correct_answers, incorrect_answers, score_percentage, mode, time_spent_seconds, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $studentId,
        $quizId,
        $moduleId,
        $sourceTitle,
        $sourceType,
        $totalQuestions,
        $correctAnswers,
        $incorrectAnswers,
        $scorePercentage,
        $mode,
        $timeSpent
    ]);
    
    $attemptId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'attempt_id' => $attemptId,
        'message' => 'Flashcard attempt saved successfully'
    ]);
    exit();
    
} catch (PDOException $e) {
    // Log error on server and return generic message to client
    error_log('Flashcard save error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to save attempt.']);
    exit();
}
