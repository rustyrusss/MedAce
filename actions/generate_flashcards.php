<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log them

// Custom error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // REQUIRED FILES
    if (!file_exists(__DIR__ . '/../config/db_conn.php')) {
        throw new Exception('Database configuration file not found');
    }
    require_once __DIR__ . '/../config/db_conn.php';
    
    if (!file_exists(__DIR__ . '/../config/chatbot.php')) {
        throw new Exception('Chatbot configuration file not found');
    }
    require_once __DIR__ . '/../config/chatbot.php';
    
    // Try to load env.php if it exists
    if (file_exists(__DIR__ . '/../config/env.php')) {
        require_once __DIR__ . '/../config/env.php';
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated. Please log in.');
    }

    // Get the request body
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No input data received');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    $topic = trim($data['topic'] ?? '');
    $numQuestions = intval($data['num_questions'] ?? 5);

    if (empty($topic)) {
        throw new Exception('Topic is required');
    }

    // Limit questions (3–10)
    $numQuestions = max(3, min(10, $numQuestions));

    // Load API key from multiple sources
    $apiKey = null;
    
    // Try environment variable first
    $apiKey = getenv('OPENAI_API_KEY');
    
    // Try $_ENV array
    if (empty($apiKey) && isset($_ENV['OPENAI_API_KEY'])) {
        $apiKey = $_ENV['OPENAI_API_KEY'];
    }
    
    // Try $_SERVER array
    if (empty($apiKey) && isset($_SERVER['OPENAI_API_KEY'])) {
        $apiKey = $_SERVER['OPENAI_API_KEY'];
    }

    if (empty($apiKey)) {
        throw new Exception('OpenAI API key not configured. Please set OPENAI_API_KEY in your environment or .env file.');
    }

    // Validate API key format
    if (!preg_match('/^sk-[a-zA-Z0-9]{48}$/', $apiKey) && !preg_match('/^sk-proj-[a-zA-Z0-9_-]+$/', $apiKey)) {
        throw new Exception('API key format appears invalid');
    }

    // PROMPT FOR AI
    $prompt = "Generate exactly {$numQuestions} nursing/medical flashcard questions about \"{$topic}\". 

Return ONLY a valid JSON array with this exact structure (no markdown, no extra text):
[
    {
        \"question\": \"The question text here?\",
        \"question_type\": \"multiple_choice\",
        \"choices\": [
            {\"text\": \"Option A\", \"is_correct\": false},
            {\"text\": \"Option B\", \"is_correct\": true},
            {\"text\": \"Option C\", \"is_correct\": false},
            {\"text\": \"Option D\", \"is_correct\": false}
        ],
        \"correct_answer\": \"Option B\",
        \"explanation\": \"Brief explanation why this is correct\"
    }
]

Rules:
- Each question must have exactly 4 choices
- Only ONE choice should have is_correct: true
- Make questions educational and relevant to nursing students
- Include a mix of difficulty levels
- correct_answer MUST match the correct choice text exactly
- Return ONLY the JSON array.";

    // --- CALL OPENAI API ---
    $ch = curl_init('https://api.openai.com/v1/chat/completions');

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a nursing education expert. Always respond with raw JSON only.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.7
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("API request failed: $curlError");
    }

    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $errorMsg = $err['error']['message'] ?? 'Unknown API error';
        throw new Exception("OpenAI API Error ($httpCode): $errorMsg");
    }

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse API response: " . json_last_error_msg());
    }

    $content = trim($responseData['choices'][0]['message']['content'] ?? '');
    
    if (empty($content)) {
        throw new Exception("Empty response from API");
    }

    // Remove accidental markdown code blocks
    $content = preg_replace('/^```json\s*|\s*```$/i', '', $content);
    $content = trim($content);

    // Decode the JSON
    $flashcards = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON parsing failed. Content: $content");
        throw new Exception("JSON parsing failed: " . json_last_error_msg());
    }

    if (!is_array($flashcards) || empty($flashcards)) {
        throw new Exception("Invalid flashcard structure returned from API");
    }

    // VALIDATE FLASHCARDS
    $validatedFlashcards = [];

    foreach ($flashcards as $index => $card) {
        if (!isset($card['question']) || !isset($card['choices'])) {
            continue;
        }

        $validatedCard = [
            'id' => $index + 1,
            'question' => $card['question'],
            'question_type' => $card['question_type'] ?? 'multiple_choice',
            'points' => 1,
            'choices' => [],
            'correct_answer' => $card['correct_answer'] ?? '',
            'correct_answers' => [],
            'explanation' => $card['explanation'] ?? ''
        ];

        foreach ($card['choices'] as $choiceIndex => $choice) {
            $validatedCard['choices'][] = [
                'id' => $choiceIndex + 1,
                'text' => $choice['text'] ?? '',
                'is_correct' => (bool)($choice['is_correct'] ?? false)
            ];

            if ($choice['is_correct'] ?? false) {
                $validatedCard['correct_answers'][] = $choice['text'];
                if (empty($validatedCard['correct_answer'])) {
                    $validatedCard['correct_answer'] = $choice['text'];
                }
            }
        }

        if (count($validatedCard['choices']) >= 2) {
            $validatedFlashcards[] = $validatedCard;
        }
    }

    if (empty($validatedFlashcards)) {
        throw new Exception("No valid flashcards could be generated. Please try a different topic.");
    }

    // SAVE TO SESSION
    $_SESSION['ai_flashcards'] = [
        'topic' => $topic,
        'cards' => $validatedFlashcards,
        'generated_at' => time(),
        'total' => count($validatedFlashcards)
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Flashcards generated successfully!',
        'topic' => $topic,
        'count' => count($validatedFlashcards),
        'redirect' => '../member/flashcards_quiz.php?source=ai&topic=' . urlencode($topic)
    ]);

} catch (Exception $e) {
    error_log("Flashcard generation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}