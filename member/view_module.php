<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get module ID
if (!isset($_GET['id'])) {
    header("Location: resources.php");
    exit();
}
$moduleId = intval($_GET['id']);

// Fetch module
$stmt = $conn->prepare("SELECT * FROM modules WHERE id = ?");
$stmt->execute([$moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$module) {
    die("Module not found.");
}

// Insert/update progress
try {
    $stmt = $conn->prepare("
        INSERT INTO module_progress (user_id, module_id, status, started_at)
        VALUES (?, ?, 'started', NOW())
        ON DUPLICATE KEY UPDATE started_at = NOW()
    ");
    $stmt->execute([$userId, $moduleId]);
} catch (PDOException $e) {
    // ignore or log
}

// Fetch student info for sidebar
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// Avatar logic
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

// Prepare viewer URL or fallback
$contentPath = $module['content'];  // e.g. "uploads/module1.pptx" or PDF
$fullUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/" . $contentPath;
// Note: ensure $fullUrl is publicly accessible

// Try Google Docs viewer (works for PPTX, PDF, etc.)
$googleViewerUrl = "https://docs.google.com/gview?url=" . urlencode($fullUrl) . "&embedded=true";

// Alternatively, for Office Online embed
$officeViewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode($fullUrl);

?>
<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: true }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($module['title']) ?> | Module</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</head>
<body class="relative min-h-screen bg-[#D1EBEC]">

  <!-- Overlay for mobile -->
  <div class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
       x-show="sidebarOpen"
       x-transition.opacity
       @click="sidebarOpen = false"></div>

  <!-- Sidebar (same as before) -->
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

    <!-- Nav -->
    <nav class="flex-1 space-y-6">
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
        <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100">
          <span class="text-xl">🏠</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Dashboard</span>
        </a>
        <a href="progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100">
          <span class="text-xl">📊</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">My Progress</span>
        </a>
      </div>
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Learning</p>
        <a href="quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100">
          <span class="text-xl">📝</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Quizzes</span>
        </a>
        <a href="resources.php" class="flex items-center p-2 rounded-lg bg-teal-100 text-teal-700 font-semibold">
          <span class="text-xl">📚</span>
          <span x-show="!collapsed" class="ml-3">Resources</span>
        </a>
      </div>
    </nav>

    <!-- Collapse button -->
    <button
      class="mt-5 flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 hidden md:flex"
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
         class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">
        <span class="text-xl">🚪</span>
        <span x-show="!collapsed" class="ml-3 font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main container -->
  <div class="relative z-10 transition-all"
       :class="{
         'md:ml-64': !collapsed && window.innerWidth >= 768,
         'md:ml-20': collapsed && window.innerWidth >= 768
       }">

    <!-- Mobile header -->
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">Module: <?= htmlspecialchars($module['title']) ?></h1>
    </header>

    <main class="p-6 space-y-10">
      <!-- Page header -->
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= htmlspecialchars($module['title']) ?></h1>

      <!-- (Optional) Filters / controls — e.g. for multiple parts, quizzes, etc. -->
      <!-- If not needed, you can omit or leave empty -->
      <div class="flex justify-between items-center">
        <!-- You can add filters or buttons here if needed -->
      </div>

      <!-- Content embedding -->
      <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-6 shadow-lg border border-gray-200">
        <p class="text-gray-600 mb-6"><?= htmlspecialchars($module['description']) ?></p>

        <div class="w-full h-[600px] border rounded-lg shadow-inner overflow-hidden">
          <?php
          // If it’s PPTX or Office file, try Office Viewer first
          $ext = strtolower(pathinfo($contentPath, PATHINFO_EXTENSION));
          if (in_array($ext, ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx'])) {
              // Use Office Online embed
              echo "<iframe src=\"" . htmlspecialchars($officeViewerUrl) . "\" class=\"w-full h-full border-0\"></iframe>";
          } else {
              // fallback to Google Docs viewer
              echo "<iframe src=\"" . htmlspecialchars($googleViewerUrl) . "\" class=\"w-full h-full border-0\"></iframe>";
          }
          ?>
        </div>

        <div class="flex justify-between items-center mt-6">
          <a href="resources.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg shadow">
            ← Back to Resources
          </a>
          <form method="POST" action="mark_completed.php">
            <input type="hidden" name="module_id" value="<?= $moduleId ?>">
            <button type="submit" class="px-5 py-2 bg-teal-600 text-white rounded-lg shadow hover:bg-teal-700">
              Mark as Completed
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
