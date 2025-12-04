<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// Support both quiz_id (direct) and module_id (from chatbot)
$quizId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0);
$moduleId = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;

if ($quizId <= 0 && $moduleId <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get source info and fetch questions directly from database
$sourceTitle = '';
$sourceDescription = '';
$sourceType = '';
$allQuestions = [];

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

// If no questions, show empty state later
shuffle($allQuestions);
$selectedQuestions = array_slice($allQuestions, 0, 10);

// Fetch answers for selected questions
$flashcards = [];
foreach ($selectedQuestions as $question) {
    $stmt = $conn->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?");
    $stmt->execute([$question['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Shuffle answers
    shuffle($answers);
    
    // Get correct answer(s)
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
        // Provide a friendly single-string correct answer; also provide array for checks
        'correct_answer' => count($correctAnswers) === 1 ? $correctAnswers[0] : implode(', ', $correctAnswers),
        'correct_answers' => $correctAnswers
    ];
}

$flashcardsJson = json_encode($flashcards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$totalQuestions = count($flashcards);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flashcard Quiz - <?= htmlspecialchars($sourceTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* (kept your CSS from your original file - omitted here for brevity) */
        /* Paste the CSS from your original file here if needed — kept the original styling */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
        }
        .container { width: 100%; max-width: 800px; margin: 0 auto; }
        /* ... rest of your CSS ... (kept exactly as you provided earlier) */
        /* For the final paste, keep the CSS you originally had */
        /* I'll include the full CSS here in the paste so it's one file */
        .mode-toggle {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .mode-toggle span { font-size: 0.85rem; color: #6b7280; font-weight: 500; }
        .toggle-switch {
            position: relative; width: 60px; height: 30px;
            background: #e5e7eb; border-radius: 15px;
            cursor: pointer; transition: background 0.3s;
        }
        .toggle-switch.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .toggle-switch::after {
            content: ''; position: absolute; width: 24px; height: 24px;
            background: white; border-radius: 50%; top: 3px; left: 3px;
            transition: transform 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .toggle-switch.active::after { transform: translateX(30px); }
        .mode-label { font-weight: 600; color: #374151; }
        .mode-label.active { color: #667eea; }

        .empty-state {
            background: white; border-radius: 1.5rem; padding: 3rem 2rem;
            text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .empty-state i { font-size: 4rem; color: #d1d5db; margin-bottom: 1.5rem; }

        .quiz-container { display: block; }
        .quiz-header {
            background: white; border-radius: 1.5rem 1.5rem 0 0;
            padding: 1.5rem 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .quiz-source {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.35rem 0.85rem; border-radius: 1rem;
            font-size: 0.75rem; color: white; font-weight: 600; margin-bottom: 0.75rem;
        }
        .quiz-body {
            background: white; padding: 2rem; min-height: 400px;
            border-radius: 0 0 1.5rem 1.5rem;
        }

        .flashcard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border-radius: 1rem; padding: 2rem;
            margin-bottom: 1.5rem; min-height: 160px;
            display: flex; flex-direction: column; justify-content: center;
            position: relative;
        }
        .flashcard .question-number {
            position: absolute; top: 1rem; left: 1rem;
            background: rgba(255,255,255,0.25); padding: 0.25rem 0.6rem;
            border-radius: 0.5rem; font-size: 0.7rem; font-weight: 600;
        }
        .flashcard .question-type {
            position: absolute; top: 1rem; right: 1rem;
            background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem;
            border-radius: 1rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
        }
        .flashcard h2 { font-size: 1.15rem; line-height: 1.6; margin-top: 1rem; }

        .choices-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin-bottom: 1.5rem; }
        .choice-option {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 1rem 1.25rem; background: #f9fafb;
            border: 2px solid #e5e7eb; border-radius: 0.75rem;
            cursor: pointer; transition: all 0.2s;
        }
        .choice-option:hover { border-color: #667eea; background: #f0f4ff; }
        .choice-option.selected { border-color: #667eea; background: #eef2ff; }
        .choice-option.correct { border-color: #10b981; background: #ecfdf5; }
        .choice-option.incorrect { border-color: #ef4444; background: #fef2f2; }
        .choice-letter {
            width: 32px; height: 32px; background: #e5e7eb;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; font-weight: 700; color: #6b7280; flex-shrink: 0;
        }
        .choice-option.selected .choice-letter { background: #667eea; color: white; }
        .choice-option.correct .choice-letter { background: #10b981; color: white; }
        .choice-option.incorrect .choice-letter { background: #ef4444; color: white; }
        .choice-text { flex: 1; font-size: 0.95rem; color: #374151; }
        .choice-icon { font-size: 1.25rem; display: none; }
        .choice-option.correct .choice-icon, .choice-option.incorrect .choice-icon { display: block; }

        .definition-section {
            display: none; background: #f9fafb; border-radius: 1rem;
            padding: 1.5rem; margin-bottom: 1.5rem;
        }
        .definition-section.active { display: block; }
        .definition-section textarea {
            width: 100%; padding: 1rem; border: 2px solid #e5e7eb;
            border-radius: 0.75rem; resize: vertical; font-family: inherit;
            font-size: 0.95rem; min-height: 100px;
        }
        .definition-section textarea:focus { outline: none; border-color: #667eea; }

        .buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn {
            padding: 0.85rem 1.5rem; border: none; border-radius: 0.75rem;
            font-weight: 600; cursor: pointer; transition: all 0.2s;
            font-size: 0.95rem; display: inline-flex; align-items: center;
            justify-content: center; gap: 0.5rem; text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; flex: 1;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-reveal {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; flex: 1;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

        .progress-bar { width: 100%; height: 8px; background: #e5e7eb; border-radius: 1rem; overflow: hidden; margin: 1rem 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s ease; }

        .feedback-box { padding: 1rem 1.25rem; border-radius: 0.75rem; margin-bottom: 1rem; display: none; }
        .feedback-box.correct { background: #ecfdf5; border: 2px solid #10b981; display: block; }
        .feedback-box.incorrect { background: #fef2f2; border: 2px solid #ef4444; display: block; }
        .feedback-box h4 { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .feedback-box.correct h4 { color: #059669; }
        .feedback-box.incorrect h4 { color: #dc2626; }
        .feedback-box p { color: #374151; font-size: 0.9rem; }

        .results-screen {
            display: none; background: white; border-radius: 1.5rem;
            padding: 2.5rem 2rem; text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .results-score {
            font-size: 4.5rem; font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; margin: 1rem 0;
        }
        .results-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin: 2rem 0; max-width: 300px; margin-left: auto; margin-right: auto; }
        .stat-box { background: #f9fafb; border-radius: 1rem; padding: 1.25rem; }
        .stat-box .number { font-size: 2rem; font-weight: 700; }
        .stat-box .label { font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem; }
        .stat-correct .number { color: #10b981; }
        .stat-incorrect .number { color: #ef4444; }

        .saving-indicator {
            display: none; align-items: center; justify-content: center;
            gap: 0.5rem; color: #6b7280; font-size: 0.85rem; margin-top: 1rem;
        }
        .saving-indicator.show { display: flex; }
        .saving-indicator .spinner {
            width: 16px; height: 16px; border: 2px solid #e5e7eb;
            border-top-color: #667eea; border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .saved-badge {
            display: none; align-items: center; justify-content: center;
            gap: 0.5rem; color: #10b981; font-size: 0.85rem; margin-top: 1rem;
        }
        .saved-badge.show { display: flex; }

        @media (max-width: 640px) {
            body { padding: 0.5rem; }
            .quiz-header, .quiz-body { padding: 1.25rem; }
            .flashcard { padding: 1.5rem; min-height: 140px; }
            .flashcard h2 { font-size: 1rem; }
            .buttons { flex-direction: column; }
            .btn { width: 100%; }
            .choice-option { padding: 0.85rem 1rem; }
            .results-stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($totalQuestions === 0): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h2 style="color: #374151; font-size: 1.5rem; margin-bottom: 0.5rem;">No Questions Available</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">There are no multiple choice or true/false questions available for flashcards.</p>
        <a href="<?= $sourceType === 'quiz' ? 'quizzes.php' : 'dashboard.php' ?>" class="btn btn-primary" style="display: inline-flex; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Go Back
        </a>
    </div>
    <?php else: ?>
    
    <!-- Mode Toggle -->
    <div class="mode-toggle">
        <span class="mode-label active" id="mcLabel">Multiple Choice</span>
        <div class="toggle-switch" id="modeToggle" onclick="toggleMode()"></div>
        <span class="mode-label" id="defLabel">Definition Mode</span>
    </div>

    <!-- Quiz Container -->
    <div id="quizContainer" class="quiz-container">
        <div class="quiz-header">
            <span class="quiz-source">
                <i class="fas fa-layer-group"></i> <?= $totalQuestions ?> Random Questions
            </span>
            <h1 style="font-size: 1.4rem; color: #1f2937; margin-bottom: 0.25rem;"><?= $sourceTitle ?></h1>
            <p style="color: #6b7280; font-size: 0.85rem;"><?= $sourceDescription ?></p>
            
            <div class="progress-bar">
                <div id="progressBar" class="progress-fill" style="width: 0%"></div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                <p id="progressText" style="color: #6b7280; font-size: 0.85rem;">Question 1 of <?= $totalQuestions ?></p>
                <p id="scoreText" style="color: #667eea; font-size: 0.85rem; font-weight: 600;">Score: 0/0</p>
            </div>
        </div>

        <div class="quiz-body">
            <div id="questionCard" class="flashcard">
                <span class="question-number" id="questionNumber">#1</span>
                <span class="question-type" id="questionType">Multiple Choice</span>
                <h2 id="questionText"></h2>
            </div>

            <div id="choicesSection" class="choices-grid"></div>

            <div id="definitionSection" class="definition-section">
                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">
                    <i class="fas fa-pencil-alt"></i> Type your answer:
                </label>
                <textarea id="definitionAnswer" placeholder="Type the correct answer here..."></textarea>
                <div class="buttons" style="margin-top: 1rem;">
                    <button onclick="checkDefinitionAnswer()" class="btn btn-reveal">
                        <i class="fas fa-check"></i> Check Answer
                    </button>
                </div>
            </div>

            <div id="feedbackBox" class="feedback-box">
                <h4 id="feedbackTitle"></h4>
                <p id="feedbackText"></p>
            </div>

            <div class="buttons" style="margin-top: 1rem;">
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

        <div class="buttons" style="justify-content: center; gap: 1rem; flex-direction: column; max-width: 300px; margin: 1.5rem auto 0;">
            <button onclick="restartQuiz()" class="btn btn-primary">
                <i class="fas fa-redo"></i> Try Again
            </button>
            <a href="<?= $sourceType === 'quiz' ? 'quizzes.php' : 'dashboard.php' ?>" class="btn btn-secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Go Back
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

let currentIndex = 0;
let correctCount = 0;
let answeredCount = 0;
let isDefinitionMode = false;
let hasAnswered = false;
let startTime = Date.now();

document.addEventListener('DOMContentLoaded', function() {
    if (totalQuestions > 0) {
        loadQuestion(0);
    }
});

function toggleMode() {
    isDefinitionMode = !isDefinitionMode;
    const toggle = document.getElementById('modeToggle');
    const mcLabel = document.getElementById('mcLabel');
    const defLabel = document.getElementById('defLabel');
    
    toggle.classList.toggle('active', isDefinitionMode);
    mcLabel.classList.toggle('active', !isDefinitionMode);
    defLabel.classList.toggle('active', isDefinitionMode);
    
    if (!hasAnswered) loadQuestion(currentIndex);
}

function loadQuestion(index) {
    currentIndex = index;
    hasAnswered = false;
    const card = flashcards[index];
    
    document.getElementById('questionText').textContent = card.question;
    document.getElementById('questionNumber').textContent = `#${index + 1}`;
    
    const typeMap = { 'multiple_choice': 'Multiple Choice', 'true_false': 'True / False' };
    document.getElementById('questionType').textContent = typeMap[card.question_type] || card.question_type;
    
    document.getElementById('progressBar').style.width = (index / totalQuestions) * 100 + '%';
    document.getElementById('progressText').textContent = `Question ${index + 1} of ${totalQuestions}`;
    document.getElementById('scoreText').textContent = `Score: ${correctCount}/${answeredCount}`;
    
    document.getElementById('feedbackBox').className = 'feedback-box';
    document.getElementById('nextBtn').style.display = 'none';
    document.getElementById('finishBtn').style.display = 'none';
    
    if (isDefinitionMode) {
        document.getElementById('choicesSection').style.display = 'none';
        document.getElementById('definitionSection').classList.add('active');
        document.getElementById('definitionAnswer').value = '';
        document.getElementById('definitionAnswer').disabled = false;
    } else {
        document.getElementById('definitionSection').classList.remove('active');
        document.getElementById('choicesSection').style.display = 'grid';
        renderChoices(card);
    }
}

function renderChoices(card) {
    const container = document.getElementById('choicesSection');
    const letters = ['A', 'B', 'C', 'D', 'E', 'F'];
    container.innerHTML = card.choices.map((choice, i) => `
        <div class="choice-option" onclick="selectChoice(this, ${choice.is_correct})" data-correct="${choice.is_correct}">
            <span class="choice-letter">${letters[i]}</span>
            <span class="choice-text">${escapeHtml(choice.text)}</span>
            <span class="choice-icon"></span>
        </div>
    `).join('');
}

function selectChoice(element, isCorrect) {
    if (hasAnswered) return;
    hasAnswered = true;
    answeredCount++;
    
    const card = flashcards[currentIndex];
    element.classList.add('selected');
    
    document.querySelectorAll('.choice-option').forEach(choice => {
        choice.style.pointerEvents = 'none';
        if (choice.dataset.correct === 'true') {
            choice.classList.add('correct');
            choice.querySelector('.choice-icon').innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i>';
        }
    });
    
    if (isCorrect) {
        correctCount++;
        showFeedback(true, 'Correct! 🎉', card.correct_answer);
    } else {
        element.classList.add('incorrect');
        element.querySelector('.choice-icon').innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i>';
        showFeedback(false, 'Incorrect', `The correct answer is: ${card.correct_answer}`);
    }
    
    document.getElementById('scoreText').textContent = `Score: ${correctCount}/${answeredCount}`;
    showNextButton();
}

function checkDefinitionAnswer() {
    if (hasAnswered) return;
    hasAnswered = true;
    answeredCount++;
    
    const card = flashcards[currentIndex];
    const userAnswer = document.getElementById('definitionAnswer').value.trim().toLowerCase();
    const correctAnswer = card.correct_answer.toLowerCase();
    
    document.getElementById('definitionAnswer').disabled = true;
    
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
    document.querySelector('.mode-toggle').style.display = 'none';
    document.getElementById('resultsScreen').style.display = 'block';
    
    // Show saving indicator
    document.getElementById('savingIndicator').classList.add('show');

    // Prepare payload with keys matching your DB columns
    const payload = {
        quiz_id: quizId,
        module_id: moduleId,
        source_title: sourceTitle,
        source_type: sourceType,
        total_questions: totalQuestions,
        correct_answers: correctCount,
        incorrect_answers: totalQuestions - correctCount,
        score_percentage: percentage,
        mode: isDefinitionMode ? 'definition' : 'multiple_choice',
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
        } else {
            // Optionally show an error indicator or toast
            console.warn('Save failed:', result.error || result.message);
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
    document.querySelector('.mode-toggle').style.display = 'flex';
    document.getElementById('quizContainer').style.display = 'block';
    
    loadQuestion(0);
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
