<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Only students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$attemptId = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null;

if (!$attemptId) {
    echo "Invalid attempt.";
    exit();
}

// ✅ Fetch attempt
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND student_id = ?");
$stmt->execute([$attemptId, $studentId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    echo "Attempt not found.";
    exit();
}

// ✅ Fetch quiz
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$attempt['quiz_id']]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ First, check what columns exist in student_answers table
$checkColumns = $conn->query("SHOW COLUMNS FROM student_answers");
$existingColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);

// Determine which column name to use for text answers
$textAnswerColumn = 'answer_id'; // default fallback
$possibleTextColumns = ['student_answer', 'text_answer', 'answer_text', 'essay_answer', 'written_answer'];
foreach ($possibleTextColumns as $colName) {
    if (in_array($colName, $existingColumns)) {
        $textAnswerColumn = $colName;
        break;
    }
}

// ✅ Fetch student answers with correct answers AND text answers
$query = "
    SELECT 
        q.id AS question_id,
        q.question_text,
        q.question_type,
        q.points,
        sa.answer_id AS chosen_answer_id,
        sa.{$textAnswerColumn} AS student_text_answer,
        a_student.answer_text AS chosen_answer,
        a_correct.id AS correct_answer_id,
        a_correct.answer_text AS correct_answer,
        CASE 
            WHEN q.question_type IN ('multiple_choice', 'true_false') THEN
                CASE WHEN sa.answer_id = a_correct.id THEN 1 ELSE 0 END
            ELSE NULL
        END AS is_correct
    FROM questions q
    LEFT JOIN student_answers sa 
        ON q.id = sa.question_id AND sa.attempt_id = ?
    LEFT JOIN answers a_student 
        ON sa.answer_id = a_student.id
    LEFT JOIN answers a_correct 
        ON q.id = a_correct.question_id AND a_correct.is_correct = 1
    WHERE q.quiz_id = ?
    ORDER BY q.id ASC
";

