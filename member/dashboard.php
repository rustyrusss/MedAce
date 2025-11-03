<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/journey_fetch.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// Default avatar logic
if (!empty($student['gender'])) {
    $g = strtolower($student['gender']);
    if ($g === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif ($g === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}

$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// Fetch journey data
$journeyData = getStudentJourney($conn, $studentId);
$steps = $journeyData['steps'] ?? [];
$stats = $journeyData['stats'] ?? ['completed'=>0, 'total'=>1, 'current'=>0, 'pending'=>0, 'progress'=>0];

// Also fetch quizzes separately if dashboard wants distinct quiz section
$quizzes = $journeyData['quizzes'] ?? [];

// Fetch a daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: true }">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    html { scroll-behavior: smooth; }
  </style>
</head>
<body class="relative min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100">

  <!-- Overlay for small screens -->
  <div class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
       x-show="sidebarOpen"
       x-transition.opacity
       @click="sidebarOpen = false"></div>

  <!-- Sidebar -->
  <aside class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
         :class="{
           'w-64': !collapsed,
           'w-20': collapsed,
           '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
         }"
         x-show="sidebarOpen || window.innerWidth >= 768"
         x-transition>
    <!-- Profile in sidebar -->
    <div class="flex items-center mb-10 transition-all"
         :class="collapsed ? 'justify-center' : 'space-x-4'">
      <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
           class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
      <div x-show="!collapsed" class="flex flex-col overflow-hidden">
        <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></p>
        <p class="text-sm text-gray-500">Nursing Student</p>
        <a href="profile_edit.php" class="text-xs mt-1 text-teal-600 hover:underline">Edit Profile Picture</a>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
        <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">🏠</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Dashboard</span>
        </a>
        <a href="progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📊</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">My Progress</span>
        </a>
      </div>
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Learning</p>
        <a href="quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📝</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Quizzes</span>
        </a>
        <a href="resources.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📂</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Resources</span>
        </a>
      </div>
    </nav>

    <!-- Collapse / Expand button -->
    <button class="mt-5 flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition hidden md:flex"
            @click="collapsed = !collapsed">
      <svg xmlns="http://www.w3.org/2000/svg"
           class="h-6 w-6 text-gray-700 transform transition-transform"
           :class="collapsed ? 'rotate-180' : ''"
           fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
      </svg>
    </button>

    <!-- Logout -->
    <div class="mt-auto">
      <a href="../actions/logout_action.php"
         class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
        <span class="text-xl">🚪</span>
        <span x-show="!collapsed" class="ml-3 font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main content area -->
  <div class="relative z-10 transition-all"
       :class="{
         'md:ml-64': !collapsed && window.innerWidth >= 768,
         'md:ml-20': collapsed && window.innerWidth >= 768
       }">
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-7 w-7" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">Student Dashboard</h1>
    </header>

    <main class="p-4 sm:p-6 lg:p-8 space-y-8">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">
        Welcome, <?= htmlspecialchars(ucwords(strtolower($studentName))) ?> 👋
      </h1>

      <!-- Progress Tracker block -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-gray-800">📊 Progress Tracker</h2>
          <span class="text-sm font-medium text-gray-600">
            <?= $stats['completed'] ?>/<?= $stats['total'] ?> completed — <?= $stats['progress'] ?>%
          </span>
        </div>
        <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden mb-8">
          <div class="h-full bg-teal-600" style="width: <?= $stats['progress'] ?>%"></div>
        </div>
        <div class="flex flex-col sm:flex-row sm:space-x-4 gap-6">
          <?php foreach ($steps as $index => $step): ?>
            <?php
              $st = strtolower($step['status']);
              $isCompleted = ($st === 'completed');
              $isCurrent = ($st === 'current');
            ?>
            <div class="flex sm:flex-col items-center text-center flex-1 relative">
              <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center font-semibold
                  <?= $isCompleted
                       ? 'bg-green-500 border-green-600 text-white'
                       : ($isCurrent
                          ? 'bg-blue-500 border-blue-600 text-white ring-4 ring-blue-200'
                          : 'bg-gray-200 border-gray-400 text-gray-600') ?>">
                <?= $step['type'] === 'module' ? '📘' : '📝' ?>
              </div>
              <?php if ($index < count($steps) - 1): ?>
                <div class="hidden sm:block absolute top-6 left-full w-full h-1
                    <?= $isCompleted ? 'bg-green-500' : 'bg-gray-300' ?>"></div>
              <?php endif; ?>
              <span class="mt-3 text-sm font-medium text-gray-700"><?= htmlspecialchars($step['title']) ?></span>
              <span class="text-xs text-gray-500"><?= ucfirst($step['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quizzes Section -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">📝 Quizzes</h2>
        <?php if (empty($quizzes)): ?>
          <p class="text-gray-500">No quizzes available yet.</p>
        <?php else: ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($quizzes as $quiz): ?>
              <?php
                $st = strtolower($quiz['status']);
              ?>
              <div class="flex flex-col justify-between p-5 bg-white rounded-xl shadow hover:shadow-lg transition border border-gray-100">
                <h4 class="font-semibold text-gray-800 mb-2 text-lg"><?= htmlspecialchars($quiz['title']) ?></h4>

                <?php if (!empty($quiz['publish_time'])): ?>
                  <p class="text-sm text-gray-600 mb-1">
                    📅 Available: <?= date("F j, Y - g:i A", strtotime($quiz['publish_time'])) ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($quiz['deadline_time'])): ?>
                  <p class="text-sm text-red-600 font-medium mb-3">
                    ⏰ Deadline: <?= date("F j, Y - g:i A", strtotime($quiz['deadline_time'])) ?>
                  </p>
                <?php endif; ?>

                <span class="inline-block self-start px-3 py-1 rounded-full text-sm font-medium
                  <?= $st === 'completed'
                        ? 'bg-green-100 text-green-700'
                        : ($st === 'pending'
                          ? 'bg-yellow-100 text-yellow-700'
                          : 'bg-gray-100 text-gray-700') ?>">
                  <?= ucfirst($st) ?>
                </span>

                <div class="mt-4">
                  <a href="../member/take_quiz.php?id=<?= $quiz['id'] ?>"
                     class="block w-full text-center bg-gradient-to-r from-teal-600 to-blue-600 text-white px-4 py-2 rounded-lg shadow hover:from-teal-700 hover:to-blue-700 transition">
                    <?php
                      if ($st === 'completed') echo "Retake Quiz";
                      elseif ($st === 'failed') echo "Retry Quiz";
                      else echo "Start Quiz";
                    ?>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Daily Nursing Tip -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200 text-center max-w-xl mx-auto">
        <h3 class="text-lg font-semibold mb-3 text-teal-700">🌟 Daily Nursing Tip</h3>
        <p class="text-gray-700 text-lg italic">"<?= htmlspecialchars($dailyTip ?: 'Stay hydrated and keep learning!') ?>"</p>
      </div>
    </main>
  </div>
</body>
</html>
