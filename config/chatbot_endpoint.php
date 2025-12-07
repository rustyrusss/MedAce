<?php
/**
 * Chatbot Endpoint - Complete Working Version
 * Handles: Chat, Progress Analysis, Study Tips, Flashcards, Modules
 */

// Disable error display, enable logging
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Send JSON response and exit
 */
function sendJson($data, $code = 200) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    die(json_encode($data));
}

// Security check - must be logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendJson(['error' => 'Unauthorized - Please log in'], 403);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// Get and parse input
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    sendJson(['error' => 'Invalid JSON'], 400);
}

$studentId = $_SESSION['user_id'];
$action = $input['action'] ?? 'chat';

try {
    // Load database connection
    require_once __DIR__ . '/db_conn.php';
    
    // Load OpenAI API key from multiple sources
    $apiKey = loadApiKey();
    
    // ============================================
    // HANDLE DIFFERENT ACTIONS
    // ============================================
    
    switch ($action) {
        
        // GET MODULES LIST
        case 'get_modules':
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
        
        // GET PROGRESS DATA
        case 'get_progress':
            $progressData = getStudentProgress($conn, $studentId);
            sendJson([
                'success' => true,
                'progress' => $progressData
            ]);
            break;
        
        // GET DAILY TIP
        case 'get_daily_tip':
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
            break;
        
        // ANALYZE PROGRESS (AI-powered)
        case 'analyze_progress':
            $message = $input['message'] ?? 'Analyze my learning progress and give me personalized recommendations.';
            $result = analyzeProgressWithAI($conn, $studentId, $apiKey, $message);
            sendJson($result);
            break;
        
        // GET STUDY TIPS (AI-powered)
        case 'get_study_tips':
            $message = $input['message'] ?? 'Give me 5 personalized study tips based on my progress.';
            $result = getStudyTipsWithAI($conn, $studentId, $apiKey, $message);
            sendJson($result);
            break;
        
        // CHAT (general AI chat)
        case 'chat':
            $message = trim($input['message'] ?? '');
            
            if (empty($message)) {
                sendJson(['error' => 'Message is required'], 400);
            }
            
            // Detect if this is a progress analysis request
            if (preg_match('/(analyze|progress|insight|recommend|Total=|Completed=)/i', $message)) {
                $result = analyzeProgressWithAI($conn, $studentId, $apiKey, $message);
            }
            // Detect if this is a study tips request
            elseif (preg_match('/(tip|tips|study|advice|help me study)/i', $message)) {
                $result = getStudyTipsWithAI($conn, $studentId, $apiKey, $message);
            }
            // General chat
            else {
                $result = handleGeneralChat($conn, $studentId, $apiKey, $message);
            }
            
            sendJson($result);
            break;
        
        default:
            sendJson(['error' => 'Invalid action: ' . $action], 400);
    }
    
} catch (PDOException $e) {
    error_log("Database error in chatbot_endpoint: " . $e->getMessage());
    sendJson(['error' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Server error in chatbot_endpoint: " . $e->getMessage());
    sendJson(['error' => 'Server error: ' . $e->getMessage()], 500);
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Load OpenAI API key from multiple sources
 */
function loadApiKey() {
    $apiKey = null;
    
    // Try env.php first
    if (file_exists(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
        if (defined('OPENAI_API_KEY')) {
            $apiKey = OPENAI_API_KEY;
        }
    }
    
    // Try environment variables
    if (!$apiKey) {
        $apiKey = getenv("OPENAI_API_KEY") ?: ($_ENV["OPENAI_API_KEY"] ?? ($_SERVER["OPENAI_API_KEY"] ?? null));
    }
    
    // Try .env file
    if (!$apiKey && file_exists(__DIR__ . '/../.env')) {
        $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if ($k === 'OPENAI_API_KEY' || $k === 'API_KEY') {
                    $apiKey = $v;
                    break;
                }
            }
        }
    }
    
    return $apiKey;
}

/**
 * Get comprehensive student progress data
 */
function getStudentProgress($conn, $studentId) {
    $data = [
        'modules' => [
            'total_available' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'pending' => 0
        ],
        'quizzes' => [
            'total_available' => 0,
            'passed' => 0,
            'failed' => 0,
            'pending' => 0
        ],
        'overall_percent' => 0,
        'total_items' => 0,
        'completed_items' => 0
    ];
    
    try {
        // Get module stats
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT m.id) as total,
                SUM(CASE WHEN sp.status = 'Completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN sp.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
            FROM modules m
            LEFT JOIN student_progress sp ON m.id = sp.module_id AND sp.student_id = ?
        ");
        $stmt->execute([$studentId]);
        $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['modules']['total_available'] = (int)$moduleStats['total'];
        $data['modules']['completed'] = (int)$moduleStats['completed'];
        $data['modules']['in_progress'] = (int)$moduleStats['in_progress'];
        $data['modules']['pending'] = $data['modules']['total_available'] - $data['modules']['completed'] - $data['modules']['in_progress'];
        
        // Get quiz stats
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT q.id) as total,
                SUM(CASE WHEN qa.status = 'Passed' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN qa.status = 'Failed' THEN 1 ELSE 0 END) as failed
            FROM quizzes q
            LEFT JOIN (
                SELECT quiz_id, student_id, status, MAX(score) as score
                FROM quiz_attempts
                WHERE student_id = ?
                GROUP BY quiz_id
            ) qa ON q.id = qa.quiz_id
        ");
        $stmt->execute([$studentId]);
        $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['quizzes']['total_available'] = (int)$quizStats['total'];
        $data['quizzes']['passed'] = (int)$quizStats['passed'];
        $data['quizzes']['failed'] = (int)$quizStats['failed'];
        $data['quizzes']['pending'] = $data['quizzes']['total_available'] - $data['quizzes']['passed'] - $data['quizzes']['failed'];
        
        // Calculate overall progress
        $data['total_items'] = $data['modules']['total_available'] + $data['quizzes']['total_available'];
        $data['completed_items'] = $data['modules']['completed'] + $data['quizzes']['passed'];
        $data['overall_percent'] = $data['total_items'] > 0 ? round(($data['completed_items'] / $data['total_items']) * 100) : 0;
        
    } catch (Exception $e) {
        error_log("Progress data error: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Analyze progress with AI
 */
function analyzeProgressWithAI($conn, $studentId, $apiKey, $message) {
    // Get progress data
    $progressData = getStudentProgress($conn, $studentId);
    $modules = $progressData['modules'];
    $quizzes = $progressData['quizzes'];
    
    // Get student name
    $stmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
    $stmt->execute([$studentId]);
    $studentName = $stmt->fetchColumn() ?: 'Student';
    
    // Build detailed analysis context
    $context = "Analyze this nursing student's ({$studentName}) learning progress:\n\n";
    $context .= "**Overall Statistics:**\n";
    $context .= "- Overall Completion: {$progressData['overall_percent']}%\n";
    $context .= "- Total Items: {$progressData['total_items']} (Completed: {$progressData['completed_items']})\n\n";
    
    $context .= "**Module Progress:**\n";
    $context .= "- Total: {$modules['total_available']}\n";
    $context .= "- Completed: {$modules['completed']}\n";
    $context .= "- In Progress: {$modules['in_progress']}\n";
    $context .= "- Pending: {$modules['pending']}\n\n";
    
    $context .= "**Quiz Performance:**\n";
    $context .= "- Total: {$quizzes['total_available']}\n";
    $context .= "- Passed: {$quizzes['passed']}\n";
    $context .= "- Failed: {$quizzes['failed']}\n";
    $context .= "- Pending: {$quizzes['pending']}\n\n";
    
    $context .= "User request: {$message}\n\n";
    $context .= "Provide a comprehensive analysis with:\n";
    $context .= "1. **Strengths** - What they're doing well\n";
    $context .= "2. **Areas for Improvement** - Specific weaknesses\n";
    $context .= "3. **Recommendations** - 3-5 actionable next steps\n";
    $context .= "4. **Encouragement** - Motivating message\n\n";
    $context .= "Use the actual numbers. Be specific, encouraging, and conversational.";
    
    $systemPrompt = "You are MedAce Assistant, an expert nursing education AI. Provide detailed, personalized progress analysis based on actual student data. Use bold headers (**Header:**) and bullet points. Be encouraging and specific. Keep under 300 words.";
    
    // Call AI or return fallback
    if (empty($apiKey)) {
        return [
            'success' => true,
            'reply' => generateProgressFallback($progressData, $studentName),
            'progress_data' => $progressData
        ];
    }
    
    $response = callOpenAI($apiKey, $systemPrompt, $context);
    
    return [
        'success' => true,
        'reply' => $response,
        'analysis' => $response,
        'progress_data' => $progressData
    ];
}

/**
 * Get study tips with AI
 */
function getStudyTipsWithAI($conn, $studentId, $apiKey, $message) {
    // Get progress data
    $progressData = getStudentProgress($conn, $studentId);
    $modules = $progressData['modules'];
    $quizzes = $progressData['quizzes'];
    
    // Build context
    $context = "Generate personalized study tips for this nursing student:\n\n";
    $context .= "**Progress Data:**\n";
    $context .= "- Overall Progress: {$progressData['overall_percent']}%\n";
    $context .= "- Modules: {$modules['completed']}/{$modules['total_available']} completed\n";
    $context .= "- Quizzes: {$quizzes['passed']} passed, {$quizzes['failed']} failed\n\n";
    $context .= "User request: {$message}\n\n";
    $context .= "Provide 5 specific, actionable study tips based on their progress. Be encouraging and practical.";
    
    $systemPrompt = "You are MedAce Assistant, an expert nursing education AI. Generate personalized study tips based on student progress. Be specific and actionable. Format with numbered points. Keep under 250 words.";
    
    // Call AI or return fallback
    if (empty($apiKey)) {
        return [
            'success' => true,
            'reply' => generateStudyTipsFallback($progressData),
            'progress_summary' => [
                'overall_percent' => $progressData['overall_percent'],
                'avg_score' => $quizzes['avg_score']
            ]
        ];
    }
    
    $response = callOpenAI($apiKey, $systemPrompt, $context);
    
    return [
        'success' => true,
        'reply' => $response,
        'tips' => $response,
        'progress_summary' => [
            'overall_percent' => $progressData['overall_percent']
        ]
    ];
}

/**
 * Handle general chat
 */
function handleGeneralChat($conn, $studentId, $apiKey, $message) {
    // Get progress for context
    $progressData = getStudentProgress($conn, $studentId);
    
    $context = "Student Progress: {$progressData['overall_percent']}% overall, ";
    $context .= "{$progressData['modules']['completed']} modules completed, ";
    $context .= "{$progressData['quizzes']['passed']} quizzes passed\n\n";
    $context .= "User message: {$message}";
    
    $systemPrompt = "You are MedAce Assistant, a helpful nursing education AI. Answer questions about nursing topics, study strategies, and learning techniques. Be concise (under 150 words), helpful, and encouraging.";
    
    // Call AI or return fallback
    if (empty($apiKey)) {
        return [
            'success' => true,
            'reply' => "I'm here to help with your nursing studies! I can analyze your progress, provide study tips, or answer questions. **Note:** For AI-powered responses, configure the OpenAI API key in `/config/env.php`. What would you like to know?"
        ];
    }
    
    $response = callOpenAI($apiKey, $systemPrompt, $context);
    
    return [
        'success' => true,
        'reply' => $response
    ];
}

/**
 * Call OpenAI API
 */
function callOpenAI($apiKey, $systemPrompt, $userMessage) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'max_tokens' => 800,
        'temperature' => 0.8
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log errors
    if ($httpCode !== 200) {
        error_log("OpenAI API Error - HTTP {$httpCode}: {$response}");
        return "I apologize, but I'm having trouble connecting to the AI service right now. Please try again in a moment.";
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    error_log("OpenAI API unexpected response: " . json_encode($result));
    return "I apologize, but I couldn't generate a response. Please try again.";
}

/**
 * Generate progress analysis fallback (when AI unavailable)
 */
function generateProgressFallback($progressData, $studentName) {
    $modules = $progressData['modules'];
    $quizzes = $progressData['quizzes'];
    $overall = $progressData['overall_percent'];
    
    $response = "Hi {$studentName}! Here's your progress analysis:\n\n";
    $response .= "**Overall Progress: {$overall}%**\n\n";
    
    $response .= "**📚 Modules:**\n";
    $response .= "• Completed: {$modules['completed']}/{$modules['total_available']}\n";
    $response .= "• In Progress: {$modules['in_progress']}\n";
    $response .= "• Pending: {$modules['pending']}\n\n";
    
    $response .= "**📝 Quizzes:**\n";
    $response .= "• Passed: {$quizzes['passed']}\n";
    $response .= "• Failed: {$quizzes['failed']}\n\n";
    
    if ($overall >= 70) {
        $response .= "**Great progress!** You're on track. ";
    } elseif ($overall >= 40) {
        $response .= "**Good effort!** Keep building momentum. ";
    } else {
        $response .= "**Let's build momentum!** ";
    }
    
    if ($quizzes['failed'] > 0) {
        $response .= "Consider reviewing topics from failed quizzes. ";
    }
    
    if ($modules['in_progress'] > 0) {
        $response .= "Focus on completing your in-progress modules.\n\n";
    }
    
    $response .= "\n**Note:** For AI-powered personalized analysis, configure OpenAI API key in `/config/env.php`.";
    
    return $response;
}

/**
 * Generate study tips fallback (when AI unavailable)
 */
function generateStudyTipsFallback($progressData) {
    $quizzes = $progressData['quizzes'];
    $overall = $progressData['overall_percent'];
    
    $response = "Here are personalized study tips based on your progress:\n\n";
    
    if ($overall < 50) {
        $response .= "1. **Active Recall**: Test yourself regularly instead of just re-reading\n";
        $response .= "2. **Spaced Repetition**: Review material at increasing intervals\n";
        $response .= "3. **Practice Questions**: Focus heavily on practice problems\n";
        $response .= "4. **Study Groups**: Discuss difficult concepts with peers\n";
        $response .= "5. **Break It Down**: Study in 25-minute focused sessions\n";
    } else {
        $response .= "1. **Clinical Application**: Connect concepts to real scenarios\n";
        $response .= "2. **Teach Others**: Explain concepts to solidify understanding\n";
        $response .= "3. **Advanced Practice**: Challenge yourself with complex questions\n";
        $response .= "4. **Integration**: Link new topics with previously learned material\n";
        $response .= "5. **Review Weak Areas**: Target any remaining gaps\n";
    }
    
    $response .= "\n**Note:** For personalized AI-powered tips, configure OpenAI API key in `/config/env.php`.";
    
    return $response;
}