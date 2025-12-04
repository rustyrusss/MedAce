<?php
/**
 * TEST SCRIPT - Check chatbot_integration.php
 * Save as: /config/test_integration.php
 * Access: http://yoursite.com/config/test_integration.php
 */

session_start();

// Force login for testing (REMOVE THIS AFTER TESTING)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 10; // Your student ID
    $_SESSION['role'] = 'student';
}

echo "<h1>Testing chatbot_integration.php</h1>";
echo "<hr>";

// Test 1: Check if file exists
echo "<h2>Test 1: File Existence</h2>";
if (file_exists(__DIR__ . '/chatbot_integration.php')) {
    echo "✅ chatbot_integration.php EXISTS<br>";
} else {
    echo "❌ chatbot_integration.php NOT FOUND<br>";
    die();
}

// Test 2: Check API key
echo "<h2>Test 2: API Key Check</h2>";

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
    echo "Loading from .env file...<br>";
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

if ($apiKey) {
    echo "✅ API Key found: " . substr($apiKey, 0, 10) . "...<br>";
} else {
    echo "❌ API Key NOT FOUND<br>";
    echo "Check your .env file or environment variables<br>";
}

// Test 3: Test get_modules action
echo "<h2>Test 3: Get Modules</h2>";

$ch = curl_init('http://localhost/medace/config/chatbot_integration.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Cookie: ' . session_name() . '=' . session_id()
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'action' => 'get_modules'
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ get_modules works!<br>";
        echo "Modules found: " . count($data['modules'] ?? []) . "<br>";
    }
} else {
    echo "❌ get_modules FAILED<br>";
}

// Test 4: Test get_module_content action
echo "<h2>Test 4: Get Module Content (ID=10)</h2>";

$ch = curl_init('http://localhost/medace/config/chatbot_integration.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Cookie: ' . session_name() . '=' . session_id()
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'action' => 'get_module_content',
        'module_id' => 10
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ get_module_content works!<br>";
    }
} else {
    echo "❌ get_module_content FAILED<br>";
}

// Test 5: Check PHP errors
echo "<h2>Test 5: PHP Error Log</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "Error log: $errorLog<br>";
    $errors = file_get_contents($errorLog);
    $lastErrors = array_slice(explode("\n", $errors), -20);
    echo "<pre>" . htmlspecialchars(implode("\n", $lastErrors)) . "</pre>";
} else {
    echo "No error log found or configured<br>";
}

echo "<hr>";
echo "<p>Test complete!</p>";
?>