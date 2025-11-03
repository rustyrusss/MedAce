<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php'; // ✅ Reusable avatar logic

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// ✅ Fetch professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";

// ✅ Use helper for avatar
$profilePic = getProfilePicture($prof, "../");

// ✅ Dashboard counts
$totalModules = $conn->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$totalQuizzes = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalStudents = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// ✅ Recent modules & quizzes
$modules = $conn->query("SELECT id, title, created_at FROM modules ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$quizzes = $conn->query("SELECT id, title, publish_time, deadline_time FROM quizzes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Professor Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .sidebar {
      transition: width 220ms ease;
    }
    .sidebar-expanded {
      width: 16rem;
    }
    .sidebar-collapsed {
      width: 5rem;
    }
    body {
      background-color: #cce7ea; /* soft teal background */
    }
    .card {
      background-color: #ffffff;
      border-radius: 1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      border: 1px solid #e5e7eb;
    }
    .hover-row:hover {
      background-color: #e0f2fe;
    }
  </style>
</head>
<body class="text-gray-800 min-h-screen font-sans">

  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-collapsed bg-white shadow-sm border-r border-gray-200 flex flex-col z-30">
      <!-- top area -->
      <div class="flex items-center justify-between px-3 py-3 border-b">
        <div class="flex items-center gap-2">
          <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar" class="w-8 h-8 rounded-full object-cover border" />
          <span class="sidebar-label hidden text-sm font-semibold text-sky-700">
            <?= htmlspecialchars(ucwords(strtolower($profName))) ?>
          </span>
        </div>

        <button id="sidebarToggle" aria-label="Toggle sidebar" class="p-1 rounded-md text-gray-600 hover:bg-gray-100">
          <!-- menu icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

      <!-- nav -->
      <nav class="flex-1 mt-3 px-1 space-y-1">
        <a href="dashboard.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
          <div class="w-8 flex items-center justify-center text-xl">🏠</div>
          <span class="sidebar-label hidden font-medium">Dashboard</span>
        </a>

        <a href="manage_modules.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
          <div class="w-8 flex items-center justify-center text-xl">📘</div>
          <span class="sidebar-label hidden font-medium">Modules</span>
        </a>

        <a href="manage_quizzes.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
          <div class="w-8 flex items-center justify-center text-xl">📝</div>
          <span class="sidebar-label hidden font-medium">Quizzes</span>
        </a>

        <a href="student_progress.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
          <div class="w-8 flex items-center justify-center text-xl">👨‍🎓</div>
          <span class="sidebar-label hidden font-medium">Student Progress</span>
        </a>
      </nav>

      <!-- footer -->
      <div class="px-2 py-4 border-t">
        <a href="../actions/logout_action.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-red-600 hover:bg-red-50">
          <div class="w-8 flex items-center justify-center text-xl">🚪</div>
          <span class="sidebar-label hidden font-medium">Logout</span>
        </a>
      </div>
    </aside>

    <!-- Main content -->
    <main id="mainContent" class="flex-1 p-6 md:p-10">
      <div class="card p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
          Welcome, <?= htmlspecialchars(ucwords(strtolower($profName))) ?> 👋
        </h1>
        <p class="text-gray-500 mt-1">Here’s a quick overview of your teaching resources.</p>
      </div>

      <!-- Overview cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="card p-6">
          <h2 class="text-sm text-gray-500">📘 Total Modules</h2>
          <p class="text-3xl font-bold text-sky-600"><?= (int)$totalModules ?></p>
        </div>
        <div class="card p-6">
          <h2 class="text-sm text-gray-500">📝 Total Quizzes</h2>
          <p class="text-3xl font-bold text-blue-600"><?= (int)$totalQuizzes ?></p>
        </div>
        <div class="card p-6">
          <h2 class="text-sm text-gray-500">👨‍🎓 Total Students</h2>
          <p class="text-3xl font-bold text-indigo-600"><?= (int)$totalStudents ?></p>
        </div>
      </div>

      <!-- Recent Modules -->
      <div class="card p-6 mt-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">📘 Recent Modules</h2>
          <a href="manage_modules.php" class="text-sky-600 font-medium">View All →</a>
        </div>

        <?php if (empty($modules)): ?>
          <p class="text-gray-500">No modules created yet.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-gray-700">
              <thead>
                <tr class="bg-sky-100 text-sm text-gray-600">
                  <th class="p-3">Title</th>
                  <th class="p-3">Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($modules as $m): ?>
                <tr class="border-b hover-row">
                  <td class="p-3"><?= htmlspecialchars($m['title']) ?></td>
                  <td class="p-3"><?= date("F j, Y", strtotime($m['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent Quizzes -->
      <div class="card p-6 mt-6 mb-10">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">📝 Recent Quizzes</h2>
          <a href="manage_quizzes.php" class="text-sky-600 font-medium">View All →</a>
        </div>

        <?php if (empty($quizzes)): ?>
          <p class="text-gray-500">No quizzes available yet.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-gray-700">
              <thead>
                <tr class="bg-sky-100 text-sm text-gray-600">
                  <th class="p-3">Title</th>
                  <th class="p-3">Publish</th>
                  <th class="p-3">Deadline</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($quizzes as $q): ?>
                <tr class="border-b hover-row">
                  <td class="p-3"><?= htmlspecialchars($q['title']) ?></td>
                  <td class="p-3"><?= $q['publish_time'] ? date("M j, Y g:i A", strtotime($q['publish_time'])) : '-' ?></td>
                  <td class="p-3"><?= $q['deadline_time'] ? date("M j, Y g:i A", strtotime($q['deadline_time'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Sidebar toggle -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const sidebar = document.getElementById("sidebar");
      const toggle = document.getElementById("sidebarToggle");
      const labels = document.querySelectorAll(".sidebar-label");
      let expanded = false;

      function collapse() {
        sidebar.classList.remove("sidebar-expanded");
        sidebar.classList.add("sidebar-collapsed");
        labels.forEach(label => label.classList.add("hidden"));
      }

      function expand() {
        sidebar.classList.remove("sidebar-collapsed");
        sidebar.classList.add("sidebar-expanded");
        labels.forEach(label => label.classList.remove("hidden"));
      }

      collapse();
      toggle.addEventListener("click", () => {
        expanded = !expanded;
        expanded ? expand() : collapse();
      });
    });
  </script>
</body>
</html>
