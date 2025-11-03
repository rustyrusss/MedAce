<?php
session_start();
require_once __DIR__ . '/../config/db_conn.php';
require_once __DIR__ . '/../includes/avatar_helper.php'; // <-- use helper safely

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// Get professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";

// Use avatar helper to resolve profile picture path (second param is base web path)
$profilePic = getProfilePicture($prof, "../");

// Fetch modules
$stmt = $conn->prepare("SELECT id, title, description, status, created_at FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Manage Modules</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    .sidebar { transition: width 220ms ease; }
    .sidebar-expanded { width: 16rem; }
    .sidebar-collapsed { width: 5rem; }
    body { background-color: #cce7ea; }
    .card {
      background-color: #ffffff;
      border-radius: 1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      border: 1px solid #e5e7eb;
    }
    .hover-row:hover { background-color: #e0f2fe; }
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
          <span class="sidebar-label hidden text-sm font-semibold text-sky-700"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></span>
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
      <div class="card p-6 mb-6 flex items-center gap-4">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar" class="w-12 h-12 rounded-full object-cover border" />
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Welcome, <?= htmlspecialchars(ucwords(strtolower($profName))) ?> 👋</h1>
          <p class="text-gray-500 mt-1">Here’s a quick overview of your modules.</p>
        </div>
      </div>

      <!-- Controls -->
      <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
          <input type="text" placeholder="🔍 Search module title..." id="searchInput" class="border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <button type="button" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 shadow-md" onclick="toggleModal()">+ Add Module</button>
      </div>

      <!-- Modules Table (kept the same style you liked) -->
      <div class="bg-white/90 backdrop-blur-md p-6 rounded-2xl shadow-xl border border-gray-200">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-gray-800">📚 Your Modules</h2>
          <p class="text-sm text-gray-500"><?= count($modules) ?> total</p>
        </div>

        <?php if (count($modules) > 0): ?>
        <div class="overflow-hidden rounded-xl border border-gray-100 shadow-sm">
          <table class="w-full text-sm text-gray-700">
            <thead class="bg-gradient-to-r from-teal-600 to-teal-500 text-white text-left">
              <tr>
                <th class="py-3 px-4 font-semibold">#</th>
                <th class="py-3 px-4 font-semibold">Title</th>
                <th class="py-3 px-4 font-semibold">Description</th>
                <th class="py-3 px-4 font-semibold">Status</th>
                <th class="py-3 px-4 font-semibold">Created</th>
                <th class="py-3 px-4 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <?php foreach ($modules as $index => $module): ?>
              <tr class="hover:bg-teal-50 transition-all">
                <td class="py-3 px-4 text-gray-600"><?= $index + 1 ?></td>
                <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($module['title']) ?></td>
                <td class="py-3 px-4 text-gray-600 truncate max-w-xs"><?= htmlspecialchars($module['description']) ?></td>
                <td class="py-3 px-4">
                  <?php
                    $status = strtolower($module['status']);
                    $statusColor = match($status) {
                      'published' => 'bg-green-100 text-green-700',
                      'draft' => 'bg-yellow-100 text-yellow-700',
                      'archived' => 'bg-gray-200 text-gray-700',
                      default => 'bg-blue-100 text-blue-700'
                    };
                  ?>
                  <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                    <?= ucfirst($module['status']) ?>
                  </span>
                </td>
                <td class="py-3 px-4 text-gray-500"><?= date('M d, Y', strtotime($module['created_at'])) ?></td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center space-x-3">
                    <a href="edit_module.php?id=<?= $module['id'] ?>" class="text-teal-600 hover:text-teal-800 font-semibold transition">Edit</a>
                    <span class="text-gray-300">|</span>
                    <a href="../actions/delete_module.php?id=<?= $module['id'] ?>"
                       onclick="return confirm('Are you sure you want to delete this module?');"
                       class="text-red-500 hover:text-red-700 font-semibold transition">Delete</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="flex flex-col items-center justify-center py-10 text-gray-500">
          <span class="text-5xl mb-3">📭</span>
          <p class="text-lg font-medium">No modules added yet.</p>
          <p class="text-sm text-gray-400 mt-1">Click “+ Add Module” to upload your first one.</p>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Add Module Modal -->
  <div id="addModuleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-lg w-full">
      <h2 class="text-2xl font-semibold mb-4">Add Module</h2>
      <form action="../actions/add_module_action.php" method="POST" enctype="multipart/form-data">
        <div class="mb-4">
          <label class="block text-gray-700">Title</label>
          <input type="text" name="title" required class="w-full border border-gray-300 rounded-lg p-2">
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Description</label>
          <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg p-2"></textarea>
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Upload File (PDF or PPT)</label>
          <input type="file" name="module_file" accept=".pdf,.ppt,.pptx" class="w-full border border-gray-300 rounded-lg p-2">
        </div>
        <div class="flex justify-between">
          <button type="button" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg" onclick="toggleModal()">Cancel</button>
          <button type="submit" class="bg-teal-600 text-white px-4 py-2 rounded-lg">Save Module</button>
        </div>
      </form>
    </div>
  </div>

<script>
  // Sidebar toggle (keeps your previous behavior)
  (function(){
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const labels = document.querySelectorAll('.sidebar-label');
    let expanded = false;

    function setCollapsed() {
      sidebar.classList.remove('sidebar-expanded');
      sidebar.classList.add('sidebar-collapsed');
      labels.forEach(s => s.classList.add('hidden'));
    }
    function setExpanded() {
      sidebar.classList.remove('sidebar-collapsed');
      sidebar.classList.add('sidebar-expanded');
      labels.forEach(s => s.classList.remove('hidden'));
    }

    setCollapsed();
    toggle && toggle.addEventListener('click', () => {
      expanded = !expanded;
      expanded ? setExpanded() : setCollapsed();
    });
  })();

  // Modal
  function toggleModal() {
    const modal = document.getElementById('addModuleModal');
    modal.classList.toggle('hidden');
  }
</script>

</body>
</html>
