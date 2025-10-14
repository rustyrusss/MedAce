<?php
session_start();
require_once '../config/db_conn.php';

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

// Default avatar
if (!empty($prof['gender'])) {
    if (strtolower($prof['gender']) === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif (strtolower($prof['gender']) === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}
$profilePic = !empty($prof['profile_pic']) ? "../" . $prof['profile_pic'] : $defaultAvatar;

// Fetch modules for dropdown
$stmt = $conn->prepare("SELECT id, title FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch quizzes
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
<html lang="en" x-data="{ sidebarOpen: false, collapsed: false, search: '', filter: 'all' }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Quizzes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    html { scroll-behavior: smooth; }
    .transition-all * { transition: all 0.2s ease-in-out; }
  </style>
</head>

<body class="transition-all min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 text-gray-800">

<!-- Mobile Header -->
<div class="md:hidden flex items-center justify-between bg-white shadow px-4 py-3">
  <h1 class="text-lg font-semibold text-gray-700">Manage Quizzes</h1>
  <button @click="sidebarOpen = !sidebarOpen" class="text-gray-700 text-2xl">☰</button>
</div>

<!-- Sidebar -->
<aside class="fixed inset-y-0 left-0 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col z-30 transition-all duration-300"
       :class="{
         'w-64': !collapsed,
         'w-20': collapsed,
         '-translate-x-full': !sidebarOpen && window.innerWidth < 768
       }">

  <!-- Collapse Button -->
  <button @click="collapsed = !collapsed" class="hidden md:flex absolute top-4 right-[-12px] bg-teal-600 text-white rounded-full w-6 h-6 items-center justify-center shadow-md hover:bg-teal-700 transition">
    <span x-show="!collapsed">«</span>
    <span x-show="collapsed">»</span>
  </button>

  <!-- Profile -->
  <div class="flex items-center mb-10 mt-6 transition-all" :class="collapsed ? 'justify-center' : 'space-x-4'">
    <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
         class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
    <div x-show="!collapsed" class="flex flex-col overflow-hidden">
      <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></p>
      <p class="text-sm text-gray-500">Professor</p>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 space-y-4">
    <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
      <span class="text-xl">🏠</span>
      <span x-show="!collapsed" class="ml-3 font-medium">Dashboard</span>
    </a>
    <a href="manage_modules.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
      <span class="text-xl">📘</span>
      <span x-show="!collapsed" class="ml-3 font-medium">Modules</span>
    </a>
    <a href="manage_quizzes.php" class="flex items-center p-2 rounded-lg bg-teal-100 transition">
      <span class="text-xl">📝</span>
      <span x-show="!collapsed" class="ml-3 font-medium">Quizzes</span>
    </a>
    <a href="student_progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
      <span class="text-xl">👨‍🎓</span>
      <span x-show="!collapsed" class="ml-3 font-medium">Student Progress</span>
    </a>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="../actions/logout_action.php"
       class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
      <span class="text-xl">🚪</span>
      <span x-show="!collapsed" class="ml-3 font-medium">Logout</span>
    </a>
  </div>
</aside>

<!-- Main Content -->
<div class="transition-all duration-300 md:ml-64 p-6 space-y-10" :class="collapsed ? 'md:ml-20' : 'md:ml-64'">
  <div class="flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800 hidden md:block">📝 Manage Quizzes</h1>
    <button type="button" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 shadow-md" onclick="toggleModal()">+ Add Quiz</button>
  </div>

  <!-- Search + Filter -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white/80 p-4 rounded-xl shadow-sm border border-gray-200 mb-4 gap-4">
    <div class="flex items-center space-x-2 w-full md:w-1/2">
      <input type="text" placeholder="🔍 Search quiz title..."
             x-model="search"
             class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-teal-400 outline-none">
    </div>
    <div class="flex items-center space-x-2">
      <label class="text-gray-600 font-medium">Filter:</label>
      <select x-model="filter"
              class="border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-teal-400 outline-none">
        <option value="all">All</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
        <option value="archived">Archived</option>
      </select>
    </div>
  </div>

  <!-- Quizzes Table -->
  <div class="bg-white/90 backdrop-blur-md p-6 rounded-2xl shadow-xl border border-gray-200 overflow-x-auto">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-800">🧠 Your Quizzes</h2>
      <p class="text-sm text-gray-500"><?= count($quizzes) ?> total</p>
    </div>

    <?php if (count($quizzes) > 0): ?>
    <table class="w-full min-w-[700px] text-sm text-gray-700">
      <thead class="bg-gradient-to-r from-teal-600 to-teal-500 text-white text-left">
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
      <tbody class="bg-white divide-y divide-gray-100">
        <?php foreach ($quizzes as $index => $quiz): ?>
        <tr class="hover:bg-teal-50 transition-all"
            x-show="(filter === 'all' || filter === '<?= strtolower($quiz['status']) ?>') &&
                     (search === '' || '<?= strtolower($quiz['title']) ?>'.includes(search.toLowerCase()))">
          <td class="py-3 px-4 text-gray-600"><?= $index + 1 ?></td>
          <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($quiz['title']) ?></td>
          <td class="py-3 px-4 text-gray-600 truncate max-w-xs"><?= htmlspecialchars($quiz['description']) ?></td>
          <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($quiz['module_title'] ?? '—') ?></td>
          <td class="py-3 px-4">
            <?php
              $status = strtolower($quiz['status']);
              $statusColor = match($status) {
                'published' => 'bg-green-100 text-green-700',
                'draft' => 'bg-yellow-100 text-yellow-700',
                'archived' => 'bg-gray-200 text-gray-700',
                default => 'bg-blue-100 text-blue-700'
              };
            ?>
            <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
              <?= ucfirst($quiz['status']) ?>
            </span>
          </td>
          <td class="py-3 px-4 text-gray-500"><?= date('M d, Y', strtotime($quiz['created_at'])) ?></td>
          <td class="py-3 px-4 text-center">
            <div class="flex items-center justify-center space-x-3">
              <a href="manage_questions.php?quiz_id=<?= $quiz['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold transition">Questions</a>
              <span class="text-gray-300">|</span>
              <a href="edit_quiz.php?id=<?= $quiz['id'] ?>" class="text-teal-600 hover:text-teal-800 font-semibold transition">Edit</a>
              <span class="text-gray-300">|</span>
              <a href="../actions/delete_quiz.php?id=<?= $quiz['id'] ?>"
                 onclick="return confirm('Are you sure you want to delete this quiz?');"
                 class="text-red-500 hover:text-red-700 font-semibold transition">Delete</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="flex flex-col items-center justify-center py-10 text-gray-500">
      <span class="text-5xl mb-3">📭</span>
      <p class="text-lg font-medium">No quizzes added yet.</p>
      <p class="text-sm text-gray-400 mt-1">Click “+ Add Quiz” to create your first one.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Quiz Modal -->
<div id="addQuizModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg max-w-lg w-full mx-4">
    <h2 class="text-2xl font-semibold mb-4">Add Quiz</h2>
    <form action="../actions/add_quiz_action.php" method="POST">
      <div class="mb-4">
        <label class="block text-gray-700">Title</label>
        <input type="text" name="title" required class="w-full border border-gray-300 rounded-lg p-2">
      </div>
      <div class="mb-4">
        <label class="block text-gray-700">Description</label>
        <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg p-2"></textarea>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700">Module</label>
        <select name="module_id" class="w-full border border-gray-300 rounded-lg p-2">
          <option value="">Select Module</option>
          <?php foreach ($modules as $module): ?>
            <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex justify-between">
        <button type="button" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg" onclick="toggleModal()">Cancel</button>
        <button type="submit" class="bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700">Save Quiz</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleModal() {
  const modal = document.getElementById('addQuizModal');
  modal.classList.toggle('hidden');
}
</script>

</body>
</html>
