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
<html lang="en" class="scroll-smooth">
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
<body class="bg-gray-50 text-gray-800 antialiased">

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

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="manage_modules.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-book text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Modules</span>
                </a>
                <a href="manage_quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="student_progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Student Progress</span>
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

<!-- Edit Quiz Modal -->
<div id="editQuizModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Edit Quiz</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-lg sm:text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4 sm:space-y-5" id="editQuizForm">
            <input type="hidden" name="action" value="edit_quiz">
            <input type="hidden" name="quiz_id" id="edit_quiz_id">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-heading text-primary-500 mr-1"></i>
                    Quiz Title *
                </label>
                <input type="text" name="title" id="edit_title" required 
                       class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-book-open text-primary-500 mr-1"></i>
                    Subject *
                </label>
                <input type="text" name="subject" id="edit_subject" required 
                       class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                       placeholder="e.g., Anatomy, Physiology">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-align-left text-primary-500 mr-1"></i>
                    Description
                </label>
                <textarea name="description" id="edit_description" rows="3" 
                          class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none text-sm sm:text-base"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-book text-primary-500 mr-1"></i>
                    Select Module *
                </label>
                <select name="module_id" id="edit_module_id" required class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    <option value="" disabled>— Choose a module —</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-lock text-amber-500 mr-1"></i>
                    Prerequisite Module (Optional)
                </label>
                <select name="prerequisite_module_id" id="edit_prerequisite_module_id" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    <option value="">— No prerequisite —</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Students must complete this module before taking the quiz</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-file-lines text-primary-500 mr-1"></i>
                    Instructions
                </label>
                <textarea name="content" id="edit_content" rows="2" 
                          class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none text-sm sm:text-base"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-primary-500 mr-1"></i>
                        Status
                    </label>
                    <select name="status" id="edit_status" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-clock text-primary-500 mr-1"></i>
                        Time Limit (min) *
                    </label>
                    <input type="number" name="time_limit" id="edit_time_limit" min="1" required
                           class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                           placeholder="Enter time limit in minutes">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-plus text-primary-500 mr-1"></i>
                        Publish Time
                    </label>
                    <input type="datetime-local" name="publish_time" id="edit_publish_time" 
                           class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-xmark text-primary-500 mr-1"></i>
                        Deadline
                    </label>
                    <input type="datetime-local" name="deadline_time" id="edit_deadline_time" 
                           class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                </div>
            </div>
            
            <div class="flex gap-2 sm:gap-3 mt-4 sm:mt-6">
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors text-sm sm:text-base">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-primary-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors text-sm sm:text-base">
                    <i class="fas fa-save mr-2"></i>
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Participants Modal -->
<div id="participantsModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                <i class="fas fa-users text-emerald-600 mr-2"></i>
                <span id="participantsModalTitle">Quiz Participants</span>
            </h2>
            <button onclick="closeParticipantsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-lg sm:text-xl"></i>
            </button>
        </div>
        
        <div id="participantsContent">
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                <p class="text-gray-600 mt-4">Loading participants...</p>
            </div>
        </div>
    </div>
</div>

<!-- Student Attempts Modal -->
<div id="attemptsModal" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                <span id="attemptsModalTitle">Student Attempts</span>
            </h2>
            <button onclick="closeAttemptsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-lg sm:text-xl"></i>
            </button>
        </div>
        
        <div id="attemptsContent">
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                <p class="text-gray-600 mt-4">Loading attempts...</p>
            </div>
        </div>
    </div>
</div>

