<?php
/**
 * Debug Chat API Proxy - Temporary debug version
 * DELETE THIS AND USE THE ORIGINAL AFTER FIXING
 */

// Start session for security check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables
require_once __DIR__ . '/../config/env.php';

// Set JSON header
header('Content-Type: application/json');

// DEBUG: Log what we're getting
error_log("=== CHAT API DEBUG ===");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));

// Security check - only allow authenticated students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit();
}

// Validate required fields
if (!isset($data['messages']) || !isset($data['model']) || !isset($data['max_tokens'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// DEBUG: Try all methods to get API key
error_log("Trying to get API key...");

$apiKey = null;
$method = '';

// Method 1: env() function
if (function_exists('env')) {
    $apiKey = env('ANTHROPIC_API_KEY');
    if ($apiKey) {
        $method = 'env() function';
        error_log("Found via env() function");
    }
}

// Method 2: getenv()
if (!$apiKey) {
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if ($apiKey && $apiKey !== false) {
        $method = 'getenv()';
        error_log("Found via getenv()");
    } else {
        $apiKey = null;
    }
}

// Method 3: $_ENV
if (!$apiKey && isset($_ENV['ANTHROPIC_API_KEY'])) {
    $apiKey = $_ENV['ANTHROPIC_API_KEY'];
    $method = '$_ENV';
    error_log("Found via \$_ENV");
}

// Method 4: $_SERVER
if (!$apiKey && isset($_SERVER['ANTHROPIC_API_KEY'])) {
    $apiKey = $_SERVER['ANTHROPIC_API_KEY'];
    $method = '$_SERVER';
    error_log("Found via \$_SERVER");
}

// DEBUG: Log what we found
if ($apiKey) {
    $masked = substr($apiKey, 0, 15) . '...' . substr($apiKey, -10);
    error_log("API Key found via $method: $masked");
    error_log("API Key length: " . strlen($apiKey));
} else {
    error_log("API Key NOT FOUND!");
    error_log("Available \$_ENV keys: " . implode(', ', array_keys($_ENV)));
    error_log("Available getenv(): " . (getenv('ANTHROPIC_API_KEY') ? 'yes' : 'no'));
}

$apiUrl = getenv('ANTHROPIC_API_URL') ?: 'https://api.anthropic.com/v1/messages';
error_log("API URL: $apiUrl");

if (!$apiKey) {
    http_response_code(500);
    $envKeys = array_keys($_ENV);
    echo json_encode([
        'error' => 'API key not configured. Check your .env file.',
        'debug' => [
            'env_keys' => $envKeys,
            'getenv_test' => getenv('ANTHROPIC_API_KEY') ? 'has value' : 'empty'
        ]
    ]);
    exit();
}

// Prepare the API request
$postData = [
    'model' => $data['model'],
    'max_tokens' => intval($data['max_tokens']),
    'messages' => $data['messages']
];

error_log("Sending request to Anthropic API...");

// Initialize cURL
$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ],
    CURLOPT_TIMEOUT => 30
]);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

error_log("API Response Code: $httpCode");

// Handle errors
if ($curlError) {
    error_log("cURL Error: $curlError");
    http_response_code(500);
    echo json_encode(['error' => 'Connection error: ' . $curlError]);
    exit();
}

// Return the response with appropriate status code
http_response_code($httpCode);
echo $response;

error_log("=== END DEBUG ===");
?>