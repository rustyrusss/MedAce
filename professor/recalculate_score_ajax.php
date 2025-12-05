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
    // Validate professor owns quiz
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

    // -----------------------------------------------------
    // AUTO-GRADE MULTIPLE CHOICE QUESTIONS
    // -----------------------------------------------------
    $stmt = $conn->prepare("
        SELECT 
            sa.id,
            sa.answer_id,
            q.points,
            q.question_type,
            a.is_correct
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        LEFT JOIN answers a ON sa.answer_id = a.id
        WHERE sa.attempt_id = ? 
          AND q.question_type = 'multiple_choice'
    ");
    $stmt->execute([$attemptId]);
    $mcQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mcQuestions as $mc) {
        $points = 0;

        // Auto-grade only if correct
        if (!empty($mc['answer_id']) && $mc['is_correct'] == 1) {
            $points = $mc['points'];
        }

        $updateStmt = $conn->prepare("
            UPDATE student_answers 
            SET points_earned = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$points, $mc['id']]);
    }

    // -----------------------------------------------------
    // RECOUNT TOTAL POINTS AFTER GRADING
    // -----------------------------------------------------
    $stmt = $conn->prepare("
        SELECT 
            SUM(sa.points_earned) AS total_earned,
            SUM(q.points) AS total_possible
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.attempt_id = ?
    ");
    $stmt->execute([$attemptId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalEarned   = floatval($result['total_earned'] ?? 0);
    $totalPossible = floatval($result['total_possible'] ?? 1);

    $percentage = ($totalPossible > 0) ? ($totalEarned / $totalPossible) * 100 : 0;

    // -----------------------------------------------------
    // COMPUTE PASS / FAIL STATUS
    // -----------------------------------------------------
    $status = ($percentage >= 75) ? 'PASSED' : 'FAILED';

    // -----------------------------------------------------
    // UPDATE QUIZ ATTEMPT WITH FINAL SCORE & STATUS
    // -----------------------------------------------------
    $stmt = $conn->prepare("
        UPDATE quiz_attempts
        SET score = ?, total_questions = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$totalEarned, $totalPossible, $status, $attemptId]);

    echo json_encode([
        'success' => true,
        'score_raw' => $totalEarned,
        'total_possible' => $totalPossible,
        'percentage' => round($percentage, 2),
        'status' => $status
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
