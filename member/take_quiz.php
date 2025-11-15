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

/* Warning Banner Styles */
.warning-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  padding: 1rem;
  text-align: center;
  font-weight: 600;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    transform: translateY(-100%);
  }
  to {
    transform: translateY(0);
  }
}

.warning-icon {
  display: inline-block;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

/* Add padding to body when banner is visible */
body.warning-active {
  padding-top: 60px;
}

/* Prevent text selection and copying */
.no-copy {
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
  -webkit-touch-callout: none;
}

/* Fullscreen Modal Styles */
.fullscreen-modal {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background-color: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(8px);
}

.modal-content {
  background: white;
  border-radius: 1.5rem;
  padding: 2rem;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.quiz-content {
  filter: blur(5px);
  pointer-events: none;
  user-select: none;
}

.quiz-content.active {
  filter: none;
  pointer-events: auto;
  user-select: auto;
}
</style>
</head>
<body class="flex min-h-screen warning-active">

<!-- Fullscreen Required Modal -->
<div id="fullscreenModal" class="fullscreen-modal">
  <div class="modal-content text-center">
    <div class="mb-6">
      <div class="w-20 h-20 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
        </svg>
      </div>
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Fullscreen Required</h2>
      <p class="text-gray-600 mb-6">
        To maintain quiz integrity and prevent cheating, you must take this quiz in fullscreen mode.
      </p>
    </div>

    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 text-left">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-yellow-700">
            <strong>Important:</strong> Exiting fullscreen or switching tabs will automatically submit your quiz.
          </p>
        </div>
      </div>
    </div>

    <button id="enterFullscreenBtn" 
            class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all transform hover:scale-105">
      🚀 Enter Fullscreen & Start Quiz
    </button>

    <button onclick="window.location.href='dashboard.php'" 
            class="w-full mt-3 bg-gray-100 text-gray-700 font-medium py-2 px-6 rounded-lg hover:bg-gray-200 transition">
      ← Back to Dashboard
    </button>
  </div>
</div>

<!-- Warning Banner -->
<div class="warning-banner" style="display: none;" id="warningBanner">
  <span class="warning-icon">⚠️</span>
  <span class="ml-2">Alt tabs and exiting fullscreen are not allowed! Your quiz will be automatically submitted if you switch tabs or exit fullscreen!</span>
  <span class="warning-icon ml-2">⚠️</span>
</div>

<!-- Quiz Content (Initially Blurred) -->
<div id="quizContent" class="quiz-content flex min-h-screen w-full">
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
        <input type="hidden" name="auto_submitted" value="0" id="autoSubmitFlag">

        <?php foreach ($questions as $index => $q): ?>
        <div id="q<?= $q['id'] ?>" class="question-card bg-white rounded-xl p-6 hover:bg-blue-50 transition-colors no-copy">
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
</div>

<!-- Scripts -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const totalQuestions = <?= count($questions) ?>;
  const submitBtn = document.getElementById("submitBtn");
  const answeredCountSpan = document.getElementById("answeredCount");
  const progressBar = document.getElementById("progressBar");
  const radios = document.querySelectorAll(".answer-radio");
  const navButtons = document.querySelectorAll(".question-nav button");
  const quizForm = document.getElementById("quizForm");
  const autoSubmitFlag = document.getElementById("autoSubmitFlag");
  const fullscreenModal = document.getElementById("fullscreenModal");
  const enterFullscreenBtn = document.getElementById("enterFullscreenBtn");
  const quizContent = document.getElementById("quizContent");
  const warningBanner = document.getElementById("warningBanner");
  
  let hasLeftPage = false;
  let fullscreenInitialized = false;
  let quizStarted = false;

  // ========== FULLSCREEN MODAL ==========
  function enterFullscreen() {
    const elem = document.documentElement;
    if (elem.requestFullscreen) {
      return elem.requestFullscreen();
    } else if (elem.webkitRequestFullscreen) { /* Safari */
      return elem.webkitRequestFullscreen();
    } else if (elem.msRequestFullscreen) { /* IE11 */
      return elem.msRequestFullscreen();
    } else if (elem.mozRequestFullScreen) { /* Firefox */
      return elem.mozRequestFullScreen();
    }
    return Promise.reject(new Error('Fullscreen not supported'));
  }

  enterFullscreenBtn.addEventListener('click', () => {
    enterFullscreen()
      .then(() => {
        // Wait for fullscreen to be confirmed
        setTimeout(() => {
          const isFullscreen = !!(document.fullscreenElement || 
                                  document.webkitFullscreenElement || 
                                  document.mozFullScreenElement || 
                                  document.msFullscreenElement);
          
          if (isFullscreen) {
            // Successfully entered fullscreen
            fullscreenModal.style.display = 'none';
            quizContent.classList.add('active');
            warningBanner.style.display = 'block';
            quizStarted = true;
            fullscreenInitialized = true;
            
            // Start timer if applicable
            <?php if ($timeLimit > 0): ?>
            startTimer();
            <?php endif; ?>
          } else {
            alert('⚠️ Please allow fullscreen to start the quiz.');
          }
        }, 100);
      })
      .catch((error) => {
        console.error('Fullscreen error:', error);
        alert('⚠️ Unable to enter fullscreen. Please allow fullscreen permissions to take the quiz.');
      });
  });

  // Monitor fullscreen changes
  document.addEventListener("fullscreenchange", handleFullscreenChange);
  document.addEventListener("webkitfullscreenchange", handleFullscreenChange);
  document.addEventListener("mozfullscreenchange", handleFullscreenChange);
  document.addEventListener("MSFullscreenChange", handleFullscreenChange);

  function handleFullscreenChange() {
    const isFullscreen = !!(document.fullscreenElement || 
                            document.webkitFullscreenElement || 
                            document.mozFullScreenElement || 
                            document.msFullscreenElement);
    
    if (isFullscreen && !fullscreenInitialized) {
      fullscreenInitialized = true;
    } else if (!isFullscreen && fullscreenInitialized && quizStarted && !hasLeftPage) {
      hasLeftPage = true;
      autoSubmitFlag.value = "1";
      alert("⚠️ You exited fullscreen mode! Your quiz is being automatically submitted.");
      quizForm.submit();
    }
  }

  // Prevent keyboard shortcuts that exit fullscreen
  document.addEventListener("keydown", (e) => {
    if (!quizStarted) return; // Only block after quiz starts
    
    // Fullscreen controls
    if (e.key === "F11") {
      e.preventDefault();
      alert("⚠️ You cannot exit fullscreen during the quiz!");
    }
    if (e.key === "Escape" || e.key === "Esc") {
      e.preventDefault();
      alert("⚠️ Escape key is disabled during the quiz!");
    }
    if ((e.altKey && e.key === "Tab") || (e.ctrlKey && e.key === "Tab")) {
      e.preventDefault();
      alert("⚠️ Tab switching is not allowed during the quiz!");
    }

    // Copy/Paste/Select All prevention
    if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C')) {
      e.preventDefault();
      alert("⚠️ Copying is disabled during the quiz.");
    }
    if ((e.ctrlKey || e.metaKey) && (e.key === 'x' || e.key === 'X')) {
      e.preventDefault();
      alert("⚠️ Cutting is disabled during the quiz.");
    }
    if ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) {
      e.preventDefault();
      alert("⚠️ Pasting is disabled during the quiz.");
    }
    if ((e.ctrlKey || e.metaKey) && (e.key === 'a' || e.key === 'A')) {
      e.preventDefault();
      alert("⚠️ Select all is disabled during the quiz.");
    }
  });

  // ========== PROGRESS TRACKING ==========
  function updateProgress() {
    const answered = new Set();
    radios.forEach(r => {
      if (r.checked) {
        const qid = r.name.match(/\[(\d+)\]/)[1];
        answered.add(qid);

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

  // ========== QUESTION NAVIGATION ==========
  navButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const target = document.getElementById(btn.dataset.qid);
      if(target) target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  // ========== ALT-TAB DETECTION ==========
  document.addEventListener("visibilitychange", () => {
    if (!quizStarted) return;
    
    if (document.hidden && !hasLeftPage) {
      hasLeftPage = true;
      autoSubmitFlag.value = "1";
      
      setTimeout(() => {
        alert("⚠️ You switched tabs! Your quiz is being automatically submitted.");
        quizForm.submit();
      }, 100);
    }
  });

  window.addEventListener("blur", () => {
    if (!quizStarted || hasLeftPage) return;
    
    hasLeftPage = true;
    autoSubmitFlag.value = "1";
    
    setTimeout(() => {
      alert("⚠️ You switched away from the quiz! Your quiz is being automatically submitted.");
      quizForm.submit();
    }, 100);
  });

  // ========== SECURITY MEASURES ==========
  // Prevent right-click
  document.addEventListener("contextmenu", (e) => {
    if (quizStarted) {
      e.preventDefault();
      alert("Right-click is disabled during the quiz.");
    }
  });

  // Prevent copying
  document.addEventListener("copy", (e) => {
    if (quizStarted) {
      e.preventDefault();
      alert("⚠️ Copying is disabled during the quiz.");
    }
  });

  // Prevent cutting
  document.addEventListener("cut", (e) => {
    if (quizStarted) {
      e.preventDefault();
      alert("⚠️ Cutting is disabled during the quiz.");
    }
  });

  // Prevent pasting
  document.addEventListener("paste", (e) => {
    if (quizStarted) {
      e.preventDefault();
      alert("⚠️ Pasting is disabled during the quiz.");
    }
  });

  // Prevent text selection with mouse drag
  document.addEventListener("selectstart", (e) => {
    if (quizStarted && e.target.tagName !== 'INPUT') {
      e.preventDefault();
    }
  });

  const devToolsCheck = () => {
    if (!quizStarted) return;
    
    const threshold = 160;
    if (window.outerWidth - window.innerWidth > threshold || 
        window.outerHeight - window.innerHeight > threshold) {
      if (!hasLeftPage) {
        hasLeftPage = true;
        autoSubmitFlag.value = "1";
        alert("⚠️ Developer tools detected! Your quiz is being automatically submitted.");
        quizForm.submit();
      }
    }
  };
  
  setInterval(devToolsCheck, 1000);

  // ========== TIMER ==========
  <?php if ($timeLimit > 0): ?>
  let remaining = <?= $remaining ?>;
  const timerDisplay = document.getElementById("timer");
  let timerInterval;

  function updateTimer() {
    if (remaining <= 0) {
      timerDisplay.textContent = "00:00";
      alert("⏰ Time's up! Submitting your quiz...");
      quizForm.submit();
      clearInterval(timerInterval);
      return;
    }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerDisplay.textContent = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    remaining--;
  }

  function startTimer() {
    timerInterval = setInterval(updateTimer, 1000);
    updateTimer();
  }
  <?php endif; ?>
});
</script>
</body>
</html>