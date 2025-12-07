<?php
/**
 * Enhanced Chatbot Integration - Complete Version
 * Combines all chatbot functionality with progress analysis and study tips
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
if (!$input) {
    sendJson(['error' => 'Invalid JSON'], 400);
}

$studentId = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/db_conn.php';
    
    // Load API key from multiple sources
    $apiKey = null;
    
    // Try env.php first
    if (file_exists(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
        if (defined('OPENAI_API_KEY')) {
            $apiKey = OPENAI_API_KEY;
        }
    }
    
    // Try composer autoload
    if (!$apiKey && file_exists(__DIR__ . "/../vendor/autoload.php")) {
        require_once __DIR__ . "/../vendor/autoload.php";
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
            $dotenv->safeLoad();
        }
    }
    
    // Try environment variables
    if (!$apiKey) {
        $apiKey = getenv("API_KEY") ?: ($_ENV["API_KEY"] ?? ($_SERVER["API_KEY"] ?? null));
    }
    if (!$apiKey) {
        $apiKey = getenv("OPENAI_API_KEY") ?: ($_ENV["OPENAI_API_KEY"] ?? ($_SERVER["OPENAI_API_KEY"] ?? null));
    }
    
    // Try manual .env load
    if (!$apiKey && file_exists(__DIR__ . '/../.env')) {
        $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if ($k === 'API_KEY' || $k === 'OPENAI_API_KEY') {
                    $apiKey = $v;
                    break;
                }
            }
        }
    }
    
    // Handle different actions
    $action = $input['action'] ?? null;
    $message = $input['message'] ?? null;
    
    // ============================================
    // ACTION: GET MODULES
    // ============================================
    if ($action === 'get_modules') {
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
    }
    
    // ============================================
    // ACTION: GET MODULE CONTENT
    // ============================================
    if ($action === 'get_module_content') {
        $moduleId = $input['module_id'] ?? null;
        
        if (!$moduleId) {
            sendJson(['error' => 'Missing module_id'], 400);
        }
        
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
        
        sendJson([
            'success' => true,
            'module' => $module
        ]);
    }
    
    // ============================================
    // ACTION: GET PROGRESS
    // ============================================
    if ($action === 'get_progress') {
        $progressData = getStudentProgress($conn, $studentId);
        
        // Calculate overall percentage
        $totalItems = $progressData['total_modules'] + $progressData['total_quizzes'];
        $completedItems = $progressData['completed_modules'] + $progressData['passed_quizzes'];
        $progressPercent = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
        
        sendJson([
            'success' => true,
            'progress' => [
                'modules' => [
                    'total_available' => $progressData['total_modules'],
                    'completed' => $progressData['completed_modules'],
                    'in_progress' => $progressData['active_modules'],
                    'pending' => $progressData['total_modules'] - $progressData['completed_modules'] - $progressData['active_modules']
                ],
                'quizzes' => [
                    'total_available' => $progressData['total_quizzes'],
                    'passed' => $progressData['passed_quizzes'],
                    'failed' => $progressData['failed_quizzes'],
                    'pending' => $progressData['total_quizzes'] - $progressData['completed_quizzes'],
                    'avg_score' => $progressData['average_score']
                ],
                'overall_percent' => $progressPercent,
                'total_items' => $totalItems,
                'completed_items' => $completedItems
            ]
        ]);
    }
    
    // ============================================
    // ACTION: GET DAILY TIP
    // ============================================
    if ($action === 'get_daily_tip') {
        try {
            $tip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();
            sendJson([
                'success' => true,
                'tip' => $tip ?: 'Stay consistent with your studies and practice regularly!'
            ]);
        } catch (Exception $e) {
            sendJson([
                'success' => true,
                'tip' => 'Stay consistent with your studies and practice regularly!'
            ]);
        }
    }
    
    // ============================================
    // ACTION: ANALYZE PROGRESS (AI-powered)
    // ============================================
    if ($action === 'analyze_progress') {
        if (!$apiKey) {
            sendJson([
                'success' => true,
                'analysis' => "I can see your progress data! You're making good progress in your nursing studies. Keep focusing on completing your pending modules and reviewing areas where you've had challenges.\n\n**Note:** For detailed AI-powered analysis, please configure the OpenAI API key in `/config/env.php`.",
                'reply' => "Please configure API key for AI analysis."
            ]);
        }
        
        $progressData = getStudentProgress($conn, $studentId);
        
        // Get student name
        $stmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $studentName = $stmt->fetchColumn() ?: 'Student';
        
        // Build detailed context
        $context = "Analyze this nursing student's ({$studentName}) learning progress:\n\n";
        $context .= "Overall Statistics:\n";
        $context .= "- Total Modules: {$progressData['total_modules']}\n";
        $context .= "- Completed Modules: {$progressData['completed_modules']}\n";
        $context .= "- Active Modules: {$progressData['active_modules']}\n";
        $context .= "- Total Quizzes: {$progressData['total_quizzes']}\n";
        $context .= "- Passed Quizzes: {$progressData['passed_quizzes']}\n";
        $context .= "- Failed Quizzes: {$progressData['failed_quizzes']}\n";
        $context .= "- Average Quiz Score: {$progressData['average_score']}%\n\n";
        
        if (!empty($progressData['weak_areas'])) {
            $context .= "Weak Areas:\n";
            foreach ($progressData['weak_areas'] as $area) {
                $context .= "- {$area['subject']}: " . round($area['avg_score'], 1) . "% avg\n";
            }
            $context .= "\n";
        }
        
        if (!empty($progressData['strong_areas'])) {
            $context .= "Strong Areas:\n";
            foreach ($progressData['strong_areas'] as $area) {
                $context .= "- {$area['subject']}: " . round($area['avg_score'], 1) . "% avg\n";
            }
            $context .= "\n";
        }
        
        $context .= $message ?: "Provide comprehensive analysis with strengths, areas for improvement, and specific recommendations.";
        
        $systemPrompt = "You are MedAce Assistant, an expert nursing education AI analyst. Provide detailed, personalized progress analysis based on actual student data. Be specific, encouraging, and actionable. Format with clear sections using bold headers (**Header:**) and bullet points. Make each analysis unique and data-driven. Keep response under 300 words.";
        
        $response = callOpenAI($apiKey, $systemPrompt, $context);
        
        sendJson([
            'success' => true,
            'analysis' => $response,
            'reply' => $response
        ]);
    }
    
    // ============================================
    // ACTION: GET STUDY TIPS (AI-powered)
    // ============================================
    if ($action === 'get_study_tips') {
        if (!$apiKey) {
            sendJson([
                'success' => true,
                'tips' => "Here are some evidence-based study tips:\n\n1. **Active Recall**: Test yourself regularly\n2. **Spaced Repetition**: Review at intervals\n3. **Practice Scenarios**: Apply to real situations\n4. **Study Groups**: Learn together\n5. **Concept Mapping**: Connect ideas visually\n\n**Note:** For personalized tips, configure API key in `/config/env.php`.",
                'reply' => "Please configure API key for personalized tips."
            ]);
        }
        
        $progressData = getStudentProgress($conn, $studentId);
        
        $context = "Generate personalized study tips for nursing student:\n\n";
        $context .= "Progress: {$progressData['completed_modules']}/{$progressData['total_modules']} modules, ";
        $context .= "{$progressData['passed_quizzes']} passed quizzes, ";
        $context .= "Avg score: {$progressData['average_score']}%\n\n";
        $context .= $message ?: "Provide 5 specific, actionable study tips based on their progress.";
        
        $systemPrompt = "You are MedAce Assistant, expert nursing education AI. Generate personalized study tips based on student data. Be specific and actionable. Format clearly with numbered points. Keep under 250 words.";
        
        $response = callOpenAI($apiKey, $systemPrompt, $context);
        
        sendJson([
            'success' => true,
            'tips' => $response,
            'reply' => $response
        ]);
    }
    
    // ============================================
    // CHAT/AI ACTIONS (general chat, flashcards)
    // ============================================
    if ($action === 'chat' || !$action) {
        if (!$apiKey) {
            sendJson(['error' => 'API key not configured'], 500);
        }
        
        if (!$message || empty(trim($message))) {
            sendJson(['error' => 'Missing message'], 400);
        }
        
        $message = trim($message);
        $task = $input["task"] ?? "chat";
        $moduleId = $input["module_id"] ?? null;
        
        // Get student info
        $stmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $studentName = $stmt->fetchColumn() ?: "";
        
        // Get progress data
        $progressData = getStudentProgress($conn, $studentId);
        
        // Get module content if flashcard task
        $moduleContent = null;
        if ($task === "flashcard" && $moduleId) {
            $stmt = $conn->prepare("SELECT title, description, content FROM modules WHERE id = ?");
            $stmt->execute([$moduleId]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($module) {
                $moduleContent = "MODULE: " . $module['title'] . "\n\n";
                if (!empty($module['description'])) {
                    $moduleContent .= "DESCRIPTION:\n" . $module['description'] . "\n\n";
                }
                if (!empty($module['content'])) {
                    $moduleContent .= "CONTENT:\n" . substr($module['content'], 0, 3000) . "\n";
                }
            }
        }
        
        // Build prompts
        $systemPrompt = buildSystemPrompt($task, $studentName, $progressData);
        $userPrompt = buildUserPrompt($task, $message, $progressData, $moduleContent);
        
        // Call AI
        $payload = [
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userPrompt]
            ],
            "max_tokens" => $task === "flashcard" ? 1500 : 500,
            "temperature" => 0.8
        ];
        
        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer $apiKey"
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$response) {
            sendJson(['error' => 'API connection failed'], 500);
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            sendJson(['error' => $data['error']['message'] ?? 'API error'], $httpCode);
        }
        
        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = trim($content);
        
        if (empty($content)) {
            sendJson(['error' => 'Empty AI response'], 500);
        }
        
        // Parse flashcards if needed
        if ($task === "flashcard") {
            $flashcards = parseFlashcards($content);
            sendJson([
                'success' => true,
                'reply' => $content,
                'flashcards' => $flashcards,
                'task' => 'flashcard'
            ]);
        } else {
            sendJson([
                'success' => true,
                'reply' => $content,
                'task' => $task
            ]);
        }
    }
    
} catch (Exception $e) {
    sendJson(['error' => $e->getMessage()], 500);
}

/**
 * Get comprehensive student progress data
 */
