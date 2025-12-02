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

// Stats
$totalProfessors = $conn->query("SELECT COUNT(*) FROM users WHERE role='professor'")->fetchColumn();
$totalStudents   = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalQuizzes    = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$pendingProfs    = $conn->query("SELECT COUNT(*) FROM users WHERE role='professor' AND status='pending'")->fetchColumn();

// Recent students
$recentStudents = $conn->query("
    SELECT firstname, lastname, email, created_at 
    FROM users 
    WHERE role='student' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Pending professors
$pendingProfessors = $conn->query("
    SELECT id, firstname, lastname, email, status 
    FROM users 
    WHERE role='professor' AND status='pending'
")->fetchAll(PDO::FETCH_ASSOC);

// Approved professors
$approvedProfessors = $conn->query("
    SELECT firstname, lastname, email, status, created_at 
    FROM users 
    WHERE role='professor' AND status='approved' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dean Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Inter', sans-serif;
    }
    
    .sidebar-icon {
        transition: all 0.2s;
    }
    
    .sidebar-icon:hover {
        transform: translateX(4px);
    }
    
    .stat-card {
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
    }

    /* Sidebar transition */
    aside {
        transition: width 0.3s ease;
    }

    aside.expanded {
        width: 16rem;
    }

    aside.collapsed {
        width: 4rem;
    }

    .sidebar-text {
        transition: opacity 0.2s ease;
    }

    aside.collapsed .sidebar-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }

    aside.expanded .sidebar-text {
        opacity: 1;
        width: auto;
    }

    /* Main content margin adjustment */
    main {
        transition: margin-left 0.3s ease;
    }

    main.expanded {
        margin-left: 16rem;
    }

    main.collapsed {
        margin-left: 4rem;
    }
  </style>
</head>
<body class="bg-gray-50">

  <!-- Sidebar -->
  <aside id="sidebar" class="fixed left-0 top-0 h-screen bg-white shadow-lg flex flex-col items-center py-6 z-50 collapsed">
    <div class="w-full px-4 mb-6 flex items-center justify-between">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-400 flex items-center justify-center flex-shrink-0">
            <span class="text-white font-bold text-sm">D</span>
        </div>
        <span class="sidebar-text ml-3 font-semibold text-gray-800 whitespace-nowrap">Dean Panel</span>
    </div>

    <!-- Toggle Button - Elegant Design -->
    <div class="w-full px-3 mb-6">
        <button onclick="toggleSidebar()" class="w-full flex items-center justify-center px-3 py-2.5 bg-gradient-to-r from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 border border-purple-200 rounded-xl transition-all duration-300 group">
            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center group-hover:shadow-md transition-shadow">
                <i id="toggleIcon" class="fas fa-chevron-right text-purple-600 text-sm"></i>
            </div>
            <span class="sidebar-text ml-2 text-sm font-medium text-purple-700 whitespace-nowrap">Expand Menu</span>
        </button>
    </div>
    
    <nav class="flex-1 flex flex-col space-y-2 w-full px-2">
        <a href="dashboard.php" class="sidebar-icon text-purple-600 flex items-center px-3 py-3 rounded-lg bg-purple-50">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
            </svg>
            <span class="sidebar-text ml-3 whitespace-nowrap font-medium">Dashboard</span>
        </a>
        <a href="professors.php" class="sidebar-icon text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center px-3 py-3 rounded-lg">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
            </svg>
            <span class="sidebar-text ml-3 whitespace-nowrap font-medium">Professors</span>
        </a>
        <a href="students.php" class="sidebar-icon text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center px-3 py-3 rounded-lg">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
            </svg>
            <span class="sidebar-text ml-3 whitespace-nowrap font-medium">Students</span>
        </a>
        <a href="quizzes.php" class="sidebar-icon text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center px-3 py-3 rounded-lg">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z" clip-rule="evenodd"></path>
            </svg>
            <span class="sidebar-text ml-3 whitespace-nowrap font-medium">Quizzes</span>
        </a>
    </nav>
    
    <div class="w-full px-2 mt-auto">
        <a href="../actions/logout_action.php" class="sidebar-icon text-red-600 hover:bg-red-50 flex items-center px-3 py-3 rounded-lg">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"></path>
            </svg>
            <span class="sidebar-text ml-3 whitespace-nowrap font-medium">Logout</span>
        </a>
    </div>
  </aside>

  <!-- Main content -->
  <main id="mainContent" class="min-h-screen collapsed">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-sm text-gray-500">Today</h1>
            <p class="text-sm text-gray-900 font-medium"><?php echo date('D, M d, Y'); ?></p>
        </div>
    </header>
    
    <div class="p-8">
      <!-- Welcome Banner -->
      <div class="bg-gradient-to-r from-purple-500 to-pink-400 rounded-3xl p-8 mb-8 text-white">
        <h1 class="text-3xl font-bold mb-2">Welcome back, Dean! 👋</h1>
        <p class="text-purple-50">Here's an overview of your institution's activity and management.</p>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Professors</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $totalProfessors; ?></p>
        </div>
        
        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Students</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $totalStudents; ?></p>
        </div>
        
        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z" clip-rule="evenodd"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Quizzes</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $totalQuizzes; ?></p>
        </div>
        
        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Pending Approvals</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $pendingProfs; ?></p>
        </div>
      </div>

      <!-- Pending Professors and Approved Professors -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Pending Professors -->
        <div class="bg-white rounded-2xl shadow-sm">
          <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <h2 class="text-lg font-semibold text-gray-900">Pending Professors</h2>
            </div>
            <a href="professors.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center">
              View All
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </a>
          </div>
          <div class="p-6">
            <?php if(count($pendingProfessors) > 0): ?>
            <table class="min-w-full">
              <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <th class="pb-3">Name</th>
                  <th class="pb-3">Email</th>
                  <th class="pb-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php foreach($pendingProfessors as $prof): ?>
                <tr class="hover:bg-gray-50">
                  <td class="py-3 text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($prof['firstname'].' '.$prof['lastname']); ?>
                  </td>
                  <td class="py-3 text-sm text-gray-600">
                    <?php echo htmlspecialchars($prof['email']); ?>
                  </td>
                  <td class="py-3 text-right space-x-2">
                    <form action="professor_approval.php" method="POST" class="inline-block">
                      <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                      <button name="action" value="approve" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600 transition">
                        ✓
                      </button>
                    </form>
                    <form action="professor_approval.php" method="POST" class="inline-block">
                      <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                      <button name="action" value="reject" class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition">
                        ✕
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
            <p class="text-center text-gray-500 py-8">No pending professors.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Approved Professors -->
        <div class="bg-white rounded-2xl shadow-sm">
          <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <h2 class="text-lg font-semibold text-gray-900">Approved Professors</h2>
            </div>
            <a href="professors.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center">
              View All
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </a>
          </div>
          <div class="p-6">
            <table class="min-w-full">
              <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <th class="pb-3">Name</th>
                  <th class="pb-3">Email</th>
                  <th class="pb-3 text-right">Joined</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php foreach($approvedProfessors as $prof): ?>
                <tr class="hover:bg-gray-50">
                  <td class="py-3 text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($prof['firstname'].' '.$prof['lastname']); ?>
                  </td>
                  <td class="py-3 text-sm text-gray-600">
                    <?php echo htmlspecialchars($prof['email']); ?>
                  </td>
                  <td class="py-3 text-sm text-gray-500 text-right">
                    <?php echo date('M d, Y', strtotime($prof['created_at'])); ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent Students -->
      <div class="bg-white rounded-2xl shadow-sm">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
            </svg>
            <h2 class="text-lg font-semibold text-gray-900">Recently Added Students</h2>
          </div>
          <a href="students.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center">
            View All
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </a>
        </div>
        <div class="p-6">
          <table class="min-w-full">
            <thead>
              <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <th class="pb-3">Name</th>
                <th class="pb-3">Email</th>
                <th class="pb-3 text-right">Joined</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach($recentStudents as $student): ?>
              <tr class="hover:bg-gray-50">
                <td class="py-3 text-sm font-medium text-gray-900">
                  <?php echo htmlspecialchars($student['firstname'].' '.$student['lastname']); ?>
                </td>
                <td class="py-3 text-sm text-gray-600">
                  <?php echo htmlspecialchars($student['email']); ?>
                </td>
                <td class="py-3 text-sm text-gray-500 text-right">
                  <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const toggleIcon = document.getElementById('toggleIcon');
      
      if (sidebar.classList.contains('collapsed')) {
        // Expand
        sidebar.classList.remove('collapsed');
        sidebar.classList.add('expanded');
        mainContent.classList.remove('collapsed');
        mainContent.classList.add('expanded');
        toggleIcon.classList.remove('fa-chevron-right');
        toggleIcon.classList.add('fa-chevron-left');
      } else {
        // Collapse
        sidebar.classList.remove('expanded');
        sidebar.classList.add('collapsed');
        mainContent.classList.remove('expanded');
        mainContent.classList.add('collapsed');
        toggleIcon.classList.remove('fa-chevron-left');
        toggleIcon.classList.add('fa-chevron-right');
      }
    }
  </script>

</body>
</html>