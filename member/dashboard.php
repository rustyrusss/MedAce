<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header("Location: ../public/index.php");
  exit();
}

$studentId = $_SESSION['user_id'];

// ✅ Get student info
$stmt = $conn->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// ✅ Get quizzes + attempts
$stmt = $conn->prepare("
  SELECT q.id, q.title, COALESCE(qa.status, 'Pending') AS status
  FROM quizzes q
  LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ?
");
$stmt->execute([$studentId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get learning journey (modules + progress)
$stmt = $conn->prepare("
  SELECT m.id, m.title, COALESCE(sp.status, 'Pending') AS status
  FROM modules m
  LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
  ORDER BY m.order_number ASC
");
$stmt->execute([$studentId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mix modules and quizzes into one journey
$journey = [];
foreach ($modules as $module) {
  $journey[] = [
    "title" => $module['title'],
    "type"  => "module",
    "status"=> $module['status']
  ];
  foreach ($quizzes as $quiz) {
    // If quiz matches module, attach (requires module_id in quizzes)
    // For now, show all quizzes after each module
    $journey[] = [
      "title" => $quiz['title'],
      "type"  => "quiz",
      "status"=> $quiz['status']
    ];
  }
}

// ✅ Progress calculation
$completedSteps = count(array_filter($journey, fn($step) => $step['status'] === 'Completed'));
$totalSteps     = count($journey) > 0 ? count($journey) : 1;
$progressPercent = round(($completedSteps / $totalSteps) * 100);

// ✅ Daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: false }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="relative min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 overflow-hidden">

  <!-- Overlay for mobile -->
  <div 
    class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false">
  </div>

  <!-- Sidebar -->
  <aside 
    class="fixed z-30 inset-y-0 left-0 bg-white/90 backdrop-blur-xl shadow-xl border-r border-gray-200 p-4 flex flex-col transition-all duration-300"
    :class="{
      'w-64': !collapsed, 
      'w-20': collapsed, 
      '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
    }"
    x-show="sidebarOpen || window.innerWidth >= 768"
    x-transition:enter="transform transition duration-300"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transform transition duration-300"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
  >
    <!-- Profile -->
    <div class="flex items-center space-x-3 mb-10" :class="collapsed ? 'justify-center' : ''">
      <img src="https://i.pravatar.cc/50?img=12" alt="avatar" class="w-12 h-12 rounded-full border-2 border-teal-400 shadow">
      <div x-show="!collapsed" class="overflow-hidden">
        <p class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($studentName) ?></p>
        <p class="text-sm text-gray-500">Nursing Student</p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
        <a href="dashboard.php" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gradient-to-r hover:from-teal-100 hover:to-blue-100 transition">
          <span>🏠</span><span x-show="!collapsed">Dashboard</span>
        </a>
        <a href="progress.php" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gradient-to-r hover:from-teal-100 hover:to-blue-100 transition">
          <span>📊</span><span x-show="!collapsed">My Progress</span>
        </a>
      </div>

      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Learning</p>
        <a href="quiz.php" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gradient-to-r hover:from-teal-100 hover:to-blue-100 transition">
          <span>📝</span><span x-show="!collapsed">Quizzes</span>
        </a>
        <a href="resources.php" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gradient-to-r hover:from-teal-100 hover:to-blue-100 transition">
          <span>📂</span><span x-show="!collapsed">Resources</span>
        </a>
      </div>
    </nav>

    <!-- Collapse -->
    <button 
      class="mt-4 flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition hidden md:flex"
      @click="collapsed = !collapsed">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700 transform transition-transform"
        :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
      </svg>
    </button>

    <!-- Logout -->
    <div class="mt-auto">
      <a href="../actions/logout_action.php" 
         class="flex items-center space-x-3 p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
        <span>🚪</span><span x-show="!collapsed">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div 
    class="relative z-10 transition-all"
    :class="{
      'md:ml-64': !collapsed && window.innerWidth >= 768, 
      'md:ml-20': collapsed && window.innerWidth >= 768
    }">

    <!-- Top Bar (mobile) -->
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">MedAce Dashboard</h1>
    </header>

    <!-- Main Content -->
    <main class="p-4 sm:p-6 lg:p-8 space-y-6">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Welcome, <?= htmlspecialchars($studentName) ?> 👋</h1>

      <!-- Progress Tracker -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200">
        <h2 class="text-lg font-semibold mb-6">📊 Your Progress Tracker</h2>
        <p class="mb-6 text-gray-600">
          Progress: 
          <span class="font-semibold text-teal-600"><?= $completedSteps ?>/<?= $totalSteps ?></span> 
          steps completed (<?= $progressPercent ?>%).
        </p>

        <!-- Timeline -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
          <?php foreach ($journey as $index => $step): ?>
            <div class="flex sm:flex-col items-center text-center relative flex-1">
              <!-- Step Circle -->
              <div class="flex items-center justify-center w-12 h-12 rounded-full border-2 font-bold
                <?php if ($step['status'] === 'Completed'): ?>
                  bg-green-500 border-green-600 text-white
                <?php elseif ($step['status'] === 'Current'): ?>
                  bg-blue-500 border-blue-600 text-white ring-4 ring-blue-200
                <?php else: ?>
                  bg-gray-200 border-gray-400 text-gray-600
                <?php endif; ?>">
                <?= $step['type'] === 'module' ? '📘' : '📝' ?>
              </div>

              <!-- Connector Line -->
              <?php if ($index < count($journey) - 1): ?>
                <div class="hidden sm:block absolute top-6 left-full w-full h-1 
                  <?= $step['status'] === 'Completed' ? 'bg-green-500' : 'bg-gray-300' ?>">
                </div>
              <?php endif; ?>

              <!-- Step Label -->
              <span class="mt-2 sm:mt-4 text-sm font-medium text-gray-700"><?= htmlspecialchars($step['title']) ?></span>
              <span class="text-xs text-gray-500"><?= htmlspecialchars($step['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quizzes -->
    <!-- Quizzes -->
<div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200">
  <h2 class="text-lg font-semibold mb-4">📝 Quizzes</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($quizzes as $quiz): ?>
      <div class="flex flex-col justify-between p-5 bg-white rounded-xl shadow border hover:shadow-lg transition">
        <h4 class="font-semibold text-gray-800 mb-3 text-lg"><?= htmlspecialchars($quiz['title']) ?></h4>
        <span class="inline-block self-start px-3 py-1 mb-4 rounded-full text-sm font-medium
          <?php if ($quiz['status'] === 'Completed') echo 'bg-green-100 text-green-700'; ?>
          <?php if ($quiz['status'] === 'Pending') echo 'bg-yellow-100 text-yellow-700'; ?>
          <?php if ($quiz['status'] === 'Failed') echo 'bg-red-100 text-red-700'; ?>">
          <?= htmlspecialchars($quiz['status']) ?>
        </span>
        <div class="mt-auto">
          <a href="../actions/attempt_quiz.php $quiz['id'] ?>" 
             class="block text-center bg-gradient-to-r from-teal-600 to-blue-600 text-white px-4 py-2 rounded-lg shadow hover:from-teal-700 hover:to-blue-700 transition">
            <?php if ($quiz['status'] === 'Completed'): ?>
              Retake Quiz
            <?php elseif ($quiz['status'] === 'Failed'): ?>
              Retry Quiz
            <?php else: ?>
              Take Quiz
            <?php endif; ?>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>


      <!-- Daily Tip -->
      <div class="bg-gradient-to-r from-indigo-500 to-teal-500 text-white p-5 rounded-2xl shadow-lg">
        <h3 class="font-semibold text-lg mb-2">💡 Nursing Tip of the Day</h3>
        <p><?= htmlspecialchars($dailyTip) ?></p>
      </div>
    </main>
  </div>
</body>
</html>