function getStudentProgress($conn, $studentId) {
    $data = [
        'total_modules' => 0,
        'completed_modules' => 0,
        'active_modules' => 0,
        'total_quizzes' => 0,
        'completed_quizzes' => 0,
        'passed_quizzes' => 0,
        'failed_quizzes' => 0,
        'average_score' => 0,
        'recent_activity' => [],
        'weak_areas' => [],
        'strong_areas' => []
    ];
    
    try {
        // Get module stats
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN LOWER(status) = 'in progress' THEN 1 ELSE 0 END) as active
            FROM student_progress 
            WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['total_modules'] = (int)$moduleStats['total'];
        $data['completed_modules'] = (int)$moduleStats['completed'];
        $data['active_modules'] = (int)$moduleStats['active'];
        
        // Get quiz stats
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN score IS NOT NULL THEN score ELSE 0 END) as avg_score
            FROM quiz_participation 
            WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['total_quizzes'] = (int)$quizStats['total'];
        $data['completed_quizzes'] = (int)$quizStats['completed'];
        $data['passed_quizzes'] = (int)$quizStats['passed'];
        $data['failed_quizzes'] = (int)$quizStats['failed'];
        $data['average_score'] = round((float)$quizStats['avg_score'], 1);
        
        // Get weak/strong areas
        $stmt = $conn->prepare("
            SELECT q.subject, AVG(qp.score) as avg_score
            FROM quiz_participation qp
            JOIN quizzes q ON q.id = qp.quiz_id
            WHERE qp.student_id = ? AND qp.score IS NOT NULL
            GROUP BY q.subject
            HAVING avg_score < 70
            ORDER BY avg_score ASC
            LIMIT 3
        ");
        $stmt->execute([$studentId]);
        $data['weak_areas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("
            SELECT q.subject, AVG(qp.score) as avg_score
            FROM quiz_participation qp
            JOIN quizzes q ON q.id = qp.quiz_id
            WHERE qp.student_id = ? AND qp.score IS NOT NULL
            GROUP BY q.subject
            HAVING avg_score >= 80
            ORDER BY avg_score DESC
            LIMIT 3
        ");
        $stmt->execute([$studentId]);
        $data['strong_areas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Progress data error: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Call OpenAI API
 */
function callOpenAI($apiKey, $systemPrompt, $userPrompt) {
    $payload = [
        "model" => "gpt-3.5-turbo",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userPrompt]
        ],
        "max_tokens" => 800,
        "temperature" => 0.8
    ];
    
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$response || $httpCode !== 200) {
        return "I'm currently unable to generate a personalized response. Please try again later.";
    }
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'No response generated.';
}

