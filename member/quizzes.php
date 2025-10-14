<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// Get student info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// Default avatar
if (!empty($student['gender'])) {
    if (strtolower($student['gender']) === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif (strtolower($student['gender']) === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// ✅ Get quizzes WITH attempt_id
$stmt = $conn->prepare("
  SELECT q.id, q.title, q.publish_time, q.deadline_time,
         qa.id AS attempt_id,
         COALESCE(qa.status, 'Pending') AS status
  FROM quizzes q
  LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ?
  ORDER BY q.publish_time DESC
");
$stmt->execute([$studentId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: true, filter: 'all', search: '' }">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quizzes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="relative min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100">

  <!-- Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
       x-show="sidebarOpen" x-transition.opacity
       @click="sidebarOpen = false"></div>

  <!-- Sidebar -->
  <aside class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
         :class="{
           'w-64': !collapsed,
           'w-20': collapsed,
           '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
         }"
         x-show="sidebarOpen || window.innerWidth >= 768">

    <!-- Profile -->
    <div class="flex items-center mb-10 transition-all"
         :class="collapsed ? 'justify-center' : 'space-x-4'">
      <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
           class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
      <div x-show="!collapsed" class="flex flex-col overflow-hidden">
        <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></p>
        <p class="text-sm text-gray-500">Nursing Student</p>
        <a href="profile_edit.php" class="text-xs mt-1 text-teal-600 hover:underline">Edit Profile</a>
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
        <a href="quizzes.php" class="flex items-center p-2 rounded-lg bg-teal-100 text-teal-700 font-semibold transition">
          <span class="text-xl">📝</span>
          <span x-show="!collapsed" class="ml-3">Quizzes</span>
        </a>
        <a href="resources.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📚</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Resources</span>
        </a>
      </div>
    </nav>

    <!-- Collapse -->
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

  <!-- Content -->
  <div class="relative z-10 transition-all"
       :class="{ 'md:ml-64': !collapsed && window.innerWidth >= 768, 'md:ml-20': collapsed && window.innerWidth >= 768 }">

    <!-- Mobile header -->
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">☰</button>
      <h1 class="text-lg font-semibold text-gray-800">Quizzes</h1>
    </header>

    <!-- Main -->
    <main class="p-6 space-y-10">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6">📝 Quizzes</h1>

      <!-- Filters -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div class="flex space-x-2">
          <button @click="filter='all'" :class="filter==='all' ? 'bg-teal-600 text-white' : 'bg-gray-100'"
                  class="px-3 py-1 rounded-lg text-sm font-medium">All</button>
          <button @click="filter='pending'" :class="filter==='pending' ? 'bg-teal-600 text-white' : 'bg-gray-100'"
                  class="px-3 py-1 rounded-lg text-sm font-medium">Pending</button>
          <button @click="filter='failed'" :class="filter==='failed' ? 'bg-teal-600 text-white' : 'bg-gray-100'"
                  class="px-3 py-1 rounded-lg text-sm font-medium">Failed</button>
          <button @click="filter='completed'" :class="filter==='completed' ? 'bg-teal-600 text-white' : 'bg-gray-100'"
                  class="px-3 py-1 rounded-lg text-sm font-medium">Completed</button>
        </div>
        <input type="text" placeholder="Search quizzes..." x-model="search"
               class="w-full sm:w-64 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" />
      </div>

      <!-- Quiz Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (empty($quizzes)): ?>
          <p class="text-gray-500 col-span-full text-center">No quizzes available yet.</p>
        <?php else: ?>
          <?php foreach ($quizzes as $quiz): 
            $status = strtolower($quiz['status']);
            $statusClass = match ($status) {
              'completed' => 'bg-green-100 text-green-700',
              'failed' => 'bg-red-100 text-red-700',
              'pending' => 'bg-yellow-100 text-yellow-700',
              default => 'bg-gray-100 text-gray-700'
            };
          ?>
          <div class="bg-white/90 backdrop-blur-xl rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all flex flex-col"
               x-show="(filter==='all' || filter==='<?= $status ?>') && ('<?= strtolower(htmlspecialchars($quiz['title'])) ?>'.includes(search.toLowerCase()))">
            <div class="h-44 bg-gradient-to-br from-teal-200 to-blue-200 flex items-center justify-center text-5xl">
              📝
            </div>
            <div class="flex flex-col flex-grow justify-between p-5">
              <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2"><?= htmlspecialchars($quiz['title']) ?></h3>
                <p class="text-sm text-gray-600 mb-2">📅 <?= htmlspecialchars($quiz['publish_time']) ?></p>
                <p class="text-sm text-red-600 mb-4">⏰ <?= htmlspecialchars($quiz['deadline_time']) ?></p>
              </div>

              <div class="flex items-center justify-between mt-3">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $statusClass ?>">
                  <?= htmlspecialchars(ucfirst($quiz['status'])) ?>
                </span>
                <?php if ($status === 'pending'): ?>
                  <a href="take_quiz.php?id=<?= $quiz['id'] ?>"
                     class="bg-gradient-to-r from-teal-600 to-blue-600 text-white px-4 py-2 rounded-lg shadow hover:from-teal-700 hover:to-blue-700 transition text-sm font-medium">
                    Start Quiz
                  </a>
                <?php elseif ($status === 'failed'): ?>
                  <a href="take_quiz.php?id=<?= $quiz['id'] ?>"
                     class="bg-gradient-to-r from-orange-500 to-red-500 text-white px-4 py-2 rounded-lg shadow hover:from-orange-600 hover:to-red-600 transition text-sm font-medium">
                    Retry Quiz
                  </a>
                <?php elseif ($status === 'completed' && $quiz['attempt_id']): ?>
                  <a href="quiz_result.php?attempt_id=<?= $quiz['attempt_id'] ?>"
                     class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-300 transition text-sm font-medium">
                    View Results
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
</body>
</html>
