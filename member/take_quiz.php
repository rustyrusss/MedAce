<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$quizId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$quizId) {
    echo "Invalid quiz ID.";
    exit();
}

// ✅ Get quiz details
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "Quiz not found.";
    exit();
}

// ✅ Get questions
$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ For each question get answers
$answers = [];
foreach ($questions as $q) {
    $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ?");
    $stmt->execute([$q['id']]);
    $answers[$q['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Timer (e.g., 60 seconds per quiz)
$timeLimit = 60; // seconds

// Reset timer if not set or expired
if (!isset($_SESSION['quiz_end_'.$quizId]) || $_SESSION['quiz_end_'.$quizId] <= time()) {
    $_SESSION['quiz_end_'.$quizId] = time() + $timeLimit;
}

$endTime = $_SESSION['quiz_end_'.$quizId];
$remaining = max(0, $endTime - time()); // seconds left
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($quiz['title']) ?> - Take Quiz</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">

  <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($quiz['title']) ?></h1>
    <p class="text-gray-600 mb-4"><?= htmlspecialchars($quiz['description']) ?></p>

    <!-- Timer -->
    <div class="mb-6 text-right text-lg font-semibold text-red-600">
      Time Left: <span id="timer"></span>
    </div>

    <form action="../actions/attempt_quiz.php" method="POST" id="quizForm">
      <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
      
      <?php foreach ($questions as $index => $q): ?>
        <div class="mb-6">
          <p class="font-semibold mb-2">
            <?= ($index+1) . ". " . htmlspecialchars($q['question_text']) ?>
          </p>
          <?php if (!empty($answers[$q['id']])): ?>
            <?php foreach ($answers[$q['id']] as $a): ?>
              <label class="block mb-2">
                <input type="radio" 
                       name="answers[<?= $q['id'] ?>]" 
                       value="<?= htmlspecialchars($a['id']) ?>"
                       required>
                <?= htmlspecialchars($a['answer_text']) ?>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg shadow hover:bg-blue-700">
        Submit Quiz
      </button>
    </form>
  </div>

  <script>
    let remaining = <?= $remaining ?>; // seconds left from PHP

    function updateTimer() {
      if (remaining <= 0) {
        document.getElementById("timer").innerHTML = "00:00";
        alert("⏰ Time's up! Submitting your quiz...");
        document.getElementById("quizForm").submit();
        return;
      }

      let minutes = Math.floor(remaining / 60);
      let seconds = remaining % 60;

      document.getElementById("timer").innerHTML =
        (minutes < 10 ? "0"+minutes : minutes) + ":" +
        (seconds < 10 ? "0"+seconds : seconds);

      remaining--;
    }

    setInterval(updateTimer, 1000);
    updateTimer();
  </script>
</body>
</html>