$stmt = $conn->prepare($query);
$stmt->execute([$attemptId, $attempt['quiz_id']]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch history of all attempts for this quiz
$stmt = $conn->prepare("SELECT id, score, attempt_number, attempted_at FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY attempt_number DESC");
$stmt->execute([$attempt['quiz_id'], $studentId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total points from questions
$stmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) as total FROM questions WHERE quiz_id = ?");
$stmt->execute([$attempt['quiz_id']]);
$totalPoints = $stmt->fetchColumn();

// Get highest score
$stmt = $conn->prepare("SELECT MAX(score) as highest FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$attempt['quiz_id'], $studentId]);
$highestScore = $stmt->fetchColumn();

$earnedPoints = $attempt['score'];
$currentPercentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 1) : 0;
$highestPercentage = $totalPoints > 0 ? round(($highestScore / $totalPoints) * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($quiz['title']) ?> - Results</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { 
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
      overflow: hidden;
      /* Disable text selection for anti-screenshot */
      -webkit-user-select: none;
      -moz-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
    .scrollable-content {
      height: calc(100vh - 140px);
      overflow-y: auto;
      overflow-x: hidden;
    }
    .scrollable-content::-webkit-scrollbar {
      width: 10px;
    }
    .scrollable-content::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    .scrollable-content::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 5px;
    }
    .scrollable-content::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    .text-answer-box {
      background-color: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-top: 0.5rem;
      white-space: pre-wrap;
      word-wrap: break-word;
      max-height: 200px;
      overflow-y: auto;
    }
    .question-type-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .badge-multiple-choice {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .badge-short-answer {
      background-color: #fef3c7;
      color: #92400e;
    }
    .badge-essay {
      background-color: #f3e8ff;
      color: #6b21a8;
    }
    .badge-true-false {
      background-color: #dcfce7;
      color: #166534;
    }
    .points-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 700;
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
      color: #78350f;
    }
    [x-cloak] { display: none !important; }

    /* Anti-screenshot watermark overlay */
    .watermark-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9999;
      opacity: 0.03;
      font-size: 48px;
      font-weight: bold;
      color: #000;
      transform: rotate(-45deg);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-around;
      align-content: space-around;
    }
    .watermark-text {
      padding: 20px;
      white-space: nowrap;
    }

    /* Blur effect when window loses focus */
    body.blurred .scrollable-content {
      filter: blur(10px);
      transition: filter 0.3s;
    }

    /* Screenshot detection overlay */
    .screenshot-warning {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(239, 68, 68, 0.95);
      color: white;
      padding: 2rem;
      border-radius: 1rem;
      z-index: 10000;
      text-align: center;
      font-size: 1.5rem;
      font-weight: bold;
      display: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }

    /* Black screen overlay for screenshot protection (Netflix-style) */
    .black-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #000000;
      z-index: 99999;
      display: none;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }

    .black-screen.active {
      display: flex;
    }

    .black-screen-icon {
      font-size: 5rem;
      color: #e50914;
      margin-bottom: 2rem;
      animation: pulse 1.5s infinite;
    }

    .black-screen-text {
      font-size: 2rem;
      font-weight: bold;
      color: #ffffff;
      text-align: center;
      margin-bottom: 1rem;
    }

    .black-screen-subtext {
      font-size: 1rem;
      color: #b3b3b3;
      text-align: center;
    }

    @keyframes pulse {
      0%, 100% { 
        transform: scale(1);
        opacity: 1;
      }
      50% { 
        transform: scale(1.1);
        opacity: 0.8;
      }
    }
  </style>
</head>
<body class="bg-gray-50" x-data="{ showHistory: false, revealedAnswers: {} }">
  
  <!-- Black Screen Overlay (Netflix-style) -->
  <div id="blackScreen" class="black-screen">
    <div class="black-screen-icon">
      <i class="fas fa-ban"></i>
    </div>
    <div class="black-screen-text">
      Screenshot Blocked
    </div>
    <div class="black-screen-subtext">
      This content is protected
    </div>
  </div>

  <!-- Watermark Overlay (appears in screenshots) -->
  <div class="watermark-overlay">
    <?php for($i = 0; $i < 50; $i++): ?>
      <div class="watermark-text">
        <?= htmlspecialchars($student['firstname'] ?? 'Student') ?> - <?= date('Y-m-d H:i') ?> - DO NOT SHARE
      </div>
    <?php endfor; ?>
  </div>

  <!-- Screenshot Warning -->
  <div id="screenshotWarning" class="screenshot-warning">
    <i class="fas fa-exclamation-triangle text-6xl mb-4"></i>
    <div>⚠️ SCREENSHOT DETECTED ⚠️</div>
    <div class="text-lg mt-2">This action has been logged</div>
  </div>

  <!-- Fixed Header -->
  <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg">
    <div class="max-w-5xl mx-auto px-6 py-4">
      <div class="flex items-center justify-between mb-3">
        <a href="quizzes.php" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-colors">
          <i class="fas fa-arrow-left"></i>
          <span class="font-medium">Back to Quizzes</span>
        </a>
        <button @click="showHistory = !showHistory" 
                class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-colors">
          <i class="fas fa-history"></i>
          <span class="font-medium">History</span>
          <span class="bg-white/30 text-white px-2 py-0.5 rounded-full text-xs font-semibold"><?= count($history) ?></span>
        </button>
      </div>
      <h1 class="text-2xl font-bold"><?= htmlspecialchars($quiz['title']) ?></h1>
      <p class="text-blue-100 text-sm">Attempt #<?= $attempt['attempt_number'] ?? 1 ?> • <?= count($results) ?> Questions</p>
    </div>
  </div>

  <!-- Score Bar -->
  <div class="bg-white border-b shadow-sm">
    <div class="max-w-5xl mx-auto px-6 py-3">
      <div class="flex items-center justify-around">
        <div class="text-center">
          <div class="text-xs text-gray-500 font-semibold uppercase mb-1">Current Score</div>
          <div class="flex items-baseline gap-2 justify-center">
            <span class="text-3xl font-bold text-blue-600"><?= $currentPercentage ?>%</span>
            <span class="text-sm text-gray-500">(<?= $earnedPoints ?>/<?= $totalPoints ?> pts)</span>
          </div>
        </div>
        <div class="h-12 w-px bg-gray-300"></div>
        <div class="text-center">
          <div class="text-xs text-amber-600 font-semibold uppercase mb-1 flex items-center gap-1 justify-center">
            <i class="fas fa-trophy"></i> Highest Score
          </div>
          <div class="flex items-baseline gap-2 justify-center">
            <span class="text-3xl font-bold text-amber-600"><?= $highestPercentage ?>%</span>
            <span class="text-sm text-gray-500">(<?= $highestScore ?>/<?= $totalPoints ?> pts)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- History Sidebar -->
  <div x-show="showHistory" 
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="showHistory = false"
       class="fixed inset-0 bg-black bg-opacity-50 z-40" 
       x-cloak>
  </div>
  
  <div x-show="showHistory"
       x-transition:enter="transition ease-out duration-300 transform"
       x-transition:enter-start="translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-200 transform"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="translate-x-full"
       class="fixed right-0 top-0 h-full w-96 bg-white shadow-2xl z-50 overflow-y-auto"
       @click.away="showHistory = false"
       x-cloak>
    <div class="p-6 border-b sticky top-0 bg-white z-10">
      <div class="flex items-center justify-between">
        <h2 class="font-bold text-xl text-gray-900">Attempt History</h2>
        <button @click="showHistory = false" class="text-gray-400 hover:text-gray-600 transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
    </div>
    <div class="p-6 space-y-3">
      <?php foreach ($history as $h): ?>
      <?php 
        $percentage = $totalPoints > 0 ? round(($h['score'] / $totalPoints) * 100, 1) : 0;
        $isCurrent = ($h['id'] == $attemptId);
        $isHighest = ($h['score'] == $highestScore);
      ?>
      <div class="p-4 rounded-xl border-2 <?= $isCurrent ? 'bg-blue-50 border-blue-300' : 'border-gray-200 hover:border-gray-300' ?> transition-all">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <span class="font-bold text-lg text-gray-900">#<?= $h['attempt_number'] ?></span>
            <?php if ($isHighest): ?>
              <i class="fas fa-trophy text-amber-500"></i>
            <?php endif; ?>
            <?php if ($isCurrent): ?>
              <span class="text-xs bg-blue-500 text-white px-2 py-1 rounded-full font-semibold">Current</span>
            <?php endif; ?>
          </div>
          <span class="text-lg font-bold <?= $percentage >= 70 ? 'text-green-600' : ($percentage >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
            <?= $percentage ?>%
          </span>
        </div>
        <div class="text-sm text-gray-600 mb-1"><?= $h['score'] ?> / <?= $totalPoints ?> points</div>
        <div class="text-xs text-gray-500"><?= date('M d, Y g:i A', strtotime($h['attempted_at'])) ?></div>
        <?php if (!$isCurrent): ?>
          <a href="quiz_result.php?attempt_id=<?= $h['id'] ?>" 
             class="text-sm text-blue-600 hover:text-blue-700 font-medium block mt-3">
            View Details <i class="fas fa-arrow-right ml-1"></i>
          </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Scrollable Content Area -->
  <div class="scrollable-content">
    <div class="max-w-5xl mx-auto p-6">
      <div class="space-y-4">
        <?php foreach ($results as $index => $r): ?>
          <?php
            $questionType = $r['question_type'];
            $badgeClass = 'badge-multiple-choice';
            $badgeText = 'Multiple Choice';
            
            if ($questionType === 'short_answer') {
              $badgeClass = 'badge-short-answer';
              $badgeText = 'Short Answer';
            } elseif ($questionType === 'essay') {
              $badgeClass = 'badge-essay';
              $badgeText = 'Essay';
            } elseif ($questionType === 'true_false') {
              $badgeClass = 'badge-true-false';
              $badgeText = 'True / False';
            }
            
            $points = isset($r['points']) && $r['points'] > 0 ? intval($r['points']) : 1;
            $isTextQuestion = in_array($questionType, ['short_answer', 'essay']);
            $bgColor = $isTextQuestion ? 'bg-blue-50 border-blue-200' : ($r['is_correct'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200');
            $questionId = $r['question_id'];
          ?>
          
          <div class="bg-white border-2 rounded-xl shadow-sm <?= $bgColor ?>">
            <div class="p-4">
              <!-- Question Header -->
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <span class="font-bold text-gray-900">Q<?= $index + 1 ?></span>
                    <span class="question-type-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    <span class="points-badge"><?= $points ?> pt<?= $points != 1 ? 's' : '' ?></span>
                    <?php if ($r['is_correct'] == 1): ?>
                      <span class="text-xs px-2 py-1 rounded-full bg-green-600 text-white font-bold">
                        <i class="fas fa-check"></i> CORRECT
                      </span>
                    <?php elseif ($r['is_correct'] == 0): ?>
                      <span class="text-xs px-2 py-1 rounded-full bg-red-600 text-white font-bold">
                        <i class="fas fa-times"></i> INCORRECT
                      </span>
                    <?php elseif ($isTextQuestion): ?>
                      <span class="text-xs px-2 py-1 rounded-full bg-amber-500 text-white font-bold">
                        <i class="fas fa-clock"></i> PENDING
                      </span>
                    <?php endif; ?>
                  </div>
                  <p class="font-medium text-gray-800"><?= htmlspecialchars($r['question_text']) ?></p>
                </div>
              </div>
              
              <?php if ($isTextQuestion): ?>
                <!-- Text-based question -->
                <div class="mb-3">
                  <p class="text-xs font-semibold text-gray-700 mb-1">Your Answer:</p>
                  <?php if (!empty($r['student_text_answer'])): ?>
                    <div class="text-answer-box text-sm text-gray-800 bg-white">
                      <?= nl2br(htmlspecialchars($r['student_text_answer'])) ?>
                    </div>
                  <?php else: ?>
                    <p class="text-sm text-gray-500 italic bg-white p-3 rounded-lg border border-gray-200">No answer provided</p>
                  <?php endif; ?>
                </div>
                
                <?php if (!empty($r['correct_answer'])): ?>
                  <div>
                    <button 
                      @click="revealedAnswers[<?= $questionId ?>] = !revealedAnswers[<?= $questionId ?>]"
                      class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                      <i class="fas" :class="revealedAnswers[<?= $questionId ?>] ? 'fa-eye-slash' : 'fa-eye'"></i>
                      <span x-text="revealedAnswers[<?= $questionId ?>] ? 'Hide Expected' : 'Show Expected'"></span>
                    </button>
                    <div x-show="revealedAnswers[<?= $questionId ?>]" x-collapse class="mt-2 p-3 bg-blue-100 border border-blue-300 rounded-lg text-sm text-blue-900">
                      <?= nl2br(htmlspecialchars($r['correct_answer'])) ?>
                    </div>
                  </div>
                <?php endif; ?>
                
              <?php else: ?>
                <!-- Multiple Choice -->
                <div class="space-y-2">
                  <div>
                    <p class="text-xs font-semibold text-gray-700 mb-1">Your Answer:</p>
                    <div class="text-sm bg-white p-3 rounded-lg border-2 <?= $r['is_correct'] ? 'border-green-300' : 'border-red-300' ?>">
                      <?= $r['chosen_answer'] ? htmlspecialchars($r['chosen_answer']) : '<em class="text-gray-400">No answer</em>' ?>
                    </div>
                  </div>
                  
                  <div>
                    <button 
                      @click="revealedAnswers[<?= $questionId ?>] = !revealedAnswers[<?= $questionId ?>]"
                      class="text-sm bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                      <i class="fas" :class="revealedAnswers[<?= $questionId ?>] ? 'fa-eye-slash' : 'fa-eye'"></i>
                      <span x-text="revealedAnswers[<?= $questionId ?>] ? 'Hide Correct' : 'Show Correct'"></span>
                    </button>
                    <div x-show="revealedAnswers[<?= $questionId ?>]" x-collapse class="mt-2 bg-green-50 p-3 rounded-lg border-2 border-green-300 text-sm font-medium text-green-800">
                      <?= htmlspecialchars($r['correct_answer']) ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Anti-Screenshot JavaScript -->
  <script>
    let blackScreenTimeout;
    const blackScreen = document.getElementById('blackScreen');

    // Show black screen (Netflix-style)
    function showBlackScreen() {
      blackScreen.classList.add('active');
      
      // Keep black screen for 2 seconds
      clearTimeout(blackScreenTimeout);
      blackScreenTimeout = setTimeout(() => {
        blackScreen.classList.remove('active');
      }, 2000);
    }

    // Disable right-click context menu
    document.addEventListener('contextmenu', function(e) {
      e.preventDefault();
      return false;
    });

    // Disable common screenshot keyboard shortcuts
    document.addEventListener('keyup', function(e) {
      // PrintScreen, Cmd+Shift+3/4/5 (Mac), Windows+Shift+S, etc.
      if (e.key === 'PrintScreen' || 
          (e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4' || e.key === '5')) ||
          (e.key === 's' && e.shiftKey && (e.metaKey || e.ctrlKey))) {
        
        // Show white screen immediately
        showBlackScreen();
        
        // Log the attempt
        console.warn('Screenshot attempt detected at:', new Date().toISOString());
        
        // Send to server
        fetch('log_screenshot_attempt.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            student_id: <?= $studentId ?>,
            quiz_id: <?= $quiz['id'] ?>,
            attempt_id: <?= $attemptId ?>,
            timestamp: new Date().toISOString()
          })
        }).catch(err => console.error('Failed to log:', err));
      }
    });

    // Also trigger white screen on keydown for faster response
    document.addEventListener('keydown', function(e) {
      if (e.key === 'PrintScreen' || 
          (e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4' || e.key === '5')) ||
          (e.key === 's' && e.shiftKey && (e.metaKey || e.ctrlKey))) {
        showBlackScreen();
      }
    });

    // Detect when user switches tabs/apps (possible screenshot)
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        document.body.classList.add('blurred');
        // Show black screen when tab is hidden
        showBlackScreen();
      } else {
        document.body.classList.remove('blurred');
      }
    });

    // Blur content when window loses focus
    window.addEventListener('blur', function() {
      document.body.classList.add('blurred');
      showBlackScreen();
    });

    window.addEventListener('focus', function() {
      document.body.classList.remove('blurred');
    });

    // Disable F12 (DevTools)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'F12' || 
          (e.ctrlKey && e.shiftKey && e.key === 'I') ||
          (e.ctrlKey && e.shiftKey && e.key === 'J') ||
          (e.ctrlKey && e.key === 'U')) {
        e.preventDefault();
        showBlackScreen();
        return false;
      }
    });

    // Detect if DevTools is open
    var devtools = {open: false, orientation: null};
    var threshold = 160;

    setInterval(function() {
      if (window.outerWidth - window.innerWidth > threshold || 
          window.outerHeight - window.innerHeight > threshold) {
        if (!devtools.open) {
          console.warn('DevTools detected!');
          document.body.classList.add('blurred');
          showBlackScreen();
        }
        devtools.open = true;
      } else {
        if (devtools.open) {
          document.body.classList.remove('blurred');
        }
        devtools.open = false;
      }
    }, 500);

    // Prevent drag and drop of images
    document.addEventListener('dragstart', function(e) {
      e.preventDefault();
      return false;
    });

    // Monitor for screenshot tools/apps opening
    // Trigger white screen when window is resized (possible screenshot tool)
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        // Check if resize might be from screenshot tool
        if (Math.abs(window.innerWidth - window.outerWidth) > 50) {
          showBlackScreen();
        }
      }, 100);
    });

    // Additional protection: Monitor clipboard
    document.addEventListener('copy', function(e) {
      showBlackScreen();
      e.preventDefault();
      return false;
    });

    // Prevent screenshots via browser extensions
    setInterval(function() {
      // Detect if page is being captured by checking for canvas operations
      const canvases = document.getElementsByTagName('canvas');
      if (canvases.length > 0) {
        showBlackScreen();
      }
    }, 1000);
  </script>

</body>
</html>