<?php
/**
 * Enhanced Chatbot Integration - Complete Version
 * Combines chatbot_integration.php functionality with chatbot_endpoint.php structure
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
    
    // Load API key
    if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
        require_once __DIR__ . "/../vendor/autoload.php";
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
            $dotenv->safeLoad();
        }
    }
    
    $apiKey = getenv("API_KEY") ?: ($_ENV["API_KEY"] ?? ($_SERVER["API_KEY"] ?? null));
    if (!$apiKey) {
        $apiKey = getenv("OPENAI_API_KEY") ?: ($_ENV["OPENAI_API_KEY"] ?? ($_SERVER["OPENAI_API_KEY"] ?? null));
    }
    
    // Manual .env load
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
        exit; // IMPORTANT: Stop execution here
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
        exit; // IMPORTANT: Stop execution here
    }
    
    // ============================================
    // ACTION: GET PROGRESS
    // ============================================
    if ($action === 'get_progress') {
        $progressData = getStudentProgress($conn, $studentId);
        
        sendJson([
            'success' => true,
            'progress' => $progressData
        ]);
        exit; // IMPORTANT: Stop execution here
    }
    
    // ============================================
    // CHAT/AI ACTIONS (chat, progress, flashcard)
    // ============================================
    
    if (!$apiKey) {
        sendJson(['error' => 'API key not configured'], 500);
    }
    
    $message = $input["message"] ?? null;
    $task = $input["task"] ?? "chat"; // 'chat', 'progress', or 'flashcard'
    $moduleId = $input["module_id"] ?? null;
    
    if (!$message || empty(trim($message))) {
        sendJson(['error' => 'Missing message'], 400);
    }
    
    $message = trim($message);
    
    // Get student info
    $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $stmt->execute([$studentId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentName = $user ? $user['firstname'] : "";
    
    // Get student progress data
    $progressData = getStudentProgress($conn, $studentId);
    
    // Get module content if flashcard task with module_id
    $moduleContent = null;
    if ($task === "flashcard" && $moduleId) {
        $stmt = $conn->prepare("
            SELECT title, description, content
            FROM modules
            WHERE id = ?
        ");
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
    
    // Build system prompt
    $systemPrompt = buildSystemPrompt($task, $studentName, $progressData);
    
    // Build user prompt
    $userPrompt = buildUserPrompt($task, $message, $progressData, $moduleContent);
    
    // Build OpenAI request
    $payload = [
        "model" => "gpt-3.5-turbo",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userPrompt]
        ],
        "max_tokens" => $task === "flashcard" ? 1500 : 500,
        "temperature" => $task === "flashcard" ? 0.8 : 0.8
    ];
    
    // Call OpenAI API
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
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        sendJson(['error' => 'Connection failed: ' . $error], 500);
    }
    
    if (!$response) {
        sendJson(['error' => 'Empty response from API'], 500);
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        sendJson(['error' => 'Invalid JSON response from API'], 500);
    }
    
    // Handle API errors
    if (isset($data['error'])) {
        sendJson(['error' => $data['error']['message'] ?? 'OpenAI API error'], $httpCode);
    }
    
    // Extract content
    if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
        sendJson(['error' => 'No response from AI'], 500);
    }
    
    $content = $data['choices'][0]['message']['content'] ?? '';
    $content = trim($content);
    
    if (empty($content)) {
        sendJson(['error' => 'Empty content from AI'], 500);
    }
    
    // Parse flashcards if task is flashcard
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
        // Get module stats - FIXED: Use correct table name
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
        
        // Get recent activity (last 5 items)
        $stmt = $conn->prepare("
            SELECT 'module' as type, m.title, sp.status, sp.updated_at as date
            FROM student_progress sp
            JOIN modules m ON m.id = sp.module_id
            WHERE sp.student_id = ?
            UNION ALL
            SELECT 'quiz' as type, q.title, qp.status, qp.submitted_at as date
            FROM quiz_participation qp
            JOIN quizzes q ON q.id = qp.quiz_id
            WHERE qp.student_id = ?
            ORDER BY date DESC
            LIMIT 5
        ");
        $stmt->execute([$studentId, $studentId]);
        $data['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Identify weak areas (subjects with low average scores)
        $stmt = $conn->prepare("
            SELECT q.subject, AVG(qp.score) as avg_score, COUNT(*) as attempts
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
        
        // Identify strong areas
        $stmt = $conn->prepare("
            SELECT q.subject, AVG(qp.score) as avg_score, COUNT(*) as attempts
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
        // Return default data if queries fail
        error_log("Progress data error: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Build system prompt based on task
 */
