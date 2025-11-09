<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/journey_fetch.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$journeyData = getStudentJourney($conn, $studentId);

// Separate modules & quizzes
$modules = $journeyData['modules'] ?? [];
$quizzes = $journeyData['quizzes'] ?? [];
$stats = $journeyData['stats'] ?? ['completed' => 0, 'total' => 0, 'progress' => 0];

// Student info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// Default avatar logic
if (!empty($student['gender'])) {
    $defaultAvatar = strtolower($student['gender']) === "male"
        ? "../assets/img/avatar_male.png"
        : (strtolower($student['gender']) === "female"
            ? "../assets/img/avatar_female.png"
            : "../assets/img/avatar_neutral.png");
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// Daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

// Stats for progress chart
$completedSteps = count(array_filter($journeyData['modules'], fn($s) => strtolower($s['status']) === 'completed')) +
                  count(array_filter($journeyData['quizzes'], fn($s) => strtolower($s['status']) === 'completed'));
$currentSteps = count(array_filter($journeyData['modules'], fn($s) => strtolower($s['status']) === 'current')) +
                count(array_filter($journeyData['quizzes'], fn($s) => strtolower($s['status']) === 'current'));
$pendingSteps = count(array_filter($journeyData['modules'], fn($s) => strtolower($s['status']) === 'pending')) +
                count(array_filter($journeyData['quizzes'], fn($s) => strtolower($s['status']) === 'pending'));
$totalSteps = max(count($journeyData['modules']) + count($journeyData['quizzes']), 1);
$progressPercent = round(($completedSteps / $totalSteps) * 100);
?>

<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: true, filter: 'all' }">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Progress Tracker | MedAce</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .chart-container { width: 220px; height: 220px; margin: auto; }
    [x-cloak] { display: none; }
  </style>
