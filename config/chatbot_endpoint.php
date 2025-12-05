<?php
/**
 * Chatbot Endpoint - Handles all chatbot requests
 * Enhanced with accurate progress calculations and comprehensive graphs
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
            // ACCURATE Progress Calculation
            
            // 1. Get ALL available modules (not just enrolled ones)
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total_available_modules
                FROM modules 
                WHERE status IN ('active', 'published')
            ");
            $stmt->execute();
            $availableModules = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalAvailableModules = (int)($availableModules['total_available_modules'] ?? 0);

            // 2. Get student's module progress
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as enrolled_modules,
                    SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed_modules,
                    SUM(CASE WHEN LOWER(status) = 'in progress' THEN 1 ELSE 0 END) as in_progress_modules,
                    SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending_modules
                FROM student_progress 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $enrolledModules = (int)($moduleStats['enrolled_modules'] ?? 0);
            $completedModules = (int)($moduleStats['completed_modules'] ?? 0);
            $inProgressModules = (int)($moduleStats['in_progress_modules'] ?? 0);
            $pendingModules = (int)($moduleStats['pending_modules'] ?? 0);
            
            // Calculate not started modules
            $notStartedModules = $totalAvailableModules - $enrolledModules;

            // 3. Get ALL quizzes from enrolled modules
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT q.id) as total_available_quizzes
                FROM quizzes q
                JOIN student_progress sp ON q.module_id = sp.module_id
                WHERE sp.student_id = ?
            ");
            $stmt->execute([$studentId]);
            $availableQuizzes = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalAvailableQuizzes = (int)($availableQuizzes['total_available_quizzes'] ?? 0);

            // 4. Get quiz attempts (count each quiz once - get latest attempt)
            $stmt = $conn->prepare("
                SELECT 
                    quiz_id,
                    MAX(id) as latest_attempt_id,
                    MAX(score) as best_score
                FROM quiz_attempts 
                WHERE student_id = ?
                GROUP BY quiz_id
            ");
            $stmt->execute([$studentId]);
            $latestAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $attemptedQuizIds = array_column($latestAttempts, 'quiz_id');

            // 5. Get detailed quiz statistics (only latest attempts)
            if (!empty($attemptedQuizIds)) {
                $placeholders = implode(',', array_fill(0, count($attemptedQuizIds), '?'));
                $stmt = $conn->prepare("
                    SELECT 
                        qa.quiz_id,
                        qa.score,
                        qa.status,
                        qa.completed_at
                    FROM quiz_attempts qa
                    INNER JOIN (
                        SELECT quiz_id, MAX(id) as max_id
                        FROM quiz_attempts
                        WHERE student_id = ?
                        GROUP BY quiz_id
                    ) latest ON qa.id = latest.max_id
                    WHERE qa.quiz_id IN ($placeholders)
                ");
                $params = array_merge([$studentId], $attemptedQuizIds);
                $stmt->execute($params);
                $quizDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $quizDetails = [];
            }

            // Calculate quiz stats
            $passedQuizzes = 0;
            $failedQuizzes = 0;
            $totalScore = 0;
            $scoreCount = 0;
            
            foreach ($quizDetails as $quiz) {
                if (strtolower($quiz['status']) === 'passed') {
                    $passedQuizzes++;
                } elseif (strtolower($quiz['status']) === 'failed') {
                    $failedQuizzes++;
                }
                
                if ($quiz['score'] !== null) {
                    $totalScore += (float)$quiz['score'];
                    $scoreCount++;
                }
            }
            
            $attemptedQuizzes = count($quizDetails);
            $notAttemptedQuizzes = $totalAvailableQuizzes - $attemptedQuizzes;
            $avgScore = $scoreCount > 0 ? round($totalScore / $scoreCount, 1) : 0;

            // 6. Calculate overall completion rate
            $totalItems = $totalAvailableModules + $totalAvailableQuizzes;
            $completedItems = $completedModules + $passedQuizzes;
            $completionRate = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 1) : 0;

            // 7. Graph Data: Daily Activity (Last 30 days)
            $stmt = $conn->prepare("
                SELECT DATE(completed_at) as date, 
                       COUNT(*) as quiz_count,
                       AVG(score) as avg_score
                FROM quiz_attempts
                WHERE student_id = ? 
                  AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(completed_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$studentId]);
            $dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 8. Graph Data: Weekly Progress (Last 12 weeks)
            $stmt = $conn->prepare("
                SELECT YEARWEEK(completed_at, 1) as week,
                       DATE(DATE_SUB(completed_at, INTERVAL WEEKDAY(completed_at) DAY)) as week_start,
                       COUNT(*) as quiz_count,
                       AVG(score) as avg_score,
                       SUM(CASE WHEN LOWER(status) = 'passed' THEN 1 ELSE 0 END) as passed_count
                FROM quiz_attempts
                WHERE student_id = ? 
                  AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                GROUP BY YEARWEEK(completed_at, 1), week_start
                ORDER BY week ASC
            ");
            $stmt->execute([$studentId]);
            $weeklyProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 9. Graph Data: Monthly Performance (Last 6 months)
            $stmt = $conn->prepare("
                SELECT DATE_FORMAT(completed_at, '%Y-%m') as month,
                       DATE_FORMAT(completed_at, '%b %Y') as month_label,
                       COUNT(*) as quiz_count,
                       AVG(score) as avg_score,
                       SUM(CASE WHEN LOWER(status) = 'passed' THEN 1 ELSE 0 END) as passed_count,
                       SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM quiz_attempts
                WHERE student_id = ? 
                  AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(completed_at, '%Y-%m'), month_label
                ORDER BY month ASC
            ");
            $stmt->execute([$studentId]);
            $monthlyPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 10. Graph Data: Score Distribution
            $stmt = $conn->prepare("
                SELECT 
                    CASE 
                        WHEN score >= 90 THEN '90-100'
                        WHEN score >= 80 THEN '80-89'
                        WHEN score >= 70 THEN '70-79'
                        WHEN score >= 60 THEN '60-69'
                        ELSE 'Below 60'
                    END as score_range,
                    COUNT(*) as count
                FROM quiz_attempts
                WHERE student_id = ?
                GROUP BY score_range
                ORDER BY score_range DESC
            ");
            $stmt->execute([$studentId]);
            $scoreDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 11. Performance by Module
            $stmt = $conn->prepare("
                SELECT m.title as module_title, 
                       COUNT(DISTINCT qa.quiz_id) as quizzes_taken,
                       AVG(qa.score) as avg_score,
                       SUM(CASE WHEN LOWER(qa.status) = 'passed' THEN 1 ELSE 0 END) as passed_count
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN modules m ON q.module_id = m.id
                WHERE qa.student_id = ?
                GROUP BY m.id, m.title
                ORDER BY avg_score DESC
            ");
            $stmt->execute([$studentId]);
            $performanceByModule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 12. Recent Activity
            $stmt = $conn->prepare("
                SELECT qa.quiz_id, q.title as quiz_title, qa.score, qa.status, 
                       qa.completed_at, m.title as module_title
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                LEFT JOIN modules m ON q.module_id = m.id
                WHERE qa.student_id = ?
                ORDER BY qa.completed_at DESC
                LIMIT 10
            ");
            $stmt->execute([$studentId]);
            $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 13. Module Details
            $stmt = $conn->prepare("
                SELECT m.id, m.title, COALESCE(sp.status, 'Not Started') as status, 
                       sp.progress_percentage, sp.started_at, sp.completed_at
                FROM modules m
                LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
                WHERE m.status IN ('active', 'published')
                ORDER BY m.display_order ASC
            ");
            $stmt->execute([$studentId]);
            $moduleDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build response with accurate calculations
            sendJson([
                'success' => true,
                'progress' => [
                    'overview' => [
                        'completion_rate' => $completionRate,
                        'total_items' => $totalItems,
                        'completed_items' => $completedItems
                    ],
                    'modules' => [
                        'total_available' => $totalAvailableModules,
                        'enrolled' => $enrolledModules,
                        'completed' => $completedModules,
                        'in_progress' => $inProgressModules,
                        'pending' => $pendingModules,
                        'not_started' => $notStartedModules,
                        'completion_percentage' => $totalAvailableModules > 0 ? round(($completedModules / $totalAvailableModules) * 100, 1) : 0,
                        'details' => $moduleDetails
                    ],
                    'quizzes' => [
                        'total_available' => $totalAvailableQuizzes,
                        'attempted' => $attemptedQuizzes,
                        'not_attempted' => $notAttemptedQuizzes,
                        'passed' => $passedQuizzes,
                        'failed' => $failedQuizzes,
                        'avg_score' => $avgScore,
                        'pass_rate' => $attemptedQuizzes > 0 ? round(($passedQuizzes / $attemptedQuizzes) * 100, 1) : 0
                    ],
                    'performance_by_module' => $performanceByModule,
                    'recent_activity' => $recentActivity,
                    'strengths' => array_slice($performanceByModule, 0, 3),
                    'needs_improvement' => array_slice(array_reverse($performanceByModule), 0, 3),
                    'graphs' => [
                        'daily_activity' => $dailyActivity,
                        'weekly_progress' => $weeklyProgress,
                        'monthly_performance' => $monthlyPerformance,
                        'score_distribution' => $scoreDistribution
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