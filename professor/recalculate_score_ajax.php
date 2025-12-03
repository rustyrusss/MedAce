<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$attemptId = isset($input['attempt_id']) ? intval($input['attempt_id']) : 0;

if ($attemptId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid attempt ID']);
    exit();
}

try {
    // Verify attempt belongs to professor's quiz
    $stmt = $conn->prepare("
        SELECT qa.id, q.professor_id
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.id = ? AND q.professor_id = ?
    ");
    $stmt->execute([$attemptId, $professorId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt) {
        echo json_encode(['success' => false, 'error' => 'Attempt not found or access denied']);
        exit();
    }
    
    // ✅ FIXED: Auto-grade multiple choice questions using answer_id
    $stmt = $conn->prepare("
        SELECT 
            sa.id, 
            sa.answer_id,
            sa.answer_text, 
            sa.student_answer, 
            q.correct_answer, 
            q.points, 
            q.question_type,
            a.is_correct
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        LEFT JOIN answers a ON sa.answer_id = a.id
        WHERE sa.attempt_id = ? AND q.question_type = 'multiple_choice'
    ");
    $stmt->execute([$attemptId]);
    $mcQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grade each multiple choice question
    foreach ($mcQuestions as $mc) {
        $points = 0;
        
        // METHOD 1: Check if answer_id is set and if it's correct
        if (!empty($mc['answer_id']) && $mc['is_correct'] == 1) {
            $points = $mc['points'];
        }
        // METHOD 2: Fallback to text comparison if no answer_id
        else if (empty($mc['answer_id'])) {
            $studentAnswer = !empty($mc['answer_text']) ? trim($mc['answer_text']) : trim($mc['student_answer'] ?? '');
            $correctAnswer = trim($mc['correct_answer']);
            
            if (strcasecmp($studentAnswer, $correctAnswer) === 0) {
                $points = $mc['points'];
            }
        }
        
        // Update points_earned
        $updateStmt = $conn->prepare("UPDATE student_answers SET points_earned = ? WHERE id = ?");
        $updateStmt->execute([$points, $mc['id']]);
    }
    
    // Calculate total points earned and total possible points
    $stmt = $conn->prepare("
        SELECT 
            SUM(sa.points_earned) as total_earned,
            SUM(q.points) as total_possible
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.attempt_id = ?
    ");
    $stmt->execute([$attemptId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalEarned = floatval($result['total_earned'] ?? 0);
    $totalPossible = floatval($result['total_possible'] ?? 1);
    
    // Calculate percentage
    $score = ($totalPossible > 0) ? ($totalEarned / $totalPossible) * 100 : 0;
    
    // Update quiz attempt score
    $stmt = $conn->prepare("
        UPDATE quiz_attempts 
        SET score = ?, status = 'Completed'
        WHERE id = ?
    ");
    $stmt->execute([$score, $attemptId]);
    
    echo json_encode([
        'success' => true,
        'new_score' => round($score, 2),
        'total_earned' => $totalEarned,
        'total_possible' => $totalPossible
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}