function buildSystemPrompt($task, $studentName, $progressData) {
    $baseName = $studentName ? " Student's name is $studentName." : "";
    
    switch ($task) {
        case "progress":
            return "You are MedAce AI Assistant, a helpful nursing tutor with access to the student's learning progress data.$baseName Your role is to provide personalized insights about their progress, identify strengths and areas for improvement, suggest next steps, and motivate them. Be encouraging and specific. Keep responses under 200 words.";
            
        case "flashcard":
            return "You are MedAce AI Assistant, a nursing education expert specialized in creating effective study flashcards.$baseName Generate exactly 8 flashcards in JSON format. Return ONLY a valid JSON array with this exact structure:

[
  {
    \"question\": \"Clear, specific question testing key concept\",
    \"answer\": \"Concise, accurate answer with key details\"
  }
]

Make questions challenging but fair. Focus on clinical application and understanding. Use proper nursing terminology. Output ONLY the JSON array, no other text.";
            
        default: // chat
            return "You are MedAce AI Assistant, a helpful nursing tutor.$baseName Be encouraging, concise (under 150 words), and use nursing terminology appropriately. If asked about progress, suggest they use the 'Progress' button. If asked for flashcards, suggest they use the 'Flashcards' button.";
    }
}

/**
 * Build user prompt with context
 */
function buildUserPrompt($task, $userMessage, $progressData, $moduleContent = null) {
    switch ($task) {
        case "progress":
            $context = "Current Progress Data:\n";
            $context .= "- Modules: {$progressData['completed_modules']}/{$progressData['total_modules']} completed\n";
            $context .= "- Quizzes: {$progressData['completed_quizzes']} completed ({$progressData['passed_quizzes']} passed, {$progressData['failed_quizzes']} failed)\n";
            $context .= "- Average Score: {$progressData['average_score']}%\n";
            
            if (!empty($progressData['weak_areas'])) {
                $context .= "\nAreas needing improvement:\n";
                foreach ($progressData['weak_areas'] as $area) {
                    $context .= "- {$area['subject']}: " . round($area['avg_score'], 1) . "% avg\n";
                }
            }
            
            if (!empty($progressData['strong_areas'])) {
                $context .= "\nStrong areas:\n";
                foreach ($progressData['strong_areas'] as $area) {
                    $context .= "- {$area['subject']}: " . round($area['avg_score'], 1) . "% avg\n";
                }
            }
            
            return $context . "\n" . $userMessage;
            
        case "flashcard":
            if ($moduleContent) {
                return "Based on the following module content, generate 8 nursing flashcards in JSON format:\n\n$moduleContent\n\nGenerate flashcards that test key concepts, clinical applications, and important facts.";
            } else {
                return "Generate 8 nursing flashcards in JSON format for: " . $userMessage;
            }
            
        default:
            return $userMessage;
    }
}

/**
 * Parse flashcards from AI response
 */
function parseFlashcards($content) {
    $flashcards = [];
    
    // Try JSON parsing first
    try {
        // Remove markdown code blocks if present
        $cleanContent = preg_replace('/```json\n?/i', '', $content);
        $cleanContent = preg_replace('/```\n?/', '', $cleanContent);
        $cleanContent = trim($cleanContent);
        
        $parsed = json_decode($cleanContent, true);
        
        if (is_array($parsed) && !empty($parsed)) {
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
        // JSON parsing failed
    }
    
    // If JSON parsing failed, try text parsing
    if (empty($flashcards)) {
        $cards = preg_split('/CARD\s+\d+/i', $content);
        
        foreach ($cards as $card) {
            $card = trim($card);
            if (empty($card)) continue;
            
            // Extract Q and A
            if (preg_match('/Q:\s*(.+?)(?=A:)/s', $card, $qMatch) && 
                preg_match('/A:\s*(.+?)(?=---|$)/s', $card, $aMatch)) {
                
                $flashcards[] = [
                    'question' => trim($qMatch[1]),
                    'answer' => trim($aMatch[1])
                ];
            }
        }
    }
    
    return $flashcards;
}
?>