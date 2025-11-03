<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// ✅ Access control
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
$profilePic = getProfilePicture($prof, "../");

// ✅ Fetch modules for dropdown
$stmt = $conn->prepare("SELECT id, title FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch quizzes
$stmt = $conn->prepare("
    SELECT q.id, q.title, q.description, q.status, q.created_at, m.title AS module_title
    FROM quizzes q
    LEFT JOIN modules m ON q.module_id = m.id
    WHERE q.professor_id = :professor_id
    ORDER BY q.created_at DESC
");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Quizzes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background-color: #cce7ea; }
    .sidebar { transition: width 220ms ease; }
    .sidebar-expanded { width: 16rem; }
    .sidebar-collapsed { width: 5rem; }
    .modal-enter { opacity: 0; transform: scale(0.95); }
    .modal-enter-active { opacity: 1; transform: scale(1); transition: all 0.25s ease-out; }
    .modal-exit { opacity: 1; transform: scale(1); }
    .modal-exit-active { opacity: 0; transform: scale(0.95); transition: all 0.2s ease-in; }
  </style>
</head>
<body class="text-gray-800 font-sans">
<div class="flex min-h-screen">

  <!-- ✅ Sidebar -->
  <aside id="sidebar" class="sidebar sidebar-collapsed bg-white border-r border-gray-200 flex flex-col shadow-sm z-30">
    <div class="flex items-center justify-between px-3 py-3 border-b">
      <div class="flex items-center gap-2">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar" class="w-8 h-8 rounded-full object-cover border" />
        <span class="sidebar-label hidden text-sm font-semibold text-sky-700">
          <?= htmlspecialchars(ucwords(strtolower($profName))) ?>
        </span>
      </div>
      <button id="sidebarToggle" aria-label="Toggle sidebar" class="p-1 rounded-md text-gray-600 hover:bg-gray-100">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <nav class="flex-1 mt-3 px-1 space-y-1">
      <a href="dashboard.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
        <div class="w-8 flex items-center justify-center text-xl">🏠</div>
        <span class="sidebar-label hidden font-medium">Dashboard</span>
      </a>

      <a href="manage_modules.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
        <div class="w-8 flex items-center justify-center text-xl">📘</div>
        <span class="sidebar-label hidden font-medium">Modules</span>
      </a>

      <a href="manage_quizzes.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg bg-sky-100 text-sky-800 font-semibold">
        <div class="w-8 flex items-center justify-center text-xl">📝</div>
        <span class="sidebar-label hidden font-medium">Quizzes</span>
      </a>

      <a href="student_progress.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
        <div class="w-8 flex items-center justify-center text-xl">👨‍🎓</div>
        <span class="sidebar-label hidden font-medium">Student Progress</span>
      </a>
    </nav>

    <div class="px-2 py-4 border-t">
      <a href="../actions/logout_action.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-red-600 hover:bg-red-50">
        <div class="w-8 flex items-center justify-center text-xl">🚪</div>
        <span class="sidebar-label hidden font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <!-- ✅ Main Content -->
  <main class="flex-1 p-6 md:p-10">
    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200 mb-6">
      <h1 class="text-2xl font-bold text-gray-800">🧠 Manage Quizzes</h1>
    </div>

    <!-- Search + Filter -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <input type="text" placeholder="🔍 Search quiz title..." class="w-full md:w-1/2 border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-sky-400 outline-none">
      <div class="flex items-center space-x-2">
        <label class="text-gray-600 font-medium">Filter:</label>
        <select class="border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-sky-400 outline-none">
          <option value="all">All</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>

    <!-- Quizzes Table -->
    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 overflow-x-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-800">Your Quizzes</h2>
        <button onclick="toggleModal()" class="px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 shadow-md transition">
          + Add Quiz
        </button>
      </div>

      <?php if (count($quizzes) > 0): ?>
      <table class="w-full text-sm text-gray-700 border-collapse">
        <thead class="bg-sky-100 text-sky-800 text-left">
          <tr>
            <th class="py-3 px-4 font-semibold">#</th>
            <th class="py-3 px-4 font-semibold">Title</th>
            <th class="py-3 px-4 font-semibold">Description</th>
            <th class="py-3 px-4 font-semibold">Module</th>
            <th class="py-3 px-4 font-semibold">Status</th>
            <th class="py-3 px-4 font-semibold">Created</th>
            <th class="py-3 px-4 font-semibold text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($quizzes as $index => $quiz): ?>
          <tr class="hover:bg-sky-50 transition-all duration-150">
            <td class="py-3 px-4"><?= $index + 1 ?></td>
            <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($quiz['title']) ?></td>
            <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($quiz['description']) ?></td>
            <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($quiz['module_title'] ?? '—') ?></td>
            <td class="py-3 px-4">
              <?php
                $status = strtolower($quiz['status']);
                $badge = match($status) {
                  'active' => 'bg-green-100 text-green-700',
                  'inactive' => 'bg-gray-200 text-gray-700',
                  default => 'bg-blue-100 text-blue-700'
                };
              ?>
              <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge ?>">
                <?= ucfirst($quiz['status']) ?>
              </span>
            </td>
            <td class="py-3 px-4 text-gray-500"><?= date('M d, Y', strtotime($quiz['created_at'])) ?></td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center space-x-2">
                <a href="manage_questions.php?quiz_id=<?= $quiz['id'] ?>" class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition">Questions</a>
                <a href="edit_quiz.php?id=<?= $quiz['id'] ?>" class="px-3 py-1 text-sm bg-sky-100 text-sky-700 rounded-md hover:bg-sky-200 transition">Edit</a>
                <a href="../actions/delete_quiz.php?id=<?= $quiz['id'] ?>" onclick="return confirm('Delete this quiz?');" class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition">Delete</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="text-center py-10 text-gray-500">
          <span class="text-5xl mb-3 block">📭</span>
          <p class="text-lg font-medium">No quizzes added yet.</p>
          <p class="text-sm text-gray-400 mt-1">Click “+ Add Quiz” to create your first one.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- ✅ Modal -->
<div id="addQuizModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 transform scale-95 opacity-0 transition-all duration-300 ease-out overflow-y-auto max-h-[90vh]" id="modalContent">
    <button onclick="toggleModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl font-semibold">×</button>
    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">➕ Add New Quiz</h2>
    <form action="../actions/add_quiz_action.php" method="POST" class="space-y-5">

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Quiz Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none"></textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Select Module <span class="text-red-500">*</span></label>
        <select name="module_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
          <option value="" disabled selected>— Choose a module —</option>
          <?php foreach ($modules as $module): ?>
            <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Instructions / Content</label>
        <textarea name="content" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none"></textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
        <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Publish Time</label>
        <input type="datetime-local" name="publish_time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500">Leave empty to publish immediately.</p>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Deadline Time</label>
        <input type="datetime-local" name="deadline_time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500">Leave empty for no deadline.</p>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <button type="button" onclick="toggleModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
        <button type="submit" class="px-5 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 transition">Save Quiz</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  const toggle = document.getElementById("sidebarToggle");
  const labels = document.querySelectorAll(".sidebar-label");
  let expanded = false;

  function collapse() {
    sidebar.classList.remove("sidebar-expanded");
    sidebar.classList.add("sidebar-collapsed");
    labels.forEach(l => l.classList.add("hidden"));
  }
  function expand() {
    sidebar.classList.remove("sidebar-collapsed");
    sidebar.classList.add("sidebar-expanded");
    labels.forEach(l => l.classList.remove("hidden"));
  }

  collapse();
  toggle.addEventListener("click", () => {
    expanded = !expanded;
    expanded ? expand() : collapse();
  });
});

function toggleModal() {
  const modal = document.getElementById("addQuizModal");
  const content = document.getElementById("modalContent");
  if (modal.classList.contains("hidden")) {
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    setTimeout(() => content.classList.remove("scale-95", "opacity-0"), 10);
  } else {
    content.classList.add("scale-95", "opacity-0");
    setTimeout(() => {
      modal.classList.add("hidden");
      modal.classList.remove("flex");
    }, 200);
  }
}
</script>
</body>
</html>