/**
 * Build system prompt based on task
 */
function buildSystemPrompt($task, $studentName, $progressData) {
    $baseName = $studentName ? " Student's name is $studentName." : "";
    
    switch ($task) {
        case "progress":
            return "You are MedAce AI Assistant.$baseName Provide personalized insights about progress, identify strengths and areas for improvement, suggest next steps. Be encouraging. Under 200 words.";
            
        case "flashcard":
            return "You are MedAce AI Assistant.$baseName Generate exactly 8 flashcards in JSON format: [{\"question\":\"...\",\"answer\":\"...\"}]. Focus on clinical application. Output ONLY valid JSON.";
            
        default:
            return "You are MedAce AI Assistant.$baseName Be encouraging, concise (under 150 words), use nursing terminology. If asked about progress/flashcards, suggest using the respective buttons.";
    }
}

/**
 * Build user prompt with context
 */
function buildUserPrompt($task, $userMessage, $progressData, $moduleContent = null) {
    if ($task === "flashcard" && $moduleContent) {
        return "Based on this module, generate 8 nursing flashcards in JSON:\n\n$moduleContent";
    }
    return $userMessage;
}

/**
 * Parse flashcards from AI response
 */
function parseFlashcards($content) {
    $flashcards = [];
    
    try {
        $cleanContent = preg_replace('/```json\n?/i', '', $content);
        $cleanContent = preg_replace('/```\n?/', '', $cleanContent);
        $cleanContent = trim($cleanContent);
        
        $parsed = json_decode($cleanContent, true);
        
        if (is_array($parsed)) {
            foreach ($parsed as $card) {
                if (isset($card['question']) && isset($card['answer'])) {
                    $flashcards[] = [
                        'question' => trim($card['question']),
                        'answer' => trim($card['answer'])
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Fallback parsing
    }
    
    return $flashcards;
}
?>