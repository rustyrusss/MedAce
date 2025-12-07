<?php
session_start();
require_once '../config/db_conn.php';
require_once '../config/env.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// Determine the source of flashcards
$source = $_GET['source'] ?? '';
$quizId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0);
$moduleId = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$aiTopic = $_GET['topic'] ?? '';

$sourceTitle = '';
$sourceDescription = '';
$sourceType = '';
$flashcards = [];
$isAIGenerated = false;

// Check if this is an AI-generated flashcard session
if ($source === 'ai' && isset($_SESSION['ai_flashcards'])) {
    $aiData = $_SESSION['ai_flashcards'];
    
    // Verify the topic matches (security check)
    if (!empty($aiTopic) && $aiData['topic'] === $aiTopic) {
        $isAIGenerated = true;
        $sourceTitle = htmlspecialchars($aiData['topic']);
        $sourceDescription = 'AI-generated flashcards based on your topic';
        $sourceType = 'ai';
        $flashcards = $aiData['cards'];
        
        // Shuffle for variety
        shuffle($flashcards);
    } else {
        // Topic mismatch or expired - redirect to dashboard
        unset($_SESSION['ai_flashcards']);
        header("Location: dashboard.php?error=flashcard_expired");
        exit();
    }
} elseif ($quizId > 0 || $moduleId > 0) {
    // Original database-based flashcard logic
    if ($quizId > 0) {
        // Direct quiz access
        $stmt = $conn->prepare("SELECT id, title, description, module_id FROM quizzes WHERE id = ?");
        $stmt->execute([$quizId]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz) {
            header("Location: quizzes.php");
            exit();
        }
        
        $sourceTitle = htmlspecialchars($quiz['title']);
        $sourceDescription = htmlspecialchars($quiz['description'] ?? '');
        $sourceType = 'quiz';
        
        // Fetch questions directly
        $stmt = $conn->prepare("
            SELECT id, question_text, question_type, points 
            FROM questions 
            WHERE quiz_id = ? 
            AND question_type IN ('multiple_choice', 'true_false')
        ");
        $stmt->execute([$quizId]);
        $allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Module access (from chatbot)
        $stmt = $conn->prepare("SELECT id, title, description FROM modules WHERE id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$module) {
            header("Location: dashboard.php");
            exit();
        }
        
        $sourceTitle = htmlspecialchars($module['title']);
        $sourceDescription = htmlspecialchars($module['description'] ?? '');
        $sourceType = 'module';
        
        // Fetch questions from all quizzes in this module
        $stmt = $conn->prepare("
            SELECT q.id, q.question_text, q.question_type, q.points, qz.title AS quiz_title
            FROM questions q
            JOIN quizzes qz ON q.quiz_id = qz.id
            WHERE qz.module_id = ?
            AND q.question_type IN ('multiple_choice', 'true_false')
        ");
        $stmt->execute([$moduleId]);
        $allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Shuffle and select questions
    shuffle($allQuestions);
    $selectedQuestions = array_slice($allQuestions, 0, 10);

    // Fetch answers for selected questions
    foreach ($selectedQuestions as $question) {
        $stmt = $conn->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?");
        $stmt->execute([$question['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        shuffle($answers);
        
        $correctAnswers = [];
        $allChoices = [];
        foreach ($answers as $answer) {
            $allChoices[] = [
                'id' => $answer['id'],
                'text' => $answer['answer_text'],
                'is_correct' => (bool)$answer['is_correct']
            ];
            if ($answer['is_correct']) {
                $correctAnswers[] = $answer['answer_text'];
            }
        }
        
        $flashcards[] = [
            'id' => $question['id'],
            'question' => $question['question_text'],
            'question_type' => $question['question_type'],
            'points' => $question['points'] ?? 1,
            'choices' => $allChoices,
            'correct_answer' => count($correctAnswers) === 1 ? $correctAnswers[0] : implode(', ', $correctAnswers),
            'correct_answers' => $correctAnswers
        ];
    }
} else {
    // No valid source - redirect
    header("Location: dashboard.php");
    exit();
}

// Rephrase ALL questions using AI to make them look naturally generated
$rephrasingSuccess = false;
$debugInfo = [];

if (count($flashcards) > 0) {
    // Create cache key based on actual question IDs (more reliable)
    $questionIds = array_column($flashcards, 'id');
    sort($questionIds); // Sort to ensure consistent cache key
    $cacheKey = 'rephrased_v2_' . md5(implode('_', $questionIds));
    
    // Check cache with expiration (24 hours)
    $cacheExpiration = 86400; // 24 hours in seconds
    $cacheValid = false;
    
    if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_time'])) {
        $cacheTime = $_SESSION[$cacheKey . '_time'];
        if ((time() - $cacheTime) < $cacheExpiration) {
            $cacheValid = true;
            $rephrasedQuestions = $_SESSION[$cacheKey];
            $debugInfo['source'] = 'cache';
            $rephrasingSuccess = true;
        } else {
            // Cache expired, clear it
            unset($_SESSION[$cacheKey]);
            unset($_SESSION[$cacheKey . '_time']);
            $debugInfo['note'] = 'Cache expired, fetching new';
        }
    }
    
    if (!$cacheValid) {
        // Need to rephrase
        $questionsToRephrase = array_map(function($card) {
            return $card['question'];
        }, $flashcards);
        
        $debugInfo['original'] = $questionsToRephrase;
        
        // Call AI to rephrase questions
        $rephrasedQuestions = rephraseQuestionsWithAI($questionsToRephrase);
        
        $debugInfo['rephrased'] = $rephrasedQuestions;
        
        if ($rephrasedQuestions && count($rephrasedQuestions) === count($flashcards)) {
            // Cache the rephrased questions with timestamp
            $_SESSION[$cacheKey] = $rephrasedQuestions;
            $_SESSION[$cacheKey . '_time'] = time();
            $rephrasingSuccess = true;
            $debugInfo['source'] = 'api';
            $debugInfo['success'] = true;
        } else {
            $debugInfo['success'] = false;
            $debugInfo['error'] = 'Count mismatch or null response';
            error_log('Rephrasing failed on first attempt');
            
            // Retry once if rephrasing fails
            error_log('Retrying rephrasing...');
            sleep(1); // Brief delay before retry
            
            $rephrasedQuestions = rephraseQuestionsWithAI($questionsToRephrase);
            
            if ($rephrasedQuestions && count($rephrasedQuestions) === count($flashcards)) {
                $_SESSION[$cacheKey] = $rephrasedQuestions;
                $_SESSION[$cacheKey . '_time'] = time();
                $rephrasingSuccess = true;
                $debugInfo['source'] = 'api_retry';
                $debugInfo['success'] = true;
                error_log('Rephrasing succeeded on retry');
            } else {
                error_log('Rephrasing failed after retry - using original questions');
            }
        }
    }
    
    // Apply rephrased questions to flashcards
    if ($rephrasedQuestions && count($rephrasedQuestions) === count($flashcards)) {
        foreach ($flashcards as $index => $card) {
            $flashcards[$index]['original_question'] = $card['question'];
            $flashcards[$index]['question'] = $rephrasedQuestions[$index];
        }
    }
}

// Debug output (uncomment for testing)
// echo '<pre style="background: white; padding: 1rem; margin: 1rem; position: relative; z-index: 9999;">DEBUG INFO: ' . print_r($debugInfo, true) . '</pre>';

function rephraseQuestionsWithAI($questions) {
    global $openaiApiKey;
    
    if (empty($openaiApiKey)) {
        error_log('OpenAI API key not found in env.php');
        return null;
    }
    
    // Build question list with context
    $questionsText = "";
    foreach ($questions as $index => $question) {
        $questionsText .= ($index + 1) . ". " . $question . "\n";
    }
    
    try {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at rephrasing educational quiz questions. Your task is to rephrase questions in a natural, conversational way while:
1. Keeping the EXACT same meaning and testing the same concept
2. Changing sentence structure and word choices significantly
3. Making questions sound fresh and different from the original
4. Maintaining the same difficulty level
5. Keeping questions clear and unambiguous

CRITICAL: Respond with ONLY a valid JSON array of strings. No markdown, no code blocks, no explanations - just the JSON array.'
                ],
                [
                    'role' => 'user',
                    'content' => "Rephrase these quiz questions to make them sound completely different while keeping the exact same meaning:\n\n" . $questionsText . "\n\nIMPORTANT: Respond with ONLY a JSON array like this: [\"rephrased question 1\", \"rephrased question 2\", ...]"
                ]
            ],
            'temperature' => 0.9, // Higher temperature for more creative rephrasing
            'max_tokens' => 3000
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openaiApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30 // Add timeout
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) {
            error_log('cURL Error in rephrasing: ' . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if (isset($result['choices'][0]['message']['content'])) {
                $text = trim($result['choices'][0]['message']['content']);
                
                // Remove markdown code blocks if present
                $text = preg_replace('/```json\s*|\s*```|```/i', '', $text);
                $text = trim($text);
                
                // Parse JSON
                $rephrasedArray = json_decode($text, true);
                
                // Validate response
                if (is_array($rephrasedArray) && count($rephrasedArray) === count($questions)) {
                    // Additional validation: ensure all items are non-empty strings
                    $allValid = true;
                    foreach ($rephrasedArray as $rephrased) {
                        if (!is_string($rephrased) || trim($rephrased) === '') {
                            $allValid = false;
                            break;
                        }
                    }
                    
                    if ($allValid) {
                        error_log('Successfully rephrased ' . count($rephrasedArray) . ' questions');
                        return $rephrasedArray;
                    } else {
                        error_log('Rephrasing validation failed: empty or invalid strings found');
                    }
                } else {
                    error_log('JSON parsing failed or count mismatch. Expected: ' . count($questions) . ', Got: ' . (is_array($rephrasedArray) ? count($rephrasedArray) : 'not an array'));
                    error_log('Response text: ' . substr($text, 0, 500));
                }
            } else {
                error_log('No content in OpenAI response');
            }
        } else {
            error_log('OpenAI API error: HTTP ' . $httpCode);
            if ($response) {
                $errorData = json_decode($response, true);
                error_log('Error details: ' . ($errorData['error']['message'] ?? substr($response, 0, 500)));
            }
        }
    } catch (Exception $e) {
        error_log('AI rephrasing exception: ' . $e->getMessage());
    }
    
    return null;
}

$flashcardsJson = json_encode($flashcards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$totalQuestions = count($flashcards);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enumeration Quiz - <?= $sourceTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
        }
        .container { 
            width: 100%; 
            max-width: 900px; 
            margin: 0 auto; 
        }

        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .ai-badge i { font-size: 0.9rem; }
        
        .ai-badge.rephrased {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .empty-state {
            background: white; 
            border-radius: 1.5rem; 
            padding: 3rem 2rem;
            text-align: center; 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .empty-state i { 
            font-size: 4rem; 
            color: #d1d5db; 
            margin-bottom: 1.5rem; 
        }

        .quiz-container { display: block; }
        .quiz-header {
            background: white; 
            border-radius: 1.5rem 1.5rem 0 0;
            padding: 1.5rem 2rem; 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .quiz-source {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.4rem 1rem; 
            border-radius: 1.5rem;
            font-size: 0.8rem; 
            color: white; 
            font-weight: 600; 
            margin-bottom: 0.75rem;
        }
        .quiz-body {
            background: white; 
            padding: 2rem; 
            min-height: 400px;
            border-radius: 0 0 1.5rem 1.5rem;
        }

        .flashcard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border-radius: 1rem; 
            padding: 2rem;
            margin-bottom: 1.5rem; 
            min-height: 180px;
            display: flex; 
            flex-direction: column; 
            justify-content: center;
            position: relative;
        }
        .flashcard .question-number {
            position: absolute; 
            top: 1rem; 
            left: 1rem;
            background: rgba(255,255,255,0.25); 
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem; 
            font-size: 0.75rem; 
            font-weight: 600;
        }
        .flashcard .question-type {
            position: absolute; 
            top: 1rem; 
            right: 1rem;
            background: rgba(255,255,255,0.2); 
            padding: 0.3rem 0.85rem;
            border-radius: 1.5rem; 
            font-size: 0.7rem; 
            font-weight: 600; 
            text-transform: uppercase;
        }
        .flashcard h2 { 
            font-size: 1.25rem; 
            line-height: 1.6; 
            margin-top: 1rem; 
        }

        .answer-section {
            background: #f9fafb; 
            border-radius: 1rem;
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
        }
        .answer-section label {
            display: block; 
            font-weight: 600; 
            color: #374151; 
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        .answer-section label i {
            margin-right: 0.5rem;
            color: #667eea;
        }
        .answer-section textarea {
            width: 100%; 
            padding: 1rem; 
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem; 
            resize: vertical; 
            font-family: inherit;
            font-size: 0.95rem; 
            min-height: 120px;
            transition: all 0.2s;
        }
        .answer-section textarea:focus { 
            outline: none; 
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .answer-section textarea:disabled {
            background: #f3f4f6;
            cursor: not-allowed;
        }

        .explanation-box {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-top: 1rem;
            display: none;
        }
        .explanation-box.show { display: block; }
        .explanation-box h4 {
            color: #0369a1;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .explanation-box p { 
            color: #374151; 
            font-size: 0.95rem; 
            line-height: 1.6;
        }

        .buttons { 
            display: flex; 
            gap: 0.75rem; 
            flex-wrap: wrap; 
        }
        .btn {
            padding: 1rem 1.75rem; 
            border: none; 
            border-radius: 0.75rem;
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.2s;
            font-size: 0.95rem; 
            display: inline-flex; 
            align-items: center;
            justify-content: center; 
            gap: 0.5rem; 
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            flex: 1;
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); 
        }
        .btn-secondary { 
            background: #6b7280; 
            color: white; 
        }
        .btn-secondary:hover { 
            background: #4b5563; 
            transform: translateY(-2px);
        }
        .btn-reveal {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; 
            flex: 1;
        }
        .btn-reveal:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        .btn:disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
            transform: none !important; 
        }

        .progress-bar { 
            width: 100%; 
            height: 8px; 
            background: #e5e7eb; 
            border-radius: 1rem; 
            overflow: hidden; 
            margin: 1rem 0; 
        }
        .progress-fill { 
            height: 100%; 
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); 
            transition: width 0.3s ease; 
        }

        .feedback-box { 
            padding: 1.25rem; 
            border-radius: 0.75rem; 
            margin-bottom: 1rem; 
            display: none; 
        }
        .feedback-box.correct { 
            background: #ecfdf5; 
            border: 2px solid #10b981; 
            display: block; 
        }
        .feedback-box.incorrect { 
            background: #fef2f2; 
            border: 2px solid #ef4444; 
            display: block; 
        }
        .feedback-box h4 { 
            display: flex; 
            align-items: center; 
            gap: 0.5rem; 
            margin-bottom: 0.75rem; 
            font-size: 1rem; 
        }
        .feedback-box.correct h4 { color: #059669; }
        .feedback-box.incorrect h4 { color: #dc2626; }
        .feedback-box p { 
            color: #374151; 
            font-size: 0.95rem; 
            line-height: 1.6;
        }

        .results-screen {
            display: none; 
            background: white; 
            border-radius: 1.5rem;
            padding: 2.5rem 2rem; 
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .results-score {
            font-size: 4.5rem; 
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            background-clip: text; 
            margin: 1rem 0;
        }
        .results-stats { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 1rem; 
            margin: 2rem auto; 
            max-width: 400px; 
        }
        .stat-box { 
            background: #f9fafb; 
            border-radius: 1rem; 
            padding: 1.5rem; 
        }
        .stat-box .number { 
            font-size: 2.5rem; 
            font-weight: 700; 
        }
        .stat-box .label { 
            font-size: 0.85rem; 
            color: #6b7280; 
            margin-top: 0.5rem; 
        }
        .stat-correct .number { color: #10b981; }
        .stat-incorrect .number { color: #ef4444; }

        .saving-indicator {
            display: none; 
            align-items: center; 
            justify-content: center;
            gap: 0.5rem; 
            color: #6b7280; 
            font-size: 0.9rem; 
            margin-top: 1rem;
        }
        .saving-indicator.show { display: flex; }
        .saving-indicator .spinner {
            width: 18px; 
            height: 18px; 
            border: 2px solid #e5e7eb;
            border-top-color: #667eea; 
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .saved-badge {
            display: none; 
            align-items: center; 
            justify-content: center;
            gap: 0.5rem; 
            color: #10b981; 
            font-size: 0.9rem; 
            margin-top: 1rem;
        }
        .saved-badge.show { display: flex; }

        /* Responsive Design */
        @media (max-width: 768px) {
            body { padding: 0.75rem; }
            .container { max-width: 100%; }
            .quiz-header, .quiz-body { padding: 1.5rem; }
            .flashcard { 
                padding: 1.5rem; 
                min-height: 160px; 
            }
            .flashcard h2 { font-size: 1.1rem; }
            .flashcard .question-number {
                padding: 0.25rem 0.6rem;
                font-size: 0.7rem;
            }
            .flashcard .question-type {
                padding: 0.25rem 0.75rem;
                font-size: 0.65rem;
            }
            .answer-section { padding: 1.25rem; }
            .answer-section textarea { min-height: 100px; }
            .results-score { font-size: 3.5rem; }
            .stat-box .number { font-size: 2rem; }
        }

        @media (max-width: 640px) {
            body { padding: 0.5rem; }
            .quiz-header, .quiz-body { padding: 1.25rem; }
            .flashcard { 
                padding: 1.25rem; 
                min-height: 140px; 
            }
            .flashcard h2 { font-size: 1rem; }
            .buttons { 
                flex-direction: column; 
            }
            .btn { 
                width: 100%; 
                padding: 0.9rem 1.5rem;
            }
            .results-stats { 
                grid-template-columns: 1fr; 
                gap: 0.75rem;
                max-width: 300px;
            }
            .results-score { font-size: 3rem; }
            .answer-section textarea { 
                min-height: 90px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .flashcard { padding: 1rem; }
            .flashcard h2 { font-size: 0.95rem; }
            .answer-section { padding: 1rem; }
            .quiz-header h1 { font-size: 1.1rem !important; }
            .quiz-source { font-size: 0.75rem; }
            .btn { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($totalQuestions === 0): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h2 style="color: #374151; font-size: 1.5rem; margin-bottom: 0.5rem;">No Questions Available</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">
            <?php if ($isAIGenerated): ?>
                Failed to generate flashcards. Please try again with a different topic.
            <?php else: ?>
                There are no multiple choice or true/false questions available for flashcards.
            <?php endif; ?>
        </p>
        <a href="dashboard.php" class="btn btn-primary" style="display: inline-flex; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Go Back
        </a>
    </div>
    <?php else: ?>

    <!-- Quiz Container -->
    <div id="quizContainer" class="quiz-container">
        <div class="quiz-header">
            <?php if ($isAIGenerated): ?>
                <div class="ai-badge">
                    <i class="fas fa-magic"></i> AI Generated
                </div>
            <?php endif; ?>
            <?php if ($rephrasingSuccess): ?>
                <div class="ai-badge rephrased">
                    <i class="fas fa-sync-alt"></i> AI Rephrased Questions
                </div>
            <?php endif; ?>
            <span class="quiz-source">
                <i class="fas fa-layer-group"></i> <?= $totalQuestions ?> <?= $isAIGenerated ? 'AI' : 'Random' ?> Questions
            </span>
            <h1 style="font-size: 1.4rem; color: #1f2937; margin-bottom: 0.25rem;"><?= $sourceTitle ?></h1>
            <p style="color: #6b7280; font-size: 0.9rem;"><?= $sourceDescription ?></p>
            
            <div class="progress-bar">
                <div id="progressBar" class="progress-fill" style="width: 0%"></div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <p id="progressText" style="color: #6b7280; font-size: 0.85rem;">Question 1 of <?= $totalQuestions ?></p>
                <p id="scoreText" style="color: #667eea; font-size: 0.85rem; font-weight: 600;">Score: 0/0</p>
            </div>
        </div>

        <div class="quiz-body">
            <div id="questionCard" class="flashcard">
                <span class="question-number" id="questionNumber">#1</span>
                <span class="question-type" id="questionType">Enumeration</span>
                <h2 id="questionText"></h2>
            </div>

            <div class="answer-section">
                <label>
                    <i class="fas fa-pencil-alt"></i> Type your answer:
                </label>
                <textarea id="answerInput" placeholder="Type the correct answer here..."></textarea>
            </div>

            <div id="feedbackBox" class="feedback-box">
                <h4 id="feedbackTitle"></h4>
                <p id="feedbackText"></p>
            </div>

            <div id="explanationBox" class="explanation-box">
                <h4><i class="fas fa-info-circle"></i> Explanation</h4>
                <p id="explanationText"></p>
            </div>

            <div class="buttons" style="margin-top: 1rem;">
                <button id="checkBtn" onclick="checkAnswer()" class="btn btn-reveal">
                    <i class="fas fa-check"></i> Check Answer
                </button>
                <button id="nextBtn" onclick="nextQuestion()" class="btn btn-primary" style="display: none;">
                    Next Question <i class="fas fa-arrow-right"></i>
                </button>
                <button id="finishBtn" onclick="finishQuiz()" class="btn btn-primary" style="display: none;">
                    <i class="fas fa-flag-checkered"></i> See Results
                </button>
            </div>
        </div>
    </div>

    <!-- Results Screen -->
    <div id="resultsScreen" class="results-screen">
        <div style="margin-bottom: 1rem;">
            <i class="fas fa-trophy" style="font-size: 3.5rem; color: #fbbf24;"></i>
        </div>
        <h2 style="color: #374151; font-size: 1.5rem;">Quiz Complete!</h2>
        <div class="results-score" id="finalScore">0%</div>
        <p style="color: #6b7280; font-size: 0.95rem;">Your final score</p>
        
        <div class="results-stats">
            <div class="stat-box stat-correct">
                <div class="number" id="correctCount">0</div>
                <div class="label">Correct</div>
            </div>
            <div class="stat-box stat-incorrect">
                <div class="number" id="incorrectCount">0</div>
                <div class="label">Incorrect</div>
            </div>
        </div>

        <div class="saving-indicator" id="savingIndicator">
            <div class="spinner"></div>
            <span>Saving your results...</span>
        </div>

        <div class="saved-badge" id="savedBadge">
            <i class="fas fa-check-circle"></i>
            <span>Results saved!</span>
        </div>

        <div class="buttons" style="justify-content: center; gap: 1rem; flex-direction: column; max-width: 350px; margin: 1.5rem auto 0;">
            <?php if ($isAIGenerated): ?>
                <button onclick="generateNewFlashcards()" class="btn btn-reveal">
                    <i class="fas fa-magic"></i> Generate New Topic
                </button>
            <?php endif; ?>
            <button onclick="restartQuiz()" class="btn btn-primary">
                <i class="fas fa-redo"></i> Try Again
            </button>
            <a href="dashboard.php" class="btn btn-secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const flashcards = <?= $flashcardsJson ?>;
const totalQuestions = <?= $totalQuestions ?>;
const quizId = <?= $quizId ?: 'null' ?>;
const moduleId = <?= $moduleId ?: 'null' ?>;
const sourceTitle = "<?= addslashes($sourceTitle) ?>";
const sourceType = "<?= $sourceType ?>";
const isAIGenerated = <?= $isAIGenerated ? 'true' : 'false' ?>;

let currentIndex = 0;
let correctCount = 0;
let answeredCount = 0;
let hasAnswered = false;
let startTime = Date.now();

document.addEventListener('DOMContentLoaded', function() {
    if (totalQuestions > 0) {
        loadQuestion(0);
    }
});

function loadQuestion(index) {
    currentIndex = index;
    hasAnswered = false;
    const card = flashcards[index];
    
    document.getElementById('questionText').textContent = card.question;
    document.getElementById('questionNumber').textContent = `#${index + 1}`;
    
    document.getElementById('progressBar').style.width = (index / totalQuestions) * 100 + '%';
    document.getElementById('progressText').textContent = `Question ${index + 1} of ${totalQuestions}`;
    document.getElementById('scoreText').textContent = `Score: ${correctCount}/${answeredCount}`;
    
    document.getElementById('feedbackBox').className = 'feedback-box';
    document.getElementById('explanationBox').classList.remove('show');
    document.getElementById('answerInput').value = '';
    document.getElementById('answerInput').disabled = false;
    document.getElementById('checkBtn').style.display = 'flex';
    document.getElementById('nextBtn').style.display = 'none';
    document.getElementById('finishBtn').style.display = 'none';
}

function checkAnswer() {
    if (hasAnswered) return;
    hasAnswered = true;
    answeredCount++;
    
    const card = flashcards[currentIndex];
    const userAnswer = document.getElementById('answerInput').value.trim().toLowerCase();
    const correctAnswer = card.correct_answer.toLowerCase();
    
    document.getElementById('answerInput').disabled = true;
    document.getElementById('checkBtn').style.display = 'none';
    
    const isCorrect = userAnswer.length > 0 && (
        userAnswer.includes(correctAnswer) || 
        correctAnswer.includes(userAnswer) || 
        levenshteinDistance(userAnswer, correctAnswer) < Math.min(5, correctAnswer.length / 2)
    );
    
    if (isCorrect) {
        correctCount++;
        showFeedback(true, 'Correct! 🎉', card.correct_answer);
    } else {
        showFeedback(false, 'Not quite right', `The correct answer is: ${card.correct_answer}`);
    }
    
    // Show explanation if available
    if (card.explanation) {
        document.getElementById('explanationText').textContent = card.explanation;
        document.getElementById('explanationBox').classList.add('show');
    }
    
    document.getElementById('scoreText').textContent = `Score: ${correctCount}/${answeredCount}`;
    showNextButton();
}

function showFeedback(isCorrect, title, text) {
    const box = document.getElementById('feedbackBox');
    box.className = 'feedback-box ' + (isCorrect ? 'correct' : 'incorrect');
    document.getElementById('feedbackTitle').innerHTML = `<i class="fas fa-${isCorrect ? 'check-circle' : 'times-circle'}"></i> ${title}`;
    document.getElementById('feedbackText').textContent = text;
}

function showNextButton() {
    if (currentIndex < totalQuestions - 1) {
        document.getElementById('nextBtn').style.display = 'flex';
    } else {
        document.getElementById('finishBtn').style.display = 'flex';
    }
}

function nextQuestion() {
    if (currentIndex < totalQuestions - 1) loadQuestion(currentIndex + 1);
}

async function finishQuiz() {
    const percentage = Math.round((correctCount / totalQuestions) * 100);
    const timeSpent = Math.round((Date.now() - startTime) / 1000);
    
    document.getElementById('finalScore').textContent = percentage + '%';
    document.getElementById('correctCount').textContent = correctCount;
    document.getElementById('incorrectCount').textContent = totalQuestions - correctCount;
    
    document.getElementById('quizContainer').style.display = 'none';
    document.getElementById('resultsScreen').style.display = 'block';
    
    // Show saving indicator
    document.getElementById('savingIndicator').classList.add('show');

    const payload = {
        quiz_id: quizId,
        module_id: moduleId,
        source_title: sourceTitle,
        source_type: sourceType,
        is_ai_generated: isAIGenerated,
        total_questions: totalQuestions,
        correct_answers: correctCount,
        incorrect_answers: totalQuestions - correctCount,
        score_percentage: percentage,
        mode: 'enumeration',
        time_spent_seconds: timeSpent
    };

    try {
        const response = await fetch('../actions/save_flashcard_attempt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        console.log('Save result:', result);
        
        document.getElementById('savingIndicator').classList.remove('show');
        
        if (result.success) {
            document.getElementById('savedBadge').classList.add('show');
        }
    } catch (error) {
        console.error('Error saving attempt:', error);
        document.getElementById('savingIndicator').classList.remove('show');
    }
}

function restartQuiz() {
    currentIndex = 0;
    correctCount = 0;
    answeredCount = 0;
    hasAnswered = false;
    startTime = Date.now();
    
    shuffleArray(flashcards);
    
    document.getElementById('resultsScreen').style.display = 'none';
    document.getElementById('savedBadge').classList.remove('show');
    document.getElementById('quizContainer').style.display = 'block';
    
    loadQuestion(0);
}

function generateNewFlashcards() {
    // Redirect back to dashboard to generate new topic
    window.location.href = 'dashboard.php';
}

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function levenshteinDistance(a, b) {
    if (a.length === 0) return b.length;
    if (b.length === 0) return a.length;
    const matrix = [];
    for (let i = 0; i <= b.length; i++) matrix[i] = [i];
    for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
    for (let i = 1; i <= b.length; i++) {
        for (let j = 1; j <= a.length; j++) {
            matrix[i][j] = b.charAt(i-1) === a.charAt(j-1) 
                ? matrix[i-1][j-1] 
                : Math.min(matrix[i-1][j-1]+1, matrix[i][j-1]+1, matrix[i-1][j]+1);
        }
    }
    return matrix[b.length][a.length];
}
</script>

</body>
</html>