</head>
<body class="relative min-h-screen bg-[#D1EBEC]">


  <!-- Overlay for mobile -->
  <div class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
       x-show="sidebarOpen" x-transition.opacity
       @click="sidebarOpen = false"></div>

  <!-- Sidebar -->
  <aside class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
         :class="{ 'w-64': !collapsed, 'w-20': collapsed, '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768 }"
         x-show="sidebarOpen || window.innerWidth >= 768"
         x-transition>
    
    <!-- Profile -->
    <div class="flex items-center mb-10" :class="collapsed ? 'justify-center' : 'space-x-4'">
      <img src="<?= htmlspecialchars($profilePic) ?>" class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover" alt="avatar">
      <div x-show="!collapsed" class="flex flex-col overflow-hidden">
        <p class="text-xl font-bold"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></p>
        <p class="text-sm text-gray-500">Nursing Student</p>
        <a href="profile_edit.php" class="text-xs mt-1 text-teal-600 hover:underline">Edit Profile</a>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
      <div>
        <p class="text-xs uppercase text-gray-400 mb-2" x-show="!collapsed">Main</p>
        <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100">
          <span class="text-xl">🏠</span>
          <span x-show="!collapsed" class="ml-3 font-medium">Dashboard</span>
        </a>
        <a href="progress.php" class="flex items-center p-2 rounded-lg bg-teal-100 text-teal-700">
          <span class="text-xl">📊</span>
          <span x-show="!collapsed" class="ml-3 font-medium">My Progress</span>
        </a>
      </div>

      <div>
        <p class="text-xs uppercase text-gray-400 mb-2" x-show="!collapsed">Learning</p>
        <a href="quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100">
          <span class="text-xl">📝</span>
          <span x-show="!collapsed" class="ml-3 font-medium">Quizzes</span>
        </a>
        <a href="resources.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100">
          <span class="text-xl">📚</span>
          <span x-show="!collapsed" class="ml-3 font-medium">Resources</span>
        </a>
      </div>
    </nav>

    <!-- Collapse -->
    <button class="mt-5 flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition hidden md:flex"
            @click="collapsed = !collapsed">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700 transform transition-transform"
           :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
      </svg>
    </button>

    <!-- Logout -->
    <div class="mt-auto">
      <a href="../actions/logout_action.php" class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">
        <span class="text-xl">🚪</span>
        <span x-show="!collapsed" class="ml-3 font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="transition-all" :class="{ 'md:ml-64': !collapsed, 'md:ml-20': collapsed }">
    <main class="p-6 sm:p-10 space-y-10">
      <h1 class="text-2xl font-bold text-gray-800">Progress Overview 👩‍⚕️</h1>

      <!-- Chart -->
      <div class="bg-white/80 backdrop-blur-xl p-8 rounded-2xl shadow-lg border border-gray-200 flex flex-col lg:flex-row items-center justify-around">
        <div class="chart-container relative">
          <canvas id="progressChart"></canvas>
          <div class="absolute inset-0 flex items-center justify-center font-bold text-lg text-teal-700"><?= $progressPercent ?>%</div>
        </div>
        <div class="flex-1 text-center lg:text-left mt-6 lg:mt-0 space-y-3">
          <h2 class="text-xl font-semibold">Your Learning Progress</h2>
          <p class="text-gray-600">You’ve completed <?= $completedSteps ?> of <?= $totalSteps ?> tasks.</p>
          <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-teal-500 to-blue-600 transition-all duration-700" style="width: <?= $progressPercent ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Filter Buttons -->
      <div class="flex flex-wrap gap-2 justify-center lg:justify-start">
        <button @click="filter = 'all'" :class="filter==='all' ? 'bg-teal-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-1.5 rounded-full font-medium transition">All</button>
        <button @click="filter = 'completed'" :class="filter==='completed' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-1.5 rounded-full font-medium transition">Completed</button>
        <button @click="filter = 'current'" :class="filter==='current' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-1.5 rounded-full font-medium transition">Current</button>
        <button @click="filter = 'pending'" :class="filter==='pending' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-1.5 rounded-full font-medium transition">Pending</button>
      </div>

      <!-- Activity Tracker -->
      <div class="grid md:grid-cols-2 gap-6">
        <!-- Modules -->
        <div class="bg-white p-6 rounded-2xl shadow-sm">
            <h3 class="text-lg font-semibold text-blue-600 mb-4">📘 Modules</h3>
            <?php if (!empty($journeyData['modules'])): ?>
                <ul class="space-y-3">
                    <?php foreach ($journeyData['modules'] as $module): ?>
                        <li 
                          x-show="filter === 'all' || filter === '<?= strtolower($module['status']) ?>'" x-cloak
                          class="border rounded-xl p-4 hover:bg-blue-50 transition flex justify-between items-center">
                            <div>
                                <h4 class="font-medium text-gray-800"><?= htmlspecialchars($module['title']) ?></h4>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($module['description'] ?? '') ?></p>
                            </div>
                            <span class="text-xs px-3 py-1 rounded-full 
                                <?= strtolower($module['status']) === 'completed' ? 'bg-green-100 text-green-700' : 
                                    (strtolower($module['status']) === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') ?>">
                                <?= htmlspecialchars($module['status']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500">No modules found.</p>
            <?php endif; ?>
        </div>

        <!-- Quizzes -->
        <div class="bg-white p-6 rounded-2xl shadow-sm">
            <h3 class="text-lg font-semibold text-pink-600 mb-4">🧩 Quizzes</h3>
            <?php if (!empty($journeyData['quizzes'])): ?>
                <ul class="space-y-3">
                    <?php foreach ($journeyData['quizzes'] as $quiz): ?>
                        <li 
                          x-show="filter === 'all' || filter === '<?= strtolower($quiz['status']) ?>'" x-cloak
                          class="border rounded-xl p-4 hover:bg-pink-50 transition flex justify-between items-center">
                            <div>
                                <h4 class="font-medium text-gray-800"><?= htmlspecialchars($quiz['title']) ?></h4>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($quiz['description'] ?? '') ?></p>
                            </div>
                            <span class="text-xs px-3 py-1 rounded-full 
                                <?= strtolower($quiz['status']) === 'completed' ? 'bg-green-100 text-green-700' : 
                                    (strtolower($quiz['status']) === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') ?>">
                                <?= htmlspecialchars($quiz['status']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500">No quizzes found.</p>
            <?php endif; ?>
        </div>
      </div>

      <!-- Daily Tip -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200 text-center max-w-xl mx-auto">
        <h3 class="text-lg font-semibold mb-3 text-teal-700">🌟 Daily Nursing Tip</h3>
        <p class="text-gray-700 italic">"<?= htmlspecialchars($dailyTip ?: 'Stay confident and keep learning!') ?>"</p>
      </div>
    </main>
  </div>

  <script>
    const ctx = document.getElementById('progressChart');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Completed', 'Current', 'Pending'],
        datasets: [{
          data: [<?= $completedSteps ?>, <?= $currentSteps ?>, <?= $pendingSteps ?>],
          backgroundColor: ['#14b8a6', '#3b82f6', '#facc15'],
          borderWidth: 2
        }]
      },
      options: {
        cutout: '70%',
        plugins: {
          legend: { display: true, position: 'bottom' }
        }
      }
    });
  </script>
</body>
</html>
