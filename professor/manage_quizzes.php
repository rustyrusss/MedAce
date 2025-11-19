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

// ✅ Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_quiz') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $module_id = intval($_POST['module_id']);
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $time_limit = isset($_POST['time_limit']) ? intval($_POST['time_limit']) : 0;
        $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
        $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
        
        if (empty($title) || empty($module_id)) {
            throw new Exception("Title and Module are required.");
        }
        
        // Verify module belongs to professor
        $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND professor_id = ?");
        $stmt->execute([$module_id, $professorId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid module selected.");
        }
        
        // Insert quiz
        $stmt = $conn->prepare("
            INSERT INTO quizzes (title, description, module_id, lesson_id, professor_id, content, status, time_limit, publish_time, deadline_time, created_at) 
            VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title,
            $description,
            $module_id,
            $professorId,
            $content,
            $status,
            $time_limit,
            $publish_time,
            $deadline_time
        ]);
        
        $_SESSION['success'] = "Quiz created successfully! 🎉";
        header("Location: manage_quizzes.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating quiz: " . $e->getMessage();
    }
}

// ✅ Handle quiz update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_quiz') {
    try {
        $quiz_id = intval($_POST['quiz_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $module_id = intval($_POST['module_id']);
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $time_limit = isset($_POST['time_limit']) ? intval($_POST['time_limit']) : 0;
        $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
        $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
        
        if (empty($title) || empty($module_id)) {
            throw new Exception("Title and Module are required.");
        }
        
        // Verify quiz belongs to professor
        $stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND professor_id = ?");
        $stmt->execute([$quiz_id, $professorId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid quiz selected.");
        }
        
        // Verify module belongs to professor
        $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND professor_id = ?");
        $stmt->execute([$module_id, $professorId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid module selected.");
        }
        
        // Update quiz
        $stmt = $conn->prepare("
            UPDATE quizzes 
            SET title = ?, description = ?, module_id = ?, content = ?, status = ?, 
                time_limit = ?, publish_time = ?, deadline_time = ?
            WHERE id = ? AND professor_id = ?
        ");
        $stmt->execute([
            $title,
            $description,
            $module_id,
            $content,
            $status,
            $time_limit,
            $publish_time,
            $deadline_time,
            $quiz_id,
            $professorId
        ]);
        
        $_SESSION['success'] = "Quiz updated successfully! ✅";
        header("Location: manage_quizzes.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating quiz: " . $e->getMessage();
    }
}

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
    SELECT q.id, q.title, q.description, q.status, q.time_limit, q.created_at, q.module_id, q.content, q.publish_time, q.deadline_time, m.title AS module_title
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

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
        <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900 font-bold">✕</button>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900 font-bold">✕</button>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Search + Filter -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <input type="text" id="searchInput" placeholder="🔍 Search quiz title..." class="w-full md:w-1/2 border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-sky-400 outline-none">
      <div class="flex items-center space-x-2">
        <label class="text-gray-600 font-medium">Filter:</label>
        <select id="statusFilter" class="border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-sky-400 outline-none">
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
        <button onclick="openAddModal()" class="px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 shadow-md transition">
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
            <th class="py-3 px-4 font-semibold">Time Limit</th>
            <th class="py-3 px-4 font-semibold">Status</th>
            <th class="py-3 px-4 font-semibold">Created</th>
            <th class="py-3 px-4 font-semibold text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100" id="quizTableBody">
          <?php foreach ($quizzes as $index => $quiz): ?>
          <tr class="hover:bg-sky-50 transition-all duration-150 quiz-row" 
              data-status="<?= strtolower($quiz['status']) ?>" 
              data-title="<?= htmlspecialchars($quiz['title']) ?>">
            <td class="py-3 px-4"><?= $index + 1 ?></td>
            <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($quiz['title']) ?></td>
            <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($quiz['description']) ?></td>
            <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($quiz['module_title'] ?? '—') ?></td>
            <td class="py-3 px-4">
              <?php if (!empty($quiz['time_limit']) && $quiz['time_limit'] > 0): ?>
                <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-semibold">
                  ⏱️ <?= $quiz['time_limit'] ?> min
                </span>
              <?php else: ?>
                <span class="text-gray-400 text-xs">No limit</span>
              <?php endif; ?>
            </td>
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
                <button onclick='openEditModal(<?= json_encode($quiz) ?>)' class="px-3 py-1 text-sm bg-sky-100 text-sky-700 rounded-md hover:bg-sky-200 transition">Edit</button>
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
          <p class="text-sm text-gray-400 mt-1">Click "+ Add Quiz" to create your first one.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- ✅ Add Quiz Modal -->
<div id="addQuizModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 transform scale-95 opacity-0 transition-all duration-300 ease-out overflow-y-auto max-h-[90vh]" id="addModalContent">
    <button onclick="closeAddModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl font-semibold">×</button>
    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">➕ Add New Quiz</h2>
    <form method="POST" class="space-y-5">
      <input type="hidden" name="action" value="add_quiz">

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
        <label class="block text-sm font-semibold text-gray-700 mb-1">⏱️ Time Limit (minutes)</label>
        <input type="number" name="time_limit" min="0" placeholder="0 = No time limit" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500 mt-1">Set quiz duration in minutes. Leave as 0 for no time limit.</p>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">📅 Publish Time</label>
        <input type="datetime-local" name="publish_time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500 mt-1">Leave empty to publish immediately.</p>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">⏰ Deadline Time</label>
        <input type="datetime-local" name="deadline_time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500 mt-1">Leave empty for no deadline.</p>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
        <button type="submit" class="px-5 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 transition">Save Quiz</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Edit Quiz Modal -->
<div id="editQuizModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 transform scale-95 opacity-0 transition-all duration-300 ease-out overflow-y-auto max-h-[90vh]" id="editModalContent">
    <button onclick="closeEditModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl font-semibold">×</button>
    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">✏️ Edit Quiz</h2>
    <form method="POST" class="space-y-5" id="editQuizForm">
      <input type="hidden" name="action" value="edit_quiz">
      <input type="hidden" name="quiz_id" id="edit_quiz_id">

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Quiz Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" id="edit_title" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
        <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none"></textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Select Module <span class="text-red-500">*</span></label>
        <select name="module_id" id="edit_module_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
          <option value="" disabled>— Choose a module —</option>
          <?php foreach ($modules as $module): ?>
            <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Instructions / Content</label>
        <textarea name="content" id="edit_content" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none"></textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
        <select name="status" id="edit_status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">⏱️ Time Limit (minutes)</label>
        <input type="number" name="time_limit" id="edit_time_limit" min="0" placeholder="0 = No time limit" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500 mt-1">Set quiz duration in minutes. Leave as 0 for no time limit.</p>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">📅 Publish Time</label>
        <input type="datetime-local" name="publish_time" id="edit_publish_time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500 mt-1">Leave empty to publish immediately.</p>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">⏰ Deadline Time</label>
        <input type="datetime-local" name="deadline_time" id="edit_deadline_time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-sky-400 outline-none">
        <p class="text-sm text-gray-500 mt-1">Leave empty for no deadline.</p>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
        <button type="submit" class="px-5 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 transition">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// ✅ Sidebar functionality
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

// ✅ Add Quiz Modal Functions
function openAddModal() {
  const modal = document.getElementById("addQuizModal");
  const content = document.getElementById("addModalContent");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  setTimeout(() => {
    content.classList.remove("scale-95", "opacity-0");
    content.classList.add("scale-100", "opacity-100");
  }, 10);
}

function closeAddModal() {
  const modal = document.getElementById("addQuizModal");
  const content = document.getElementById("addModalContent");
  content.classList.remove("scale-100", "opacity-100");
  content.classList.add("scale-95", "opacity-0");
  setTimeout(() => {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }, 200);
}

// ✅ Edit Quiz Modal Functions
function openEditModal(quiz) {
  // Populate form fields
  document.getElementById("edit_quiz_id").value = quiz.id;
  document.getElementById("edit_title").value = quiz.title;
  document.getElementById("edit_description").value = quiz.description || '';
  document.getElementById("edit_module_id").value = quiz.module_id;
  document.getElementById("edit_content").value = quiz.content || '';
  document.getElementById("edit_status").value = quiz.status;
  document.getElementById("edit_time_limit").value = quiz.time_limit || 0;
  
  // Format datetime for input fields (PHP format to HTML5 datetime-local format)
  if (quiz.publish_time) {
    const publishDate = new Date(quiz.publish_time);
    document.getElementById("edit_publish_time").value = formatDateTimeLocal(publishDate);
  } else {
    document.getElementById("edit_publish_time").value = '';
  }
  
  if (quiz.deadline_time) {
    const deadlineDate = new Date(quiz.deadline_time);
    document.getElementById("edit_deadline_time").value = formatDateTimeLocal(deadlineDate);
  } else {
    document.getElementById("edit_deadline_time").value = '';
  }
  
  // Show modal
  const modal = document.getElementById("editQuizModal");
  const content = document.getElementById("editModalContent");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  setTimeout(() => {
    content.classList.remove("scale-95", "opacity-0");
    content.classList.add("scale-100", "opacity-100");
  }, 10);
}

function closeEditModal() {
  const modal = document.getElementById("editQuizModal");
  const content = document.getElementById("editModalContent");
  content.classList.remove("scale-100", "opacity-100");
  content.classList.add("scale-95", "opacity-0");
  setTimeout(() => {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }, 200);
}

// Helper function to format date for datetime-local input
function formatDateTimeLocal(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// ✅ Search and Filter Functionality
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('searchInput');
  const statusFilter = document.getElementById('statusFilter');
  const quizRows = document.querySelectorAll('.quiz-row');

  function filterQuizzes() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value.toLowerCase();

    quizRows.forEach(row => {
      const title = row.getAttribute('data-title').toLowerCase();
      const status = row.getAttribute('data-status').toLowerCase();

      const matchesSearch = title.includes(searchTerm);
      const matchesStatus = statusValue === 'all' || status === statusValue;

      if (matchesSearch && matchesStatus) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  searchInput.addEventListener('input', filterQuizzes);
  statusFilter.addEventListener('change', filterQuizzes);
});

// ✅ Close modals when clicking outside
document.getElementById('addQuizModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeAddModal();
  }
});

document.getElementById('editQuizModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeEditModal();
  }
});
</script>
</body>
</html>