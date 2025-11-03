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

// ✅ Fetch quiz info
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) {
    echo "Quiz not found.";
    exit();
}

// ✅ Fetch questions + answers
$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$answers = [];
foreach ($questions as $q) {
    $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ?");
    $stmt->execute([$q['id']]);
    $answers[$q['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Timer setup
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
  <style>
    body { background-color: #ecf2f3ff; }
    .sidebar { background-color: #1d9296ff; }
    .accent { background-color: #2c9643ff; }
    .accent:hover { background-color: #4b6f6c; }
  </style>
</head>
<body class="min-h-screen flex">

  <!-- Sidebar -->
  <aside class="sidebar w-72 text-white flex flex-col justify-between p-6 sticky top-0 h-screen">
    <div>
      <h1 class="text-2xl font-extrabold mb-1"><?= htmlspecialchars($quiz['title']) ?></h1>
      <p class="text-sm text-gray-300 mb-6"><?= htmlspecialchars($quiz['description']) ?></p>

      <?php if ($timeLimit > 0): ?>
        <div class="mb-6">
          <p class="text-gray-300 text-sm mb-1">⏰ Time Remaining</p>
          <div class="text-3xl font-bold text-red-400" id="timer">--:--</div>
        </div>
      <?php else: ?>
        <p class="text-green-400 font-semibold mb-6">✅ No time limit</p>
      <?php endif; ?>

      <div class="mb-4">
        <p class="text-gray-300 text-sm mb-1">Progress</p>
        <div class="w-full bg-white rounded-full h-3">
          <div id="progressBar" class="accent h-3 rounded-full transition-all duration-300" style="width:0%"></div>
        </div>
        <p class="mt-2 text-sm text-gray-200">
          <span id="answeredCount">0</span> / <?= count($questions) ?> answered
        </p>
      </div>
    </div>

    <div>
      <button
        type="submit"
        form="quizForm"
        id="submitBtn"
        disabled
        class="w-full accent text-white font-semibold py-3 rounded-lg shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
        🚀 Submit Quiz
      </button>
    </div>
  </aside>

  <!-- Main Quiz Section -->
  <main class="flex-1 p-10 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-3xl mx-auto">
      <form id="quizForm" action="../actions/attempt_quiz.php" method="POST" class="space-y-8">
        <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

        <?php foreach ($questions as $index => $q): ?>
          <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 hover:shadow-md transition">
            <p class="font-semibold text-lg text-gray-800 mb-4">
              <?= ($index + 1) . ". " . htmlspecialchars($q['question_text']) ?>
            </p>

            <?php foreach ($answers[$q['id']] as $a): ?>
              <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-[#D5ECEE] transition">
                <input type="radio"
                       name="answers[<?= $q['id'] ?>]"
                       value="<?= htmlspecialchars($a['id']) ?>"
                       class="w-4 h-4 text-[#587F7C] focus:ring-[#587F7C] answer-radio"
                       required>
                <span class="text-gray-700"><?= htmlspecialchars($a['answer_text']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </form>
    </div>
  </main>

  <!-- Timer & Progress Script -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const totalQuestions = <?= count($questions) ?>;
      const submitBtn = document.getElementById("submitBtn");
      const answeredCountSpan = document.getElementById("answeredCount");
      const progressBar = document.getElementById("progressBar");
      const radios = document.querySelectorAll(".answer-radio");

      function updateProgress() {
        const answered = new Set();
        radios.forEach(r => {
          if (r.checked) {
            const qid = r.name.match(/\[(\d+)\]/)[1];
            answered.add(qid);
          }
        });
        const count = answered.size;
        answeredCountSpan.textContent = count;
        progressBar.style.width = (count / totalQuestions * 100) + "%";
        submitBtn.disabled = count < totalQuestions;
      }

      radios.forEach(radio => radio.addEventListener("change", updateProgress));
      updateProgress();

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
