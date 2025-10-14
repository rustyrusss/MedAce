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

// Fetch modules
$stmt = $conn->prepare("SELECT id, title, description, status, created_at FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: false, search: '', filter: 'all' }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Modules</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    html { scroll-behavior: smooth; }
    .transition-all * { transition: all 0.2s ease-in-out; }
  </style>
</head>
<body class="transition-all min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 text-gray-800">

<!-- Sidebar -->
<aside class="fixed inset-y-0 left-0 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col z-30 transition-all duration-300"
       :class="{'w-64': !collapsed, 'w-20': collapsed, '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768}">
    
    <!-- Profile -->
    <div class="flex items-center mb-10 transition-all" :class="collapsed ? 'justify-center' : 'space-x-4'">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
             class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
        <div x-show="!collapsed" class="flex flex-col overflow-hidden">
            <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></p>
            <p class="text-sm text-gray-500">Professor</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
        <div>
            <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
            <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">🏠</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Dashboard</span>
            </a>
            <a href="manage_modules.php" class="flex items-center p-2 rounded-lg bg-teal-100 transition">
                <span class="text-xl">📘</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Modules</span>
            </a>
            <a href="manage_quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">📝</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Quizzes</span>
            </a>
            <a href="student_progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">👨‍🎓</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Student Progress</span>
            </a>
        </div>
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

<!-- Main content -->
<div class="md:ml-64 p-6 space-y-10 transition-all">
    <h1 class="text-3xl font-bold text-gray-800">📘 Manage Modules</h1>

    <!-- Button to Add Module -->
    <div class="flex justify-end mb-4">
        <button type="button" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 shadow-md" onclick="toggleModal()">+ Add Module</button>
    </div>

    <!-- Search + Filter -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-white/80 p-4 rounded-xl shadow-sm border border-gray-200 mb-4 gap-4">
        <div class="flex items-center space-x-2 w-full md:w-1/2">
            <input type="text" placeholder="🔍 Search module title..."
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

    <!-- Modules Table -->
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
            <tr class="hover:bg-teal-50 transition-all"
                x-show="(filter === 'all' || filter === '<?= strtolower($module['status']) ?>') &&
                         (search === '' || '<?= strtolower($module['title']) ?>'.includes(search.toLowerCase()))">
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
                  <a href="edit_module.php?id=<?= $module['id'] ?>"
                     class="text-teal-600 hover:text-teal-800 font-semibold transition">Edit</a>
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
function toggleModal() {
    const modal = document.getElementById('addModuleModal');
    modal.classList.toggle('hidden');
}
</script>

</body>
</html>
