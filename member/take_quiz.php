<?php
session_start();
require_once '../config/db_conn.php';

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

// Fetch quiz info
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) {
    echo "Quiz not found.";
    exit();
}

// Fetch questions + answers
$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$answers = [];
foreach ($questions as $q) {
    $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ?");
    $stmt->execute([$q['id']]);
    $answers[$q['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Determine attempt number
$stmt = $conn->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quizId, $studentId]);
$attemptNumber = $stmt->fetchColumn() + 1;

// Timer setup
$timeLimit = !empty($quiz['time_limit']) ? intval($quiz['time_limit']) * 60 : 0;
if ($timeLimit > 0) {
    if (!isset($_SESSION['quiz_end_'.$quizId]) || $_SESSION['quiz_end_'.$quizId] <= time()) {
        $_SESSION['quiz_end_'.$quizId] = time() + $timeLimit;
    }
    $endTime = $_SESSION['quiz_end_'.$quizId];
    $remaining = max(0, $endTime - time());
} else {
    $remaining = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($quiz['title']) ?> | MedAce</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; color: #111827; }
.accent { background-color: #3b82f6; transition: all 0.2s ease; }
.accent:hover { background-color: #2563eb; }
.question-card { transition: all 0.3s ease; border: 1px solid #e5e7eb; }
.question-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.05); transform: translateY(-3px); }
.radio-option input:checked + span { color: #2563eb; font-weight: 600; }
.progress-bg { background-color: #e5e7eb; }
::selection { background-color: #dbeafe; color: #111827; }
.question-nav button { transition: all 0.2s ease; }
.question-nav button.answered { background-color: #3b82f6; color: #fff; }
.question-nav button:hover { background-color: #2563eb; color: #fff; }
</style>
</head>
<body class="flex min-h-screen">

<!-- Sidebar (Right) -->
<aside class="w-80 p-8 bg-white shadow-xl sticky top-0 h-screen flex flex-col justify-between">
  <div class="space-y-6">
    <!-- Quiz Title & Description -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($quiz['title']) ?></h1>
      <p class="text-gray-500 text-sm"><?= htmlspecialchars($quiz['description']) ?></p>
      <p class="text-gray-600 text-sm mt-1">Attempt #<?= $attemptNumber ?></p>
    </div>

    <!-- Timer -->
    <?php if ($timeLimit > 0): ?>
    <div class="p-4 bg-red-50 rounded-lg border border-red-100 shadow-sm text-center">
      <p class="text-red-600 font-semibold mb-1 text-sm">⏱ Time Remaining</p>
      <div class="text-3xl font-bold text-red-600" id="timer">--:--</div>
    </div>
    <?php else: ?>
    <p class="text-green-600 font-medium text-center">✅ No time limit</p>
    <?php endif; ?>

    <!-- Progress -->
    <div>
      <p class="text-gray-500 text-sm mb-2 text-center">Progress</p>
      <div class="progress-bg rounded-full h-2 overflow-hidden mb-2">
        <div id="progressBar" class="accent h-2 rounded-full w-0 transition-all duration-300"></div>
      </div>
      <p class="text-sm text-gray-500 text-center">
        <span id="answeredCount">0</span> / <?= count($questions) ?> answered
      </p>
    </div>

    <!-- Question Navigator -->
    <div>
      <p class="text-gray-500 text-sm mb-2 text-center">Questions</p>
      <div class="grid grid-cols-5 gap-2 question-nav">
        <?php foreach ($questions as $index => $q): ?>
          <button type="button" data-qid="q<?= $q['id'] ?>" class="w-10 h-10 rounded-full border border-gray-300 text-gray-600 font-medium">
            <?= $index + 1 ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <button type="submit"
          form="quizForm"
          id="submitBtn"
          disabled
          class="accent text-white font-medium py-3 rounded-lg shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed mt-8">
    🚀 Submit Quiz
  </button>
</aside>

<!-- Main Content (Questions) -->
<main class="flex-1 px-10 py-12 overflow-y-auto">
  <div class="max-w-3xl mx-auto space-y-8">
    <form id="quizForm" action="../actions/attempt_quiz.php" method="POST" class="space-y-8">
      <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
      <input type="hidden" name="attempt_number" value="<?= $attemptNumber ?>">

      <?php foreach ($questions as $index => $q): ?>
      <div id="q<?= $q['id'] ?>" class="question-card bg-white rounded-xl p-6 hover:bg-blue-50 transition-colors">
        <p class="font-semibold text-lg text-gray-900 mb-4"><?= ($index + 1) . ". " . htmlspecialchars($q['question_text']) ?></p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <?php foreach ($answers[$q['id']] as $a): ?>
          <label class="radio-option flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
            <input type="radio"
                  name="answers[<?= $q['id'] ?>]"
                  value="<?= htmlspecialchars($a['id']) ?>"
                  class="w-4 h-4 text-blue-600 focus:ring-blue-500 answer-radio"
                  required>
            <span class="text-gray-700"><?= htmlspecialchars($a['answer_text']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
</main>

<!-- Scripts -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const totalQuestions = <?= count($questions) ?>;
  const submitBtn = document.getElementById("submitBtn");
  const answeredCountSpan = document.getElementById("answeredCount");
  const progressBar = document.getElementById("progressBar");
  const radios = document.querySelectorAll(".answer-radio");
  const navButtons = document.querySelectorAll(".question-nav button");

  function updateProgress() {
    const answered = new Set();
    radios.forEach(r => {
      if (r.checked) {
        const qid = r.name.match(/\[(\d+)\]/)[1];
        answered.add(qid);

        // highlight nav button
        const btn = document.querySelector(`.question-nav button[data-qid='q${qid}']`);
        if(btn) btn.classList.add("answered");
      }
    });
    const count = answered.size;
    answeredCountSpan.textContent = count;
    progressBar.style.width = (count / totalQuestions * 100) + "%";
    submitBtn.disabled = count < totalQuestions;
  }

  radios.forEach(radio => radio.addEventListener("change", updateProgress));
  updateProgress();

  // Question navigation
  navButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const target = document.getElementById(btn.dataset.qid);
      if(target) target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  <?php if ($timeLimit > 0): ?>
  let remaining = <?= $remaining ?>;
  const timerDisplay = document.getElementById("timer");

  function updateTimer() {
    if (remaining <= 0) {
      timerDisplay.textContent = "00:00";
      alert("⏰ Time's up! Submitting your quiz...");
      document.getElementById("quizForm").submit();
      return;
    }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerDisplay.textContent = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    remaining--;
  }

  setInterval(updateTimer, 1000);
  updateTimer();
  <?php endif; ?>
});
</script>
</body>
</html>
