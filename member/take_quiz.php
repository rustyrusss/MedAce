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

// ✅ Timer (use quiz.time_limit from DB, stored in minutes)
$timeLimit = !empty($quiz['time_limit']) ? intval($quiz['time_limit']) * 60 : 0; // convert to seconds

if ($timeLimit > 0) {
    // Reset timer if not set or expired
    if (!isset($_SESSION['quiz_end_'.$quizId]) || $_SESSION['quiz_end_'.$quizId] <= time()) {
        $_SESSION['quiz_end_'.$quizId] = time() + $timeLimit;
    }

    $endTime = $_SESSION['quiz_end_'.$quizId];
    $remaining = max(0, $endTime - time()); // seconds left
} else {
    // No time limit
    $remaining = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($quiz['title']) ?> - Take Quiz</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-100 min-h-screen p-6 flex items-center justify-center">

  <div class="w-full max-w-3xl bg-white shadow-2xl rounded-2xl p-8">
    <!-- Header -->
    <div class="border-b pb-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-gray-800 mb-2">
          <?= htmlspecialchars($quiz['title']) ?>
        </h1>
        <p class="text-gray-600"><?= htmlspecialchars($quiz['description']) ?></p>
      </div>

      <div class="flex items-center space-x-4">
        <!-- Timer -->
        <?php if ($timeLimit > 0): ?>
          <span class="bg-red-100 text-red-700 font-semibold px-4 py-2 rounded-lg shadow whitespace-nowrap" id="timer-container">
            ⏰ Time Left: <span id="timer"></span>
          </span>
        <?php else: ?>
          <span class="bg-green-100 text-green-700 font-semibold px-4 py-2 rounded-lg shadow whitespace-nowrap">
            ✅ No time limit
          </span>
        <?php endif; ?>

        <!-- Question progress -->
        <span
          class="bg-blue-100 text-blue-700 font-semibold px-4 py-2 rounded-lg shadow whitespace-nowrap"
          id="progress">
          Questions Answered: <span id="answeredCount">0</span> / <?= count($questions) ?>
        </span>

        <!-- Submit Button -->
        <button
          type="submit"
          form="quizForm"
          id="submitBtn"
          disabled
          class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          🚀 Submit Quiz
        </button>
      </div>
    </div>

    <!-- Quiz Form -->
    <form action="../actions/attempt_quiz.php" method="POST" id="quizForm" class="space-y-8">
      <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

      <?php foreach ($questions as $index => $q): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
          <p class="font-semibold text-lg text-gray-800 mb-4">
            <?= ($index+1) . ". " . htmlspecialchars($q['question_text']) ?>
          </p>

          <?php if (!empty($answers[$q['id']])): ?>
            <div class="space-y-3">
              <?php foreach ($answers[$q['id']] as $a): ?>
                <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-blue-50 transition">
                  <input type="radio" 
                         name="answers[<?= $q['id'] ?>]" 
                         value="<?= htmlspecialchars($a['id']) ?>"
                         class="w-4 h-4 text-blue-600 focus:ring-blue-500 answer-radio"
                         required>
                  <span class="text-gray-700"><?= htmlspecialchars($a['answer_text']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </form>
  </div>

  <!-- Timer and Progress Script -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const totalQuestions = <?= count($questions) ?>;
      const submitBtn = document.getElementById("submitBtn");
      const answeredCountSpan = document.getElementById("answeredCount");
      const radios = document.querySelectorAll(".answer-radio");

      function updateAnsweredCount() {
        const answeredQuestions = new Set();

        radios.forEach(radio => {
          if (radio.checked) {
            // Extract question ID from name, e.g. answers[123]
            const name = radio.name;
            const questionId = name.match(/\[(\d+)\]/)[1];
            answeredQuestions.add(questionId);
          }
        });

        const answered = answeredQuestions.size;
        answeredCountSpan.textContent = answered;

        // Enable submit only if all questions answered
        submitBtn.disabled = answered < totalQuestions;
      }

      radios.forEach(radio => {
        radio.addEventListener("change", updateAnsweredCount);
      });

      // Initialize count on page load
      updateAnsweredCount();

      <?php if ($timeLimit > 0): ?>
      let remaining = <?= $remaining ?>;

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
      <?php endif; ?>
    });
  </script>
</body>
</html>