<script>
    let sidebarExpanded = false;

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileHamburgerBtn = document.getElementById('mobileHamburgerBtn');
        
        sidebarExpanded = !sidebarExpanded;
        
        hamburgerBtn.classList.toggle('active');
        mobileHamburgerBtn.classList.toggle('active');
        
        if (window.innerWidth < 1024) {
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            overlay.classList.toggle('hidden');
            overlay.classList.toggle('show');
            
            if (sidebarExpanded) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        } else {
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            
            if (sidebarExpanded) {
                mainContent.style.marginLeft = '18rem';
            } else {
                mainContent.style.marginLeft = '5rem';
            }
        }
    }

    function closeSidebar() {
        if (window.innerWidth < 1024 && sidebarExpanded) {
            toggleSidebar();
        }
    }

    function openAddModal() {
        document.getElementById('addQuizModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        document.getElementById('addQuizModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function openEditModal(quiz) {
        document.getElementById('edit_quiz_id').value = quiz.id;
        document.getElementById('edit_title').value = quiz.title;
        document.getElementById('edit_subject').value = quiz.subject || '';
        document.getElementById('edit_description').value = quiz.description || '';
        document.getElementById('edit_module_id').value = quiz.module_id;
        document.getElementById('edit_prerequisite_module_id').value = quiz.prerequisite_module_id || '';
        document.getElementById('edit_content').value = quiz.content || '';
        document.getElementById('edit_status').value = quiz.status;
        document.getElementById('edit_time_limit').value = quiz.time_limit || 1;
        
        if (quiz.publish_time) {
            const publishDate = new Date(quiz.publish_time);
            document.getElementById('edit_publish_time').value = formatDateTimeLocal(publishDate);
        } else {
            document.getElementById('edit_publish_time').value = '';
        }
        
        if (quiz.deadline_time) {
            const deadlineDate = new Date(quiz.deadline_time);
            document.getElementById('edit_deadline_time').value = formatDateTimeLocal(deadlineDate);
        } else {
            document.getElementById('edit_deadline_time').value = '';
        }
        
        document.getElementById('editQuizModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editQuizModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function openParticipantsModal(quizId, quizTitle) {
        document.getElementById('participantsModalTitle').textContent = quizTitle + ' - Participants';
        document.getElementById('participantsModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Reset content
        document.getElementById('participantsContent').innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                <p class="text-gray-600 mt-4">Loading participants...</p>
            </div>
        `;
        
        // Load participants via AJAX
        fetch(`quiz_participants_ajax.php?quiz_id=${quizId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('participantsContent').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('participantsContent').innerHTML = `
                    <div class="text-center py-12">
                        <i class="fas fa-exclamation-circle text-4xl text-red-500"></i>
                        <p class="text-gray-600 mt-4">Error loading participants</p>
                    </div>
                `;
            });
    }

    function closeParticipantsModal() {
        document.getElementById('participantsModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function openAttemptsModal(quizId, studentId, studentName) {
        document.getElementById('attemptsModalTitle').textContent = studentName + ' - Attempts';
        document.getElementById('attemptsModal').classList.add('show');
        
        // Reset content
        document.getElementById('attemptsContent').innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                <p class="text-gray-600 mt-4">Loading attempts...</p>
            </div>
        `;
        
        // Load attempts via AJAX
        fetch(`student_attempts_ajax.php?quiz_id=${quizId}&student_id=${studentId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('attemptsContent').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('attemptsContent').innerHTML = `
                    <div class="text-center py-12">
                        <i class="fas fa-exclamation-circle text-4xl text-red-500"></i>
                        <p class="text-gray-600 mt-4">Error loading attempts</p>
                    </div>
                `;
            });
    }

    function closeAttemptsModal() {
        document.getElementById('attemptsModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Function to toggle attempt details (for the attempts modal)
    window.toggleAttemptDetails = function(attemptId) {
        const detailsDiv = document.getElementById(`attempt-details-${attemptId}`);
        
        if (detailsDiv.classList.contains('hidden')) {
            detailsDiv.classList.remove('hidden');
            
            // Load details if not already loaded
            if (!detailsDiv.dataset.loaded) {
                loadAttemptDetails(attemptId);
                detailsDiv.dataset.loaded = 'true';
            }
        } else {
            detailsDiv.classList.add('hidden');
        }
    }

    function loadAttemptDetails(attemptId) {
        const detailsDiv = document.getElementById(`attempt-details-${attemptId}`);
        
        fetch(`attempts_details_ajax.php?attempt_id=${attemptId}`)
            .then(response => response.text())
            .then(html => {
                detailsDiv.innerHTML = html;
            })
            .catch(error => {
                detailsDiv.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl text-red-500"></i>
                        <p class="text-gray-600 mt-2">Error loading details</p>
                    </div>
                `;
            });
    }

    // Grading functions (called from attempt details)
    window.autoSaveGrade = function(answerId, attemptId) {
        const points = document.getElementById(`points-${answerId}`).value;
        const feedback = document.getElementById(`feedback-${answerId}`).value;
        const statusDiv = document.getElementById(`save-status-${answerId}`);
        
        // Show saving indicator
        statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-primary-600"></i> Saving...';
        
        fetch('save_grade_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                answer_id: answerId,
                attempt_id: attemptId,
                points: parseInt(points),
                feedback: feedback
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<i class="fas fa-check-circle text-green-600"></i> Saved';
                setTimeout(() => {
                    statusDiv.innerHTML = '';
                }, 2000);
            } else {
                statusDiv.innerHTML = '<i class="fas fa-times-circle text-red-600"></i> Error';
            }
        })
        .catch(error => {
            statusDiv.innerHTML = '<i class="fas fa-times-circle text-red-600"></i> Error';
        });
    }

    window.recalculateScore = function(attemptId) {
        const recalcBtn = document.getElementById(`recalc-btn-${attemptId}`);
        const originalHTML = recalcBtn.innerHTML;
        
        recalcBtn.disabled = true;
        recalcBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Calculating...';
        
        fetch('recalculate_score_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                attempt_id: attemptId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                recalcBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Score Updated: ' + data.new_score + '%';
                recalcBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
                recalcBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                recalcBtn.innerHTML = '<i class="fas fa-times mr-2"></i>' + (data.error || 'Failed');
                recalcBtn.classList.add('bg-red-600');
                setTimeout(() => {
                    recalcBtn.innerHTML = originalHTML;
                    recalcBtn.classList.remove('bg-red-600');
                    recalcBtn.disabled = false;
                }, 2000);
            }
        })
        .catch(error => {
            recalcBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Error';
            setTimeout(() => {
                recalcBtn.innerHTML = originalHTML;
                recalcBtn.disabled = false;
            }, 2000);
        });
    }

    function formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const quizRows = document.querySelectorAll('.quiz-row');
        const quizRowsMobile = document.querySelectorAll('.quiz-row-mobile');

        function filterQuizzes() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();

            // Filter desktop table rows
            quizRows.forEach(row => {
                const title = row.getAttribute('data-title').toLowerCase();
                const subject = row.getAttribute('data-subject').toLowerCase();
                const status = row.getAttribute('data-status').toLowerCase();

                const matchesSearch = title.includes(searchTerm) || subject.includes(searchTerm);
                const matchesStatus = statusValue === 'all' || status === statusValue;

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Filter mobile cards
            quizRowsMobile.forEach(card => {
                const title = card.getAttribute('data-title').toLowerCase();
                const subject = card.getAttribute('data-subject').toLowerCase();
                const status = card.getAttribute('data-status').toLowerCase();

                const matchesSearch = title.includes(searchTerm) || subject.includes(searchTerm);
                const matchesStatus = statusValue === 'all' || status === statusValue;

                if (matchesSearch && matchesStatus) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterQuizzes);
        statusFilter.addEventListener('change', filterQuizzes);

        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        if (window.innerWidth >= 1024) {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-expanded');
            mainContent.style.marginLeft = '5rem';
            sidebarExpanded = false;
        } else {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-expanded');
            mainContent.style.marginLeft = '0';
            sidebarExpanded = false;
        }

        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('main-content');
                const overlay = document.getElementById('sidebar-overlay');
                const hamburgerBtn = document.getElementById('hamburgerBtn');
                const mobileHamburgerBtn = document.getElementById('mobileHamburgerBtn');
                
                if (window.innerWidth >= 1024) {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('show');
                    document.body.style.overflow = 'auto';
                    
                    if (!sidebar.classList.contains('sidebar-collapsed') && !sidebar.classList.contains('sidebar-expanded')) {
                        sidebar.classList.add('sidebar-collapsed');
                        sidebarExpanded = false;
                    }
                    
                    if (sidebarExpanded) {
                        mainContent.style.marginLeft = '18rem';
                    } else {
                        mainContent.style.marginLeft = '5rem';
                    }
                } else {
                    mainContent.style.marginLeft = '0';
                    
                    if (sidebarExpanded) {
                        sidebar.classList.remove('sidebar-collapsed');
                        sidebar.classList.add('sidebar-expanded');
                        overlay.classList.remove('hidden');
                        overlay.classList.add('show');
                    } else {
                        sidebar.classList.add('sidebar-collapsed');
                        sidebar.classList.remove('sidebar-expanded');
                        overlay.classList.add('hidden');
                        overlay.classList.remove('show');
                        hamburgerBtn.classList.remove('active');
                        mobileHamburgerBtn.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }
                }
            }, 250);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeParticipantsModal();
                closeAttemptsModal();
                if (sidebarExpanded && window.innerWidth < 1024) {
                    closeSidebar();
                }
            }
        });

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

        document.getElementById('participantsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeParticipantsModal();
            }
        });

        document.getElementById('attemptsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAttemptsModal();
            }
        });
    });

    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, false);
    
    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);
    
    function handleSwipe() {
        if (window.innerWidth < 1024) {
            if (touchEndX - touchStartX > 50 && !sidebarExpanded) {
                toggleSidebar();
            }
            if (touchStartX - touchEndX > 50 && sidebarExpanded) {
                toggleSidebar();
            }
        }
    }
</script>

</body>
</html>