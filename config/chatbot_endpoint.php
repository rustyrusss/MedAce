<?php
/**
 * Chatbot Endpoint - Handles all chatbot requests
 * UPDATED to support flashcard quiz page
 * FIXED: Correct table names
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
    require_once __DIR__ . '/chatbot_api.php';

    // Load dotenv if available
    if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
        require_once __DIR__ . "/../vendor/autoload.php";
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
            $dotenv->safeLoad();
        }
    }

    // Initialize ChatAPI
    $chatAPI = new ChatAPI();

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

        case "get_module_content":
            // Get module content for flashcard generation
            if (!isset($input["module_id"]) || empty($input["module_id"])) {
                sendJson(['error' => 'Missing module_id'], 400);
            }
            
            $moduleId = intval($input["module_id"]);
            
            // Get module data
            $stmt = $conn->prepare("
                SELECT id, title, description, content, file_path 
                FROM modules 
                WHERE id = ?
            ");
            $stmt->execute([$moduleId]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$module) {
                sendJson(['error' => 'Module not found'], 404);
            }
            
            // Return module data for AI processing
            sendJson([
                'success' => true,
                'module' => $module
            ]);
            break;

        case "get_progress":
            // FIXED: Get student progress data using correct table name
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

            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_quizzes,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_quizzes,
                    SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed_quizzes,
                    AVG(CASE WHEN score IS NOT NULL THEN score ELSE 0 END) as avg_score
                FROM quiz_participation 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);

            sendJson([
                'success' => true,
                'progress' => [
                    'modules' => $moduleStats,
                    'quizzes' => $quizStats
                ]
            ]);
            break;

        case "chat":
        case "progress":
        case "flashcard":
            // Validate message
            if (!isset($input["message"]) || empty(trim($input["message"]))) {
                sendJson(['error' => 'Missing message'], 400);
            }

            $userMessage = trim($input["message"]);
            $task = $input["task"] ?? "chat";
            $moduleId = $input["module_id"] ?? null;

            // Handle request
            $result = $chatAPI->handleChatRequest($conn, $studentId, $userMessage, $task, $moduleId);

            if (isset($result['error'])) {
                sendJson(['error' => $result['error']], 500);
            }

            sendJson($result);
            break;

        default:
            sendJson(['error' => 'Invalid action'], 400);
    }

} catch (Exception $e) {
    sendJson(['error' => $e->getMessage()], 500);
}
?>