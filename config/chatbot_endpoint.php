<?php
/**
 * Chatbot Endpoint - Handles all chatbot requests
 * FIXED: ChatAPI class now included directly - no external file needed
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

/**
 * ChatAPI Class - Included directly to avoid file path issues
 */
class ChatAPI
{
    private $apiKey;
    private $apiUrl = "https://api.openai.com/v1/chat/completions";
    private $model = "gpt-3.5-turbo";

    public function __construct()
    {
        // Try multiple sources for API key
        $this->apiKey = getenv("API_KEY") ?: ($_ENV["API_KEY"] ?? ($_SERVER["API_KEY"] ?? null));
        
        if (!$this->apiKey) {
            $this->apiKey = getenv("OPENAI_API_KEY") ?: ($_ENV["OPENAI_API_KEY"] ?? ($_SERVER["OPENAI_API_KEY"] ?? null));
        }

        // Manual .env load if still not found
        if (!$this->apiKey && file_exists(__DIR__ . '/../.env')) {
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if ($k === 'API_KEY' || $k === 'OPENAI_API_KEY') {
                        $this->apiKey = $v;
                        break;
                    }
                }
            }
        }

        if (!$this->apiKey) {
            throw new Exception("API_KEY is missing. Please add it to your .env file.");
        }
    }

    public function sendMessage($userMessage, $systemMessage = "You are a helpful assistant.", $maxTokens = 500)
    {
        $payload = [
            "model" => $this->model,
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $userMessage]
            ],
            "max_tokens" => $maxTokens,
            "temperature" => 0.8
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ["error" => "Connection failed: " . $error];
        }

        if (!$response) {
            return ["error" => "No response from API"];
        }

        $json = json_decode($response, true);

        if (!$json) {
            return ["error" => "Invalid JSON response"];
        }

        if (isset($json["error"])) {
            return ["error" => $json["error"]["message"] ?? "API error"];
        }

        if (isset($json["choices"][0]["message"]["content"])) {
            return [
                "success" => true,
                "content" => trim($json["choices"][0]["message"]["content"])
            ];
        }

        return ["error" => "Invalid API response format"];
    }

    private function getStudentProgress($conn, $studentId)
    {
        $data = [
            'total_modules' => 0, 'completed_modules' => 0, 'active_modules' => 0,
            'total_quizzes' => 0, 'completed_quizzes' => 0, 'passed_quizzes' => 0,
            'failed_quizzes' => 0, 'average_score' => 0, 'weak_areas' => [], 'strong_areas' => []
        ];

        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total,
                    SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN LOWER(status) = 'in progress' THEN 1 ELSE 0 END) as active
                FROM student_progress WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($moduleStats) {
                $data['total_modules'] = (int)($moduleStats['total'] ?? 0);
                $data['completed_modules'] = (int)($moduleStats['completed'] ?? 0);
                $data['active_modules'] = (int)($moduleStats['active'] ?? 0);
            }

            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT quiz_id) as total,
                    SUM(CASE WHEN LOWER(status) IN ('completed', 'passed') THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(score) as avg_score
                FROM quiz_attempts WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($quizStats) {
                $data['total_quizzes'] = (int)($quizStats['total'] ?? 0);
                $data['passed_quizzes'] = (int)($quizStats['passed'] ?? 0);
                $data['failed_quizzes'] = (int)($quizStats['failed'] ?? 0);
                $data['completed_quizzes'] = $data['passed_quizzes'] + $data['failed_quizzes'];
                $data['average_score'] = round((float)($quizStats['avg_score'] ?? 0), 1);
            }
        } catch (Exception $e) {
            // Return default data
        }

        return $data;
    }

    private function buildSystemPrompt($task, $studentName)
    {
        $baseName = $studentName ? " Student's name is $studentName." : "";

        switch ($task) {
            case "progress":
                return "You are MedAce AI Assistant, a helpful nursing tutor.$baseName Analyze the student's progress data and provide personalized insights. Celebrate achievements, identify areas for improvement, and suggest next steps. Be encouraging and specific. Keep response under 200 words.";
            case "flashcard":
                return "You are MedAce AI Assistant, a nursing education expert.$baseName Create exactly 5 flashcards. Format: CARD [number]\nQ: [Question]\nA: [Answer]\n---";
            default:
                return "You are MedAce AI Assistant, a friendly nursing tutor.$baseName Be helpful, concise (under 150 words), and use nursing terminology appropriately.";
        }
    }

    private function buildUserPrompt($task, $userMessage, $progressData)
    {
        if ($task === "progress") {
            $context = "=== STUDENT PROGRESS ===\n";
            $context .= "Modules: {$progressData['completed_modules']}/{$progressData['total_modules']} completed, {$progressData['active_modules']} in progress\n";
            $context .= "Quizzes: {$progressData['passed_quizzes']} passed, {$progressData['failed_quizzes']} failed\n";
            $context .= "Average Score: {$progressData['average_score']}%\n";
            $context .= "=== END ===\n\n" . $userMessage;
            return $context;
        }
        return $userMessage;
    }

    public function handleChatRequest($conn, $studentId, $userMessage, $task = "chat", $moduleId = null)
    {
        $studentName = "";
        try {
            $stmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $studentName = $user['firstname'] ?? "";
        } catch (Exception $e) {}

        $progressData = $this->getStudentProgress($conn, $studentId);
        $systemPrompt = $this->buildSystemPrompt($task, $studentName);
        $userPrompt = $this->buildUserPrompt($task, $userMessage, $progressData);
        
        $maxTokens = $task === 'progress' ? 600 : 400;
        $response = $this->sendMessage($userPrompt, $systemPrompt, $maxTokens);

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        return ['reply' => $response['content'], 'task' => $task];
    }
}

