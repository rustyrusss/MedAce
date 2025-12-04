<?php
/**
 * Chatbot Endpoint - Handles all chatbot requests
 * FIXED: Chat functionality and progress tracking now working
 */

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function sendJson($data, $code = 200) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    die(json_encode($data));
}

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendJson(['error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// Get input
$input = json_decode(file_get_contents("php://input"), true);

$studentId = $_SESSION['user_id'];
$action = $input["action"] ?? "chat";

try {
    // Load dependencies
    require_once __DIR__ . '/db_conn.php';

    // Handle different actions
    switch ($action) {
        case "get_modules":
            // Return list of available modules (matching resources.php structure)
            $stmt = $conn->prepare("
                SELECT 
                    m.id, 
                    m.title, 
                    m.description,
                    COALESCE(sp.status, 'Pending') AS status
                FROM modules m
                LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
                WHERE m.status IN ('active', 'published')
                ORDER BY m.display_order ASC, m.created_at DESC
            ");
            $stmt->execute([$studentId]);
            $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJson([
                'success' => true,
                'modules' => $modules
            ]);
            break;

        case "get_flashcard_questions":
            // Get questions from quizzes for flashcard mode
            $quizId = isset($input["quiz_id"]) ? intval($input["quiz_id"]) : 0;
            $moduleId = isset($input["module_id"]) ? intval($input["module_id"]) : 0;
            $limit = isset($input["limit"]) ? intval($input["limit"]) : 20;
            
            if (!$quizId && !$moduleId) {
                sendJson(['error' => 'Missing quiz_id or module_id'], 400);
            }
            
            $limit = max(1, min(50, intval($limit)));
            
            if ($quizId > 0) {
                $stmt = $conn->prepare("SELECT id, title, description, module_id FROM quizzes WHERE id = ?");
                $stmt->execute([$quizId]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$quiz) {
                    sendJson(['error' => 'Quiz not found'], 404);
                }
                
                $stmt = $conn->prepare("
                    SELECT q.id, q.question_text, q.question_type, q.points
                    FROM questions q
                    WHERE q.quiz_id = ?
                    AND q.question_type IN ('multiple_choice', 'true_false')
                    ORDER BY RAND()
                    LIMIT {$limit}
                ");
                $stmt->execute([$quizId]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $sourceInfo = [
                    'type' => 'quiz',
                    'id' => $quiz['id'],
                    'title' => $quiz['title'],
                    'description' => $quiz['description']
                ];
            } else {
                $stmt = $conn->prepare("SELECT id, title, description FROM modules WHERE id = ?");
                $stmt->execute([$moduleId]);
                $module = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$module) {
                    sendJson(['error' => 'Module not found'], 404);
                }
                
                $stmt = $conn->prepare("
                    SELECT q.id, q.question_text, q.question_type, q.points, qz.title AS quiz_title
                    FROM questions q
                    JOIN quizzes qz ON q.quiz_id = qz.id
                    WHERE qz.module_id = ?
                    AND q.question_type IN ('multiple_choice', 'true_false')
                    ORDER BY RAND()
                    LIMIT {$limit}
                ");
                $stmt->execute([$moduleId]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $sourceInfo = [
                    'type' => 'module',
                    'id' => $module['id'],
                    'title' => $module['title'],
                    'description' => $module['description']
                ];
            }
            
            if (empty($questions)) {
                sendJson([
                    'success' => true,
                    'source' => $sourceInfo,
                    'flashcards' => [],
                    'total_questions' => 0,
                    'message' => 'No questions available.'
                ]);
            }
            
            $flashcards = [];
            foreach ($questions as $question) {
                $stmt = $conn->prepare("SELECT answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY RAND()");
                $stmt->execute([$question['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $correctAnswers = [];
                $allChoices = [];
                
                foreach ($answers as $answer) {
                    $allChoices[] = $answer['answer_text'];
                    if ($answer['is_correct']) {
                        $correctAnswers[] = $answer['answer_text'];
                    }
                }
                
                $flashcard = [
                    'id' => $question['id'],
                    'question' => $question['question_text'],
                    'question_type' => $question['question_type'],
                    'points' => $question['points'] ?? 1,
                    'choices' => $allChoices,
                    'answer' => count($correctAnswers) === 1 ? $correctAnswers[0] : implode(", ", $correctAnswers),
                    'correct_answers' => $correctAnswers
                ];
                
                if (isset($question['quiz_title'])) {
                    $flashcard['quiz_title'] = $question['quiz_title'];
                }
                
                $flashcards[] = $flashcard;
            }
            
            sendJson([
                'success' => true,
                'source' => $sourceInfo,
                'flashcards' => $flashcards,
                'total_questions' => count($flashcards)
            ]);
            break;

        case "get_progress":
            // Get student progress data - FIXED to match progress.php structure
            
            // Get module stats from student_progress table
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_modules,
                    SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed_modules,
                    SUM(CASE WHEN LOWER(status) = 'in progress' THEN 1 ELSE 0 END) as active_modules
                FROM student_progress 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get quiz stats from quiz_attempts table (matching progress.php)
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT quiz_id) as total_quizzes,
                    SUM(CASE WHEN LOWER(status) IN ('completed', 'passed') THEN 1 ELSE 0 END) as completed_quizzes,
                    SUM(CASE WHEN LOWER(status) = 'passed' THEN 1 ELSE 0 END) as passed_quizzes,
                    SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 ELSE 0 END) as failed_quizzes,
                    AVG(score) as avg_score
                FROM quiz_attempts 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);

            sendJson([
                'success' => true,
                'progress' => [
                    'modules' => [
                        'total_modules' => (int)($moduleStats['total_modules'] ?? 0),
                        'completed_modules' => (int)($moduleStats['completed_modules'] ?? 0),
                        'active_modules' => (int)($moduleStats['active_modules'] ?? 0)
                    ],
                    'quizzes' => [
                        'total_quizzes' => (int)($quizStats['total_quizzes'] ?? 0),
                        'completed_quizzes' => (int)($quizStats['completed_quizzes'] ?? 0),
                        'passed_quizzes' => (int)($quizStats['passed_quizzes'] ?? 0),
                        'failed_quizzes' => (int)($quizStats['failed_quizzes'] ?? 0),
                        'avg_score' => round((float)($quizStats['avg_score'] ?? 0), 1)
                    ]
                ]
            ]);
            break;

        case "chat":
        case "progress":
        case "flashcard":
            // Load ChatAPI for AI-powered responses
            $chatApiFile = __DIR__ . '/ChatAPI.php';
            
            // Also check for chatbot_api.php (alternate name)
            if (!file_exists($chatApiFile)) {
                $chatApiFile = __DIR__ . '/chatbot_api.php';
            }
            
            if (!file_exists($chatApiFile)) {
                sendJson(['error' => 'Chat API file not found'], 500);
            }
            
            require_once $chatApiFile;
            
            // Load dotenv if available
            if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
                require_once __DIR__ . "/../vendor/autoload.php";
                if (class_exists('Dotenv\Dotenv')) {
                    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
                    $dotenv->safeLoad();
                }
            }
            
            try {
                $chatAPI = new ChatAPI();
            } catch (Exception $e) {
                sendJson(['error' => 'API configuration error: ' . $e->getMessage()], 500);
            }
            
            // Get message
            $userMessage = trim($input["message"] ?? '');
            
            if (empty($userMessage)) {
                // Default messages for different tasks
                if ($action === 'progress') {
                    $userMessage = 'Analyze my learning progress and give me personalized recommendations.';
                } else {
                    sendJson(['error' => 'Message is required'], 400);
                }
            }

            $task = $input["task"] ?? $action;
            $moduleId = $input["module_id"] ?? null;

            // Handle request through ChatAPI
            $result = $chatAPI->handleChatRequest($conn, $studentId, $userMessage, $task, $moduleId);

            if (isset($result['error'])) {
                sendJson(['error' => $result['error']], 500);
            }

            sendJson($result);
            break;

        default:
            sendJson(['error' => 'Invalid action: ' . $action], 400);
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendJson(['error' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    sendJson(['error' => 'Server error: ' . $e->getMessage()], 500);
}