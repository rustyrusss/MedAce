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

// Get all students with their details
$students = $conn->query("
    SELECT firstname, lastname, email, section, student_id, year, created_at
    FROM users 
    WHERE role='student' 
    ORDER BY section ASC, lastname ASC, firstname ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = count($students);

// Get unique sections
$sections = $conn->query("
    SELECT DISTINCT section 
    FROM users 
    WHERE role='student' AND section IS NOT NULL AND section != ''
    ORDER BY section ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Get unique years
$years = $conn->query("
    SELECT DISTINCT year 
    FROM users 
    WHERE role='student' AND year IS NOT NULL AND year != ''
    ORDER BY year ASC
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Students - Dean Dashboard</title>
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
        <a href="dashboard.php" class="sidebar-icon text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center px-3 py-3 rounded-lg">
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
        <a href="students.php" class="sidebar-icon text-purple-600 flex items-center px-3 py-3 rounded-lg bg-purple-50">
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
      <!-- Title -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Students Management</h1>
        <p class="text-gray-600">Total Students: <span class="font-semibold text-purple-600"><?php echo $totalStudents; ?></span></p>
      </div>

      <!-- Stats Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Total Students</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $totalStudents; ?></p>
        </div>

        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Sections</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo count($sections); ?></p>
        </div>

        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Year Levels</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo count($years); ?></p>
        </div>
      </div>

      <!-- Search and Filter Section -->
      <div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input 
              type="text" 
              id="searchInput" 
              placeholder="Search by name, email, or student ID..." 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
          </div>
          <div class="w-full md:w-48">
            <select 
              id="sectionFilter" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
              <option value="">All Sections</option>
              <?php foreach($sections as $section): ?>
                <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="w-full md:w-48">
            <select 
              id="yearFilter" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
              <option value="">All Years</option>
              <?php foreach($years as $year): ?>
                <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Students Table -->
      <div class="bg-white rounded-2xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
          <h2 class="text-xl font-semibold text-gray-900">All Students</h2>
        </div>
        
        <?php if($totalStudents > 0): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white" id="studentsTable">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Student ID</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Name</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Email</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Section</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Year</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Joined</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach($students as $student): ?>
              <tr class="hover:bg-gray-50 transition duration-200" data-student='<?php echo json_encode($student); ?>'>
                <td class="p-4 text-gray-800 font-medium">
                  <?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?>
                </td>
                <td class="p-4 text-gray-900 font-medium">
                  <?php echo htmlspecialchars($student['firstname'].' '.$student['lastname']); ?>
                </td>
                <td class="p-4 text-gray-600">
                  <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="hover:text-purple-600 hover:underline">
                    <?php echo htmlspecialchars($student['email']); ?>
                  </a>
                </td>
                <td class="p-4">
                  <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                    <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?>
                  </span>
                </td>
                <td class="p-4 text-gray-700">
                  <?php echo htmlspecialchars($student['year'] ?? 'N/A'); ?>
                </td>
                <td class="p-4 text-gray-500 text-sm">
                  <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="p-12 text-center">
          <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">No Students Found</h3>
          <p class="text-gray-500">There are currently no enrolled students in the system.</p>
        </div>
        <?php endif; ?>
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

    // Search and filter functionality
    const searchInput = document.getElementById('searchInput');
    const sectionFilter = document.getElementById('sectionFilter');
    const yearFilter = document.getElementById('yearFilter');
    const tableRows = document.querySelectorAll('#studentsTable tbody tr');

    function filterTable() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedSection = sectionFilter.value.toLowerCase();
      const selectedYear = yearFilter.value.toLowerCase();

      tableRows.forEach(row => {
        const studentData = JSON.parse(row.getAttribute('data-student'));
        const name = (studentData.firstname + ' ' + studentData.lastname).toLowerCase();
        const email = (studentData.email || '').toLowerCase();
        const studentId = (studentData.student_id || '').toLowerCase();
        const section = (studentData.section || '').toLowerCase();
        const year = (studentData.year || '').toLowerCase();

        const matchesSearch = name.includes(searchTerm) || 
                            email.includes(searchTerm) || 
                            studentId.includes(searchTerm);
        const matchesSection = !selectedSection || section === selectedSection;
        const matchesYear = !selectedYear || year === selectedYear;

        if (matchesSearch && matchesSection && matchesYear) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    searchInput.addEventListener('input', filterTable);
    sectionFilter.addEventListener('change', filterTable);
    yearFilter.addEventListener('change', filterTable);
  </script>

</body>
</html>