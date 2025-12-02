<?php
session_start();
require_once '../config/db_conn.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow dean
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dean') {
    header("Location: ../public/index.php");
    exit();
}

// Get all quizzes with professor information
$quizzes = $conn->query("
    SELECT 
        q.*,
        u.firstname AS prof_firstname,
        u.lastname AS prof_lastname,
        u.email AS prof_email,
        l.title AS lesson_title,
        m.title AS module_title
    FROM quizzes q
    LEFT JOIN users u ON q.professor_id = u.id
    LEFT JOIN lessons l ON q.lesson_id = l.id
    LEFT JOIN modules m ON q.module_id = m.id
    ORDER BY q.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all modules with professor information
$modules = $conn->query("
    SELECT 
        m.*,
        u.firstname AS prof_firstname,
        u.lastname AS prof_lastname,
        u.email AS prof_email,
        COUNT(DISTINCT l.id) AS lesson_count
    FROM modules m
    LEFT JOIN users u ON m.professor_id = u.id
    LEFT JOIN lessons l ON m.id = l.module_id
    GROUP BY m.id
    ORDER BY m.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$totalQuizzes = count($quizzes);
$totalModules = count($modules);
$activeQuizzes = 0;
$inactiveQuizzes = 0;

foreach($quizzes as $quiz) {
    if($quiz['status'] === 'active') $activeQuizzes++;
    else $inactiveQuizzes++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quizzes & Modules - Dean Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-lg p-6 fixed h-full overflow-y-auto">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Dean Panel</h2>
    <nav class="space-y-4">
      <a href="dashboard.php" class="block text-gray-700 hover:text-blue-600 font-medium">🏠 Dashboard</a>
      <a href="professors.php" class="block text-gray-700 hover:text-blue-600 font-medium">👨‍🏫 Professors</a>
      <a href="students.php" class="block text-gray-700 hover:text-blue-600 font-medium">👩‍🎓 Students</a>
      <a href="quizzes.php" class="block text-blue-600 font-bold border-l-4 border-blue-600 pl-2">📝 Quizzes & Modules</a>
      <a href="../actions/logout_action.php" class="block text-red-600 font-semibold mt-4">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main content -->
  <main class="flex-1 ml-64 p-8">

    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-2">Quizzes & Modules Management</h1>
      <p class="text-gray-600">Overview of all published learning content</p>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-blue-100 text-sm font-medium mb-1">Total Quizzes</p>
            <p class="text-3xl font-bold"><?php echo $totalQuizzes; ?></p>
          </div>
          <div class="text-blue-200">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-green-100 text-sm font-medium mb-1">Active Quizzes</p>
            <p class="text-3xl font-bold"><?php echo $activeQuizzes; ?></p>
          </div>
          <div class="text-green-200">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-100 text-sm font-medium mb-1">Inactive Quizzes</p>
            <p class="text-3xl font-bold"><?php echo $inactiveQuizzes; ?></p>
          </div>
          <div class="text-gray-200">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-purple-100 text-sm font-medium mb-1">Total Modules</p>
            <p class="text-3xl font-bold"><?php echo $totalModules; ?></p>
          </div>
          <div class="text-purple-200">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-t-xl shadow-md">
      <div class="flex border-b">
        <button id="quizzesTab" class="flex-1 px-6 py-4 text-center font-semibold text-blue-600 border-b-2 border-blue-600 bg-blue-50" onclick="showTab('quizzes')">
          📝 Quizzes (<?php echo $totalQuizzes; ?>)
        </button>
        <button id="modulesTab" class="flex-1 px-6 py-4 text-center font-semibold text-gray-600 hover:bg-gray-50" onclick="showTab('modules')">
          📚 Modules (<?php echo $totalModules; ?>)
        </button>
      </div>
    </div>

    <!-- Quizzes Section -->
    <div id="quizzesSection" class="bg-white rounded-b-xl shadow-md">
      <!-- Search and Filter -->
      <div class="p-6 border-b">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input 
              type="text" 
              id="quizSearchInput" 
              placeholder="Search by quiz title, description, or professor..." 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>
          <div class="w-full md:w-48">
            <select 
              id="quizStatusFilter" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Quizzes Table -->
      <div class="overflow-x-auto">
        <?php if($totalQuizzes > 0): ?>
        <table class="min-w-full bg-white" id="quizzesTable">
          <thead class="bg-gray-100">
            <tr>
              <th class="text-left p-4 font-semibold text-gray-700">Quiz Title</th>
              <th class="text-left p-4 font-semibold text-gray-700">Description</th>
              <th class="text-left p-4 font-semibold text-gray-700">Module/Lesson</th>
              <th class="text-left p-4 font-semibold text-gray-700">Professor</th>
              <th class="text-left p-4 font-semibold text-gray-700">Status</th>
              <th class="text-left p-4 font-semibold text-gray-700">Time Limit</th>
              <th class="text-left p-4 font-semibold text-gray-700">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($quizzes as $quiz): ?>
            <tr class="border-b hover:bg-gray-50 transition duration-200" data-quiz='<?php echo json_encode($quiz); ?>'>
              <td class="p-4 text-gray-800 font-medium">
                <?php echo htmlspecialchars($quiz['title']); ?>
              </td>
              <td class="p-4 text-gray-600 text-sm">
                <?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . (strlen($quiz['description']) > 50 ? '...' : ''); ?>
              </td>
              <td class="p-4 text-gray-700 text-sm">
                <?php 
                if($quiz['module_title']) {
                  echo '<span class="text-purple-600 font-medium">📚 ' . htmlspecialchars($quiz['module_title']) . '</span>';
                }
                if($quiz['lesson_title']) {
                  echo '<br><span class="text-blue-600">📖 ' . htmlspecialchars($quiz['lesson_title']) . '</span>';
                }
                if(!$quiz['module_title'] && !$quiz['lesson_title']) {
                  echo '<span class="text-gray-400">Not assigned</span>';
                }
                ?>
              </td>
              <td class="p-4 text-gray-700">
                <?php 
                if($quiz['prof_firstname']) {
                  echo htmlspecialchars($quiz['prof_firstname'].' '.$quiz['prof_lastname']);
                } else {
                  echo '<span class="text-gray-400">Unknown</span>';
                }
                ?>
              </td>
              <td class="p-4">
                <?php 
                $statusColors = [
                  'active' => 'bg-green-100 text-green-700',
                  'inactive' => 'bg-gray-100 text-gray-700'
                ];
                $status = $quiz['status'];
                $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
                ?>
                <span class="px-3 py-1 <?php echo $colorClass; ?> rounded-full text-sm font-semibold uppercase">
                  <?php echo htmlspecialchars($status); ?>
                </span>
              </td>
              <td class="p-4 text-gray-700">
                <?php echo $quiz['time_limit'] ? htmlspecialchars($quiz['time_limit']) . ' min' : 'No limit'; ?>
              </td>
              <td class="p-4 text-gray-600 text-sm">
                <?php echo date('M d, Y', strtotime($quiz['created_at'])); ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="p-12 text-center">
          <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">No Quizzes Found</h3>
          <p class="text-gray-500">There are currently no quizzes in the system.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Modules Section -->
    <div id="modulesSection" class="bg-white rounded-b-xl shadow-md hidden">
      <!-- Search and Filter -->
      <div class="p-6 border-b">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input 
              type="text" 
              id="moduleSearchInput" 
              placeholder="Search by module title, description, or professor..." 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>
        </div>
      </div>

      <!-- Modules Grid -->
      <div class="p-6">
        <?php if($totalModules > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="modulesGrid">
          <?php foreach($modules as $module): ?>
          <div class="border border-gray-200 rounded-lg p-6 hover:shadow-lg transition duration-300 bg-white" data-module='<?php echo json_encode($module); ?>'>
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($module['title']); ?></h3>
                <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                  <?php echo htmlspecialchars(substr($module['description'], 0, 100)) . (strlen($module['description']) > 100 ? '...' : ''); ?>
                </p>
              </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4 space-y-2">
              <div class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span><?php echo htmlspecialchars($module['prof_firstname'].' '.$module['prof_lastname']); ?></span>
              </div>
              
              <div class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span><?php echo $module['lesson_count']; ?> Lesson<?php echo $module['lesson_count'] != 1 ? 's' : ''; ?></span>
              </div>
              
              <div class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span><?php echo date('M d, Y', strtotime($module['created_at'])); ?></span>
              </div>
              
              <?php if($module['subject']): ?>
              <div class="mt-3">
                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                  <?php echo htmlspecialchars($module['subject']); ?>
                </span>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-12 text-center">
          <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">No Modules Found</h3>
          <p class="text-gray-500">There are currently no modules in the system.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </main>

  <script>
    // Tab switching
    function showTab(tab) {
      const quizzesSection = document.getElementById('quizzesSection');
      const modulesSection = document.getElementById('modulesSection');
      const quizzesTab = document.getElementById('quizzesTab');
      const modulesTab = document.getElementById('modulesTab');

      if (tab === 'quizzes') {
        quizzesSection.classList.remove('hidden');
        modulesSection.classList.add('hidden');
        quizzesTab.classList.add('text-blue-600', 'border-b-2', 'border-blue-600', 'bg-blue-50');
        quizzesTab.classList.remove('text-gray-600');
        modulesTab.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600', 'bg-blue-50');
        modulesTab.classList.add('text-gray-600');
      } else {
        modulesSection.classList.remove('hidden');
        quizzesSection.classList.add('hidden');
        modulesTab.classList.add('text-blue-600', 'border-b-2', 'border-blue-600', 'bg-blue-50');
        modulesTab.classList.remove('text-gray-600');
        quizzesTab.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600', 'bg-blue-50');
        quizzesTab.classList.add('text-gray-600');
      }
    }

    // Quiz search and filter
    const quizSearchInput = document.getElementById('quizSearchInput');
    const quizStatusFilter = document.getElementById('quizStatusFilter');
    const quizTableRows = document.querySelectorAll('#quizzesTable tbody tr');

    function filterQuizzes() {
      const searchTerm = quizSearchInput.value.toLowerCase();
      const selectedStatus = quizStatusFilter.value.toLowerCase();

      quizTableRows.forEach(row => {
        const quizData = JSON.parse(row.getAttribute('data-quiz'));
        const title = (quizData.title || '').toLowerCase();
        const description = (quizData.description || '').toLowerCase();
        const professor = ((quizData.prof_firstname || '') + ' ' + (quizData.prof_lastname || '')).toLowerCase();
        const status = (quizData.status || '').toLowerCase();

        const matchesSearch = title.includes(searchTerm) || 
                            description.includes(searchTerm) || 
                            professor.includes(searchTerm);
        const matchesStatus = !selectedStatus || status === selectedStatus;

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    if(quizSearchInput) quizSearchInput.addEventListener('input', filterQuizzes);
    if(quizStatusFilter) quizStatusFilter.addEventListener('change', filterQuizzes);

    // Module search
    const moduleSearchInput = document.getElementById('moduleSearchInput');
    const moduleCards = document.querySelectorAll('#modulesGrid > div');

    function filterModules() {
      const searchTerm = moduleSearchInput.value.toLowerCase();

      moduleCards.forEach(card => {
        const moduleData = JSON.parse(card.getAttribute('data-module'));
        const title = (moduleData.title || '').toLowerCase();
        const description = (moduleData.description || '').toLowerCase();
        const professor = ((moduleData.prof_firstname || '') + ' ' + (moduleData.prof_lastname || '')).toLowerCase();
        const subject = (moduleData.subject || '').toLowerCase();

        const matchesSearch = title.includes(searchTerm) || 
                            description.includes(searchTerm) || 
                            professor.includes(searchTerm) ||
                            subject.includes(searchTerm);

        if (matchesSearch) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    }

    if(moduleSearchInput) moduleSearchInput.addEventListener('input', filterModules);
  </script>

</body>
</html>