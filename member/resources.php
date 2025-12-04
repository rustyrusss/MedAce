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

// Profile picture
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// FIXED: Fetch all available modules (both 'active' and 'published' status to match view_module.php)
$stmt = $conn->prepare("
    SELECT 
        m.id, 
        m.title, 
        m.description, 
        m.content, 
        COALESCE(sp.status, 'Pending') AS status
    FROM modules m
    LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
    WHERE m.status IN ('active', 'published')
    ORDER BY m.display_order ASC, m.created_at DESC
");
$stmt->execute([$studentId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

// Get success/error messages from session
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Learning Resources</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="relative min-h-screen bg-[#D1EBEC]">

  <!-- Overlay (for mobile sidebar) -->
  <div 
    class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false">
  </div>

  <!-- Sidebar -->
  <aside
    class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
    :class="{
      'w-64': !collapsed,
      'w-20': collapsed,
      '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
    }"
    x-show="sidebarOpen || window.innerWidth >= 768"
  >
    <!-- Profile -->
    <div class="flex items-center mb-10 transition-all" :class="collapsed ? 'justify-center' : 'space-x-4'">
      <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
           class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100">
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

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">My Progress</span>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="resources.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-book text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Resources</span>
                </a>
            </nav>

            <div class="px-3 py-4 border-t border-gray-200">
                <a href="../actions/logout_action.php" class="flex items-center space-x-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-all">
                    <i class="fas fa-sign-out-alt w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Logout</span>
                </a>
            </div>
        </div>
    </aside>

  <!-- Content -->
  <div class="relative z-10 transition-all"
       :class="{
         'md:ml-64': !collapsed && window.innerWidth >= 768,
         'md:ml-20': collapsed && window.innerWidth >= 768
       }">

    <!-- Mobile Header -->
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">Learning Resources</h1>
    </header>

    <!-- Main -->
    <main class="p-6 space-y-10">

      <!-- Page Header -->
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">📘 Available Learning Modules</h1>

      <!-- Filters and Search -->
      <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex flex-wrap gap-3">
          <button @click="activeFilter = 'All'" :class="activeFilter === 'All' ? 'bg-teal-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                  class="px-3 py-1 rounded-md text-sm font-medium border border-gray-200">All</button>
          <button @click="activeFilter = 'Pending'" :class="activeFilter === 'Pending' ? 'bg-teal-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                  class="px-3 py-1 rounded-md text-sm font-medium border border-gray-200">Pending</button>
          <button @click="activeFilter = 'In Progress'" :class="activeFilter === 'In Progress' ? 'bg-teal-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                  class="px-3 py-1 rounded-md text-sm font-medium border border-gray-200">In Progress</button>
          <button @click="activeFilter = 'Completed'" :class="activeFilter === 'Completed' ? 'bg-teal-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                  class="px-3 py-1 rounded-md text-sm font-medium border border-gray-200">Completed</button>
        </div>

        <input type="text" placeholder="Search modules..." x-model="searchQuery"
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400 w-full sm:w-64">
      </div>

      <!-- Modules Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (empty($modules)): ?>
          <p class="text-gray-500 col-span-full text-center">No modules available yet.</p>
        <?php else: ?>
          <?php foreach ($modules as $module): ?>
            <?php
              $coverImage = "../assets/img/module/module_default.jpg";
              $status = strtolower($module['status']);
              $statusClass = match ($status) {
                'completed' => 'bg-green-100 text-green-700',
                'in progress' => 'bg-blue-100 text-blue-700',
                'pending' => 'bg-yellow-100 text-yellow-700',
                default => 'bg-gray-100 text-gray-700'
              };
            ?>
            <div class="bg-white/90 backdrop-blur-xl rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all flex flex-col"
                 x-show="(activeFilter === 'All' || activeFilter === '<?= ucfirst($status) ?>') && 
                          (searchQuery === '' || '<?= strtolower($module['title']) ?>'.includes(searchQuery.toLowerCase()))">
              <div class="h-44 overflow-hidden relative group">
                <img src="<?= $coverImage ?>" alt="<?= htmlspecialchars($module['title']) ?>"
                     class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500 ease-out">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
              </div>

              <div class="flex flex-col flex-grow justify-between p-5">
                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-2"><?= htmlspecialchars($module['title']) ?></h3>
                  <p class="text-sm text-gray-600 mb-4 line-clamp-3">
                    <?= htmlspecialchars($module['description'] ?: "No description available.") ?>
                  </p>
                </div>

                <div class="flex items-center justify-between mt-3">
                  <span class="px-3 py-1 rounded-full text-sm font-medium <?= $statusClass ?>">
                    <?= htmlspecialchars(ucwords($module['status'])) ?>
                  </span>
                  <a href="view_module.php?id=<?= $module['id'] ?>"
                     class="bg-gradient-to-r from-teal-600 to-blue-600 text-white px-4 py-2 rounded-lg shadow hover:from-teal-700 hover:to-blue-700 transition text-sm font-medium">
                    Start Lesson
                  </a>
                </div>
              </div>
            </div>
            <?php endif; ?>

      <!-- Daily Tip -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200 text-center max-w-xl mx-auto">
        <h3 class="text-lg font-semibold mb-3 text-teal-700">🌟 Daily Nursing Tip</h3>
        <p class="text-gray-700 text-lg italic">
          "<?= htmlspecialchars($dailyTip ?: "Stay focused and keep growing!") ?>"
        </p>
      </div>
    </main>
  </div>
</body>
</html>