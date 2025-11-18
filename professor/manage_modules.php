<?php
session_start();
require_once __DIR__ . '/../config/db_conn.php';
require_once __DIR__ . '/../includes/avatar_helper.php';

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

// Use avatar helper to resolve profile picture path
$profilePic = getProfilePicture($prof, "../");

// Fetch modules
$stmt = $conn->prepare("SELECT id, title, description, content, status, created_at FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
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
      <div class="flex items-center justify-between px-3 py-3 border-b">
        <div class="flex items-center gap-2">
          <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar" class="w-8 h-8 rounded-full object-cover border" />
          <span class="sidebar-label hidden text-sm font-semibold text-sky-700"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></span>
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

        <a href="manage_quizzes.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50">
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

    <!-- Main content -->
    <main id="mainContent" class="flex-1 p-6 md:p-10">
      
      <?php if (isset($_SESSION['success'])): ?>
      <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
        <div class="flex items-center">
          <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <p class="font-semibold"><?= htmlspecialchars($_SESSION['success']) ?></p>
        </div>
      </div>
      <?php unset($_SESSION['success']); endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
      <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
        <div class="flex items-center">
          <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <p class="font-semibold"><?= htmlspecialchars($_SESSION['error']) ?></p>
        </div>
      </div>
      <?php unset($_SESSION['error']); endif; ?>

      <div class="card p-6 mb-6 flex items-center gap-4">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar" class="w-12 h-12 rounded-full object-cover border" />
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Welcome, <?= htmlspecialchars(ucwords(strtolower($profName))) ?> 👋</h1>
          <p class="text-gray-500 mt-1">Here's a quick overview of your modules.</p>
        </div>
      </div>

      <!-- Controls -->
      <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
          <input type="text" placeholder="🔍 Search module title..." id="searchInput" class="border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-teal-400 outline-none">
        </div>
        <button type="button" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 shadow-md" onclick="toggleModal()">+ Add Module</button>
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
              <tr class="hover:bg-teal-50 transition-all">
                <td class="py-3 px-4 text-gray-600"><?= $index + 1 ?></td>
                <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($module['title']) ?></td>
                <td class="py-3 px-4 text-gray-600 truncate max-w-xs"><?= htmlspecialchars($module['description']) ?></td>
                <td class="py-3 px-4">
                  <?php
                    $status = strtolower($module['status']);
                    $statusColor = match($status) {
                      'published', 'active' => 'bg-green-100 text-green-700',
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
                  <button onclick="deleteModule(<?= $module['id'] ?>, '<?= htmlspecialchars(addslashes($module['title'])) ?>', '<?= htmlspecialchars($module['content']) ?>')" 
                          class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-semibold transition shadow-sm">
                    🗑️ Delete
                  </button>
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
          <p class="text-sm text-gray-400 mt-1">Click "+ Add Module" to upload your first one.</p>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Add Module Modal -->
  <div id="addModuleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
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

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
      <div class="text-center mb-4">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
          <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Module?</h3>
        <p class="text-gray-600 mb-1">Are you sure you want to delete:</p>
        <p class="text-gray-800 font-semibold mb-4" id="deleteModuleName"></p>
        <p class="text-sm text-red-600">⚠️ This action cannot be undone. The file and all student progress will be permanently deleted.</p>
      </div>
      <div class="flex gap-3">
        <button type="button" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300" onclick="closeDeleteModal()">Cancel</button>
        <button type="button" id="confirmDeleteBtn" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 font-semibold">Yes, Delete</button>
      </div>
    </div>
  </div>

<script>
  // Sidebar toggle
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

  // Add Module Modal
  function toggleModal() {
    const modal = document.getElementById('addModuleModal');
    modal.classList.toggle('hidden');
  }

  // Delete Module
  let deleteModuleId = null;
  let deleteModuleFile = null;

  function deleteModule(id, title, filePath) {
    deleteModuleId = id;
    deleteModuleFile = filePath;
    document.getElementById('deleteModuleName').textContent = title;
    document.getElementById('deleteModal').classList.remove('hidden');
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteModuleId = null;
    deleteModuleFile = null;
  }

  document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteModuleId) {
      // Create form and submit
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '../actions/delete_module_action.php';
      
      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'module_id';
      idInput.value = deleteModuleId;
      
      const fileInput = document.createElement('input');
      fileInput.type = 'hidden';
      fileInput.name = 'file_path';
      fileInput.value = deleteModuleFile;
      
      form.appendChild(idInput);
      form.appendChild(fileInput);
      document.body.appendChild(form);
      form.submit();
    }
  });

  // Search functionality
  document.getElementById('searchInput').addEventListener('keyup', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
      const title = row.cells[1].textContent.toLowerCase();
      const description = row.cells[2].textContent.toLowerCase();
      
      if (title.includes(searchTerm) || description.includes(searchTerm)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });
</script>

</body>
</html>








