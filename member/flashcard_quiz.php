<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$moduleId = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;

if ($moduleId <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get module details
$stmt = $conn->prepare("SELECT id, title, description FROM modules WHERE id = ?");
$stmt->execute([$moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    header("Location: dashboard.php");
    exit();
}

$moduleTitle = htmlspecialchars($module['title']);
$moduleDescription = htmlspecialchars($module['description'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flashcard Quiz - <?= $moduleTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .container {
            width: 100%;
            max-width: 800px;
        }

        .loading-screen {
            background: white;
            border-radius: 1.5rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .quiz-container {
            display: none;
        }

        .quiz-header {
            background: white;
            border-radius: 1.5rem 1.5rem 0 0;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .quiz-body {
            background: white;
            padding: 2rem;
            min-height: 400px;
        }

        .flashcard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .answer-section {
            background: #f9fafb;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            resize: vertical;
            font-family: inherit;
            font-size: 0.95rem;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

        .review-section {
            display: none;
        }

        .review-box {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .assessment-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-correct {
            background: #10b981;
            color: white;
        }

        .btn-partial {
            background: #f59e0b;
            color: white;
        }

        .btn-incorrect {
            background: #ef4444;
            color: white;
        }

        @media (max-width: 640px) {
            body {
                padding: 0.5rem;
            }

            .quiz-header, .quiz-body {
                padding: 1rem;
            }

            .flashcard {
                padding: 1.5rem;
            }

            .buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Loading Screen -->
    <div id="loadingScreen" class="loading-screen">
        <div class="spinner"></div>
        <h2 style="color: #667eea; font-size: 1.5rem; margin-bottom: 0.5rem;">Generating Flashcards...</h2>
        <p style="color: #6b7280;">AI is creating your study materials</p>
    </div>

    <!-- Quiz Container -->
    <div id="quizContainer" class="quiz-container">
        <div class="quiz-header">
            <h1 style="font-size: 1.5rem; color: #1f2937; margin-bottom: 0.5rem;"><?= $moduleTitle ?></h1>
            <p style="color: #6b7280; font-size: 0.9rem;"><?= $moduleDescription ?></p>
            
            <div class="progress-bar">
                <div id="progressBar" class="progress-fill" style="width: 0%"></div>
            </div>
            
            <p id="progressText" style="text-align: center; color: #6b7280; font-size: 0.85rem; margin-top: 0.5rem;">
                Question 1 of 8
            </p>
        </div>

        <div class="quiz-body">
            <!-- Question Card -->
            <div id="questionCard" class="flashcard">
                <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; opacity: 0.9;">QUESTION</p>
                <h2 id="questionText" style="font-size: 1.25rem; line-height: 1.6;"></h2>
            </div>

            <!-- Answer Input Section -->
            <div id="answerSection" class="answer-section">
                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">
                    ✍️ Your Answer:
                </label>
                <textarea id="studentAnswer" rows="5" placeholder="Type your answer here..."></textarea>
                
                <div class="buttons" style="margin-top: 1rem;">
                    <button onclick="submitAnswer()" class="btn btn-primary">
                        <i class="fas fa-check"></i> Submit Answer
                    </button>
                    <button onclick="skipAnswer()" class="btn btn-secondary">
                        <i class="fas fa-forward"></i> Skip
                    </button>
                </div>
            </div>

            <!-- Review Section -->
            <div id="reviewSection" class="review-section">
                <div class="review-box" style="border-color: #93c5fd;">
                    <p style="font-weight: 600; color: #1e40af; margin-bottom: 0.5rem;">
                        <i class="fas fa-user-edit"></i> Your Answer:
                    </p>
                    <p id="submittedAnswer" style="color: #374151;"></p>
                </div>

                <div class="review-box" style="border-color: #86efac;">
                    <p style="font-weight: 600; color: #166534; margin-bottom: 0.5rem;">
                        <i class="fas fa-check-circle"></i> Correct Answer:
                    </p>
                    <p id="correctAnswer" style="color: #374151;"></p>
                </div>

                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem;">
                    <p style="font-weight: 600; color: #78350f; margin-bottom: 0.75rem; text-align: center;">
                        📊 How did you do?
                    </p>
                    <div class="assessment-buttons">
                        <button onclick="recordAssessment('incorrect')" class="btn btn-incorrect">
                            ❌ Incorrect
                        </button>
                        <button onclick="recordAssessment('partial')" class="btn btn-partial">
                            ⚠️ Partial
                        </button>
                        <button onclick="recordAssessment('correct')" class="btn btn-correct">
                            ✅ Correct
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="buttons">
                <button id="prevBtn" onclick="previousCard()" class="btn btn-secondary" style="max-width: 120px;" disabled>
                    ← Previous
                </button>
                <div style="flex: 1;"></div>
                <button id="nextBtn" onclick="nextCard()" class="btn btn-primary" style="max-width: 120px;" disabled>
                    Next →
                </button>
                <button id="finishBtn" onclick="finishQuiz()" class="btn btn-primary" style="max-width: 120px; display: none;" disabled>
                    ✓ Finish
                </button>
            </div>
        </div>
    </div>
</div>

<script>
console.log('🎯 Flashcard Quiz Page Loaded');
console.log('Module ID:', <?= $moduleId ?>);

let flashcards = [];
let currentIndex = 0;
let results = [];

// Generate flashcards on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('📚 Starting flashcard generation...');
    generateFlashcards();
});

async function generateFlashcards() {
    console.log('Calling API: ../config/chatbot_integration.php');
    console.log('Module ID:', <?= $moduleId ?>);
    
    try {
        const response = await fetch('../config/chatbot_integration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_module_content',
                module_id: <?= $moduleId ?>
            })
        });

        console.log('Get module response:', response.status);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const moduleData = await response.json();
        console.log('Module data:', moduleData);

        if (!moduleData.success || !moduleData.module) {
            throw new Error('Failed to get module content');
        }

        // Now generate flashcards
        console.log('Generating flashcards from module content...');
        
        const flashcardResponse = await fetch('../config/chatbot_integration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: `Generate flashcards for ${moduleData.module.title}`,
                task: 'flashcard',
                module_id: <?= $moduleId ?>
            })
        });

        console.log('Flashcard generation response:', flashcardResponse.status);

        if (!flashcardResponse.ok) {
            throw new Error(`HTTP ${flashcardResponse.status}`);
        }

        const result = await flashcardResponse.json();
        console.log('Flashcard result:', result);

        if (result.error) {
            throw new Error(result.error);
        }

        if (!result.success || !result.flashcards || result.flashcards.length === 0) {
            throw new Error('No flashcards generated');
        }

        flashcards = result.flashcards;
        console.log('✅ Generated', flashcards.length, 'flashcards');

        // Initialize results array
        results = new Array(flashcards.length).fill(null);

        // Hide loading, show quiz
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('quizContainer').style.display = 'block';

        // Load first card
        loadCard(0);

    } catch (error) {
        console.error('❌ Error generating flashcards:', error);
        alert('Failed to generate flashcards. Please try again.\n\nError: ' + error.message);
        window.location.href = 'dashboard.php';
    }
}

function loadCard(index) {
    console.log('Loading card', index + 1, 'of', flashcards.length);
    
    currentIndex = index;
    const card = flashcards[index];

    // Update question
    document.getElementById('questionText').textContent = card.question;

    // Reset UI
    document.getElementById('studentAnswer').value = '';
    document.getElementById('answerSection').style.display = 'block';
    document.getElementById('reviewSection').style.display = 'none';

    // Update progress
    const progress = ((index) / flashcards.length) * 100;
    document.getElementById('progressBar').style.width = progress + '%';
    document.getElementById('progressText').textContent = `Question ${index + 1} of ${flashcards.length}`;

    // Update navigation buttons
    document.getElementById('prevBtn').disabled = (index === 0);
    document.getElementById('nextBtn').disabled = true;
    document.getElementById('nextBtn').style.display = (index < flashcards.length - 1) ? 'block' : 'none';
    document.getElementById('finishBtn').disabled = true;
    document.getElementById('finishBtn').style.display = (index === flashcards.length - 1) ? 'block' : 'none';
}

function submitAnswer() {
    const answer = document.getElementById('studentAnswer').value.trim();
    
    if (!answer) {
        alert('Please type your answer before submitting.');
        return;
    }

    // Show review section
    document.getElementById('submittedAnswer').textContent = answer;
    document.getElementById('correctAnswer').textContent = flashcards[currentIndex].answer;
    document.getElementById('answerSection').style.display = 'none';
    document.getElementById('reviewSection').style.display = 'block';
}

function skipAnswer() {
    document.getElementById('submittedAnswer').innerHTML = '<em style="color: #6b7280;">Skipped - No answer provided</em>';
    document.getElementById('correctAnswer').textContent = flashcards[currentIndex].answer;
    document.getElementById('answerSection').style.display = 'none';
    document.getElementById('reviewSection').style.display = 'block';
    
    results[currentIndex] = 'skipped';
    enableNavigation();
}

function recordAssessment(assessment) {
    console.log('Assessment:', assessment);
    results[currentIndex] = assessment;
    enableNavigation();
}

function enableNavigation() {
    if (currentIndex < flashcards.length - 1) {
        document.getElementById('nextBtn').disabled = false;
    } else {
        document.getElementById('finishBtn').disabled = false;
    }
}

function previousCard() {
    if (currentIndex > 0) {
        loadCard(currentIndex - 1);
    }
}

function nextCard() {
    if (currentIndex < flashcards.length - 1) {
        loadCard(currentIndex + 1);
    }
}

function finishQuiz() {
    const total = flashcards.length;
    const correct = results.filter(r => r === 'correct').length;
    const partial = results.filter(r => r === 'partial').length;
    const incorrect = results.filter(r => r === 'incorrect').length;
    const skipped = results.filter(r => r === 'skipped').length;
    
    const percentage = Math.round((correct / total) * 100);
    
    let message = `Quiz Complete!\n\n`;
    message += `Score: ${percentage}%\n`;
    message += `Correct: ${correct}\n`;
    message += `Partial: ${partial}\n`;
    message += `Incorrect: ${incorrect}\n`;
    message += `Skipped: ${skipped}\n`;
    
    alert(message);
    window.location.href = 'dashboard.php';
}
</script>

</body>
</html>