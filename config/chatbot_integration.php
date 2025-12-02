<?php
/**
 * Chatbot Integration - Simplified & Fixed
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

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    sendJson(['error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// Get input
$input = json_decode(file_get_contents("php://input"), true);
if (!isset($input["message"]) || empty(trim($input["message"]))) {
    sendJson(['error' => 'Missing message'], 400);
}

$userMessage = trim($input["message"]);

try {
    // Load dotenv
    if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
        require_once __DIR__ . "/../vendor/autoload.php";
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
            $dotenv->safeLoad();
        }
    }
    
    // Get API key
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
    
    if (!$apiKey) {
        sendJson(['error' => 'API key not found'], 500);
    }
    
    // Get student name
    $studentName = "";
    try {
        require_once __DIR__ . '/db_conn.php';
        $stmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $studentName = $user['firstname'];
    } catch (Exception $e) {}
    
    // Build request
    $payload = [
        "model" => "gpt-3.5-turbo",  // Changed to more stable model
        "messages" => [
            [
                "role" => "system", 
                "content" => "You are MedAce AI Assistant, a helpful nursing tutor. Be encouraging, concise (under 150 words), and use nursing terminology appropriately." . ($studentName ? " Student's name is $studentName." : "")
            ],
            [
                "role" => "user", 
                "content" => $userMessage
            ]
        ],
        "max_tokens" => 500  // Use max_tokens for gpt-3.5-turbo
    ];
    
    // Call OpenAI
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
        sendJson(['error' => 'Empty response'], 500);
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        sendJson(['error' => 'Invalid JSON response'], 500);
    }
    
    // Handle errors
    if (isset($data['error'])) {
        sendJson(['error' => $data['error']['message'] ?? 'OpenAI error'], $httpCode);
    }
    
    // Get content
    if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
        sendJson(['error' => 'No choices in response', 'debug' => array_keys($data)], 500);
    }
    
    $choice = $data['choices'][0];
    
    if (!isset($choice['message']) || !isset($choice['message']['content'])) {
        sendJson(['error' => 'No content in choice', 'debug' => array_keys($choice)], 500);
    }
    
    $content = $choice['message']['content'];
    $content = trim(strip_tags($content));
    
    if (empty($content)) {
        sendJson(['error' => 'Empty content'], 500);
    }
    
    sendJson(['reply' => $content]);
    
} catch (Exception $e) {
    sendJson(['error' => $e->getMessage()], 500);
}
?>