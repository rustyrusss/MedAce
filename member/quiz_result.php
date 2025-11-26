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
$stmt = $conn->prepare("SELECT id, score, attempt_number, attempted_at FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY attempt_number ASC");
$stmt->execute([$attempt['quiz_id'], $studentId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total points and earned points
$totalPoints = 0;
$earnedPoints = 0;
foreach ($results as $r) {
    $points = isset($r['points']) && $r['points'] > 0 ? intval($r['points']) : 1;
    $totalPoints += $points;
    if ($r['is_correct'] == 1) {
        $earnedPoints += $points;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($quiz['title']) ?> - Results</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .text-answer-box {
      background-color: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-top: 0.5rem;
      white-space: pre-wrap;
      word-wrap: break-word;
      max-height: 300px;
      overflow-y: auto;
    }
    .question-type-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
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
      margin-left: 0.5rem;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 p-8">
  <div class="max-w-5xl mx-auto bg-white/90 backdrop-blur-xl shadow-xl rounded-2xl p-8 border border-gray-200">
    
    <!-- Quiz Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($quiz['title']) ?> - Results</h1>
      <a href="../quiz/take_quiz.php?id=<?= $quiz['id'] ?>" 
         class="bg-green-600 text-white px-5 py-2 rounded-lg shadow hover:bg-green-700 transition font-medium">
        🔄 Retake Quiz
      </a>
    </div>

    <!-- Current Attempt Score -->
    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
      <p class="text-gray-700">
        <span class="font-semibold">Score for Attempt #<?= $attempt['attempt_number'] ?? 1 ?>:</span>
        <span class="font-bold text-blue-700 text-xl ml-2">
          <?= $earnedPoints ?> / <?= $totalPoints ?> points
        </span>
        <span class="text-gray-600 ml-2">(<?= $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 1) : 0 ?>%)</span>
      </p>
    </div>

    <!-- Questions -->
    <div class="space-y-4 mb-8">
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
        ?>
        
        <div class="p-5 border rounded-xl shadow-sm <?= $bgColor ?>">
          <!-- Question Type and Points -->
          <div class="flex items-center gap-2 mb-2">
            <span class="question-type-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            <span class="points-badge">🎯 <?= $points ?> point<?= $points != 1 ? 's' : '' ?></span>
            <?php if ($r['is_correct'] == 1): ?>
              <span class="text-xs px-2 py-1 rounded-full bg-green-600 text-white font-bold">✓ CORRECT</span>
            <?php elseif ($r['is_correct'] == 0): ?>
              <span class="text-xs px-2 py-1 rounded-full bg-red-600 text-white font-bold">✗ INCORRECT</span>
            <?php elseif ($isTextQuestion): ?>
              <span class="text-xs px-2 py-1 rounded-full bg-amber-500 text-white font-bold">⏳ PENDING REVIEW</span>
            <?php endif; ?>
          </div>
          
          <p class="font-semibold text-gray-800 mb-3"><?= ($index+1) ?>. <?= htmlspecialchars($r['question_text']) ?></p>
          
          <?php if ($isTextQuestion): ?>
            <!-- Text-based question (Short Answer / Essay) -->
            <div class="mb-2">
              <p class="text-sm font-semibold text-gray-700 mb-1">Your Answer:</p>
              <?php if (!empty($r['student_text_answer'])): ?>
                <div class="text-answer-box text-gray-800">
                  <?= nl2br(htmlspecialchars($r['student_text_answer'])) ?>
                </div>
              <?php else: ?>
                <p class="text-gray-500 italic">No answer provided</p>
              <?php endif; ?>
            </div>
            
            <?php if (!empty($r['correct_answer'])): ?>
              <div class="mt-3 p-3 bg-blue-100 border border-blue-300 rounded-lg">
                <p class="text-sm font-semibold text-blue-900 mb-1">Expected Answer (Reference):</p>
                <p class="text-sm text-blue-800"><?= htmlspecialchars($r['correct_answer']) ?></p>
              </div>
            <?php endif; ?>
            
            <p class="text-xs text-gray-600 mt-2">
              <strong>Note:</strong> This question requires manual grading by your professor.
            </p>
            
          <?php else: ?>
            <!-- Multiple Choice / True-False -->
            <div class="space-y-2">
              <p class="text-sm">
                <span class="font-medium text-gray-700">Your Answer:</span>
                <span class="ml-2 font-semibold <?= $r['is_correct'] ? 'text-green-700' : 'text-red-700' ?>">
                  <?= $r['chosen_answer'] ? htmlspecialchars($r['chosen_answer']) : '<em class="text-gray-500">No Answer</em>' ?>
                </span>
              </p>
              
              <p class="text-sm">
                <span class="font-medium text-gray-700">Correct Answer:</span>
                <span class="ml-2 font-semibold text-green-700">
                  <?= htmlspecialchars($r['correct_answer']) ?>
                </span>
              </p>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- History of Attempts -->
    <h2 class="text-2xl font-semibold mb-4 text-gray-800">📊 Your Quiz History</h2>
    <div class="overflow-x-auto mb-6">
      <table class="min-w-full bg-white border border-gray-200 rounded-xl">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-6 py-3 text-left text-gray-700 font-medium border-b">Attempt #</th>
            <th class="px-6 py-3 text-left text-gray-700 font-medium border-b">Score</th>
            <th class="px-6 py-3 text-left text-gray-700 font-medium border-b">Percentage</th>
            <th class="px-6 py-3 text-left text-gray-700 font-medium border-b">Date</th>
            <th class="px-6 py-3 text-left text-gray-700 font-medium border-b">View</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <?php 
            $percentage = $totalPoints > 0 ? round(($h['score'] / $totalPoints) * 100, 1) : 0;
            $rowClass = ($h['id'] == $attemptId) ? 'bg-blue-50' : '';
          ?>
          <tr class="border-b hover:bg-gray-50 transition <?= $rowClass ?>">
            <td class="px-6 py-3 font-medium"><?= $h['attempt_number'] ?></td>
            <td class="px-6 py-3"><?= $h['score'] ?> / <?= $totalPoints ?></td>
            <td class="px-6 py-3">
              <span class="px-2 py-1 rounded text-xs font-semibold <?= $percentage >= 70 ? 'bg-green-100 text-green-700' : ($percentage >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                <?= $percentage ?>%
              </span>
            </td>
            <td class="px-6 py-3"><?= date('M d, Y H:i', strtotime($h['attempted_at'])) ?></td>
            <td class="px-6 py-3">
              <?php if ($h['id'] == $attemptId): ?>
                <span class="text-blue-600 font-semibold">Current</span>
              <?php else: ?>
                <a href="quiz_result.php?attempt_id=<?= $h['id'] ?>" class="text-blue-600 hover:underline">View</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Back Button -->
    <div class="text-center">
      <a href="../member/dashboard.php" 
         class="inline-block bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition font-medium">
        ⬅ Back to Dashboard
      </a>
    </div>
  </div>
</body>
</html>