try {
    // Load database connection
    require_once __DIR__ . '/db_conn.php';

    // Handle different actions
    switch ($action) {
        case "get_modules":
            $stmt = $conn->prepare("
                SELECT m.id, m.title, m.description, COALESCE(sp.status, 'Pending') AS status
                FROM modules m
                LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
                WHERE m.status IN ('active', 'published')
                ORDER BY m.display_order ASC, m.created_at DESC
            ");
            $stmt->execute([$studentId]);
            $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJson(['success' => true, 'modules' => $modules]);
            break;

        case "get_flashcard_questions":
            $quizId = intval($input["quiz_id"] ?? 0);
            $moduleId = intval($input["module_id"] ?? 0);
            $limit = max(1, min(50, intval($input["limit"] ?? 20)));
            
            if (!$quizId && !$moduleId) {
                sendJson(['error' => 'Missing quiz_id or module_id'], 400);
            }
            
            if ($quizId > 0) {
                $stmt = $conn->prepare("SELECT id, title, description FROM quizzes WHERE id = ?");
                $stmt->execute([$quizId]);
                $source = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$source) sendJson(['error' => 'Quiz not found'], 404);
                
                $stmt = $conn->prepare("SELECT id, question_text, question_type, points FROM questions WHERE quiz_id = ? AND question_type IN ('multiple_choice', 'true_false') ORDER BY RAND() LIMIT {$limit}");
                $stmt->execute([$quizId]);
                $sourceInfo = ['type' => 'quiz', 'id' => $source['id'], 'title' => $source['title']];
            } else {
                $stmt = $conn->prepare("SELECT id, title, description FROM modules WHERE id = ?");
                $stmt->execute([$moduleId]);
                $source = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$source) sendJson(['error' => 'Module not found'], 404);
                
                $stmt = $conn->prepare("SELECT q.id, q.question_text, q.question_type, q.points FROM questions q JOIN quizzes qz ON q.quiz_id = qz.id WHERE qz.module_id = ? AND q.question_type IN ('multiple_choice', 'true_false') ORDER BY RAND() LIMIT {$limit}");
                $stmt->execute([$moduleId]);
                $sourceInfo = ['type' => 'module', 'id' => $source['id'], 'title' => $source['title']];
            }
            
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $flashcards = [];
            
            foreach ($questions as $q) {
                $stmt = $conn->prepare("SELECT answer_text, is_correct FROM answers WHERE question_id = ?");
                $stmt->execute([$q['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $choices = []; $correct = [];
                foreach ($answers as $a) {
                    $choices[] = $a['answer_text'];
                    if ($a['is_correct']) $correct[] = $a['answer_text'];
                }
                
                $flashcards[] = [
                    'id' => $q['id'], 'question' => $q['question_text'],
                    'choices' => $choices, 'answer' => implode(", ", $correct),
                    'correct_answers' => $correct
                ];
            }
            
            sendJson(['success' => true, 'source' => $sourceInfo, 'flashcards' => $flashcards, 'total_questions' => count($flashcards)]);
            break;

        case "get_progress":
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total_modules,
                    SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed_modules,
                    SUM(CASE WHEN LOWER(status) = 'in progress' THEN 1 ELSE 0 END) as active_modules
                FROM student_progress WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT quiz_id) as total_quizzes,
                    SUM(CASE WHEN LOWER(status) = 'passed' THEN 1 ELSE 0 END) as passed_quizzes,
                    SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 ELSE 0 END) as failed_quizzes,
                    AVG(score) as avg_score
                FROM quiz_attempts WHERE student_id = ?
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
                sendJson(['error' => $e->getMessage()], 500);
            }
            
            $userMessage = trim($input["message"] ?? '');
            if (empty($userMessage)) {
                $userMessage = ($action === 'progress') 
                    ? 'Analyze my learning progress and give me personalized recommendations.'
                    : '';
                if (empty($userMessage)) sendJson(['error' => 'Message is required'], 400);
            }

            $task = $input["task"] ?? $action;
            $moduleId = $input["module_id"] ?? null;

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
    sendJson(['error' => 'Database error'], 500);
} catch (Exception $e) {
    sendJson(['error' => $e->getMessage()], 500);
}