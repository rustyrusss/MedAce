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

// Get all professors with their details
$professors = $conn->query("
    SELECT id, firstname, lastname, email, status, created_at
    FROM users 
    WHERE role='professor' 
    ORDER BY status DESC, lastname ASC, firstname ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$approvedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;

foreach($professors as $prof) {
    if($prof['status'] === 'approved') $approvedCount++;
    elseif($prof['status'] === 'pending') $pendingCount++;
    elseif($prof['status'] === 'rejected') $rejectedCount++;
}

$totalProfessors = count($professors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Professors - Dean Dashboard</title>
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
        <a href="professors.php" class="sidebar-icon text-purple-600 flex items-center px-3 py-3 rounded-lg bg-purple-50">
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
      <!-- Title -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Professors Management</h1>
        <p class="text-gray-600">Total Professors: <span class="font-semibold text-purple-600"><?php echo $totalProfessors; ?></span></p>
      </div>

      <!-- Stats Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Approved</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $approvedCount; ?></p>
        </div>

        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Pending</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $pendingCount; ?></p>
        </div>

        <div class="stat-card bg-white rounded-2xl shadow-sm p-6">
          <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium mb-1">Rejected</p>
          <p class="text-3xl font-bold text-gray-900"><?php echo $rejectedCount; ?></p>
        </div>
      </div>

      <!-- Search and Filter Section -->
      <div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input 
              type="text" 
              id="searchInput" 
              placeholder="Search by name or email..." 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
          </div>
          <div class="w-full md:w-48">
            <select 
              id="statusFilter" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
              <option value="">All Status</option>
              <option value="approved">Approved</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Pending Professors Section -->
      <?php if($pendingCount > 0): ?>
      <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 mb-8 rounded-2xl">
        <div class="flex items-center mb-4">
          <svg class="w-6 h-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          <h2 class="text-xl font-semibold text-yellow-800">Pending Approvals (<?php echo $pendingCount; ?>)</h2>
        </div>
        
        <div class="bg-white rounded-xl overflow-hidden shadow-sm">
          <table class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm">Name</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm">Email</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm">Applied</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              foreach($professors as $prof): 
                if($prof['status'] !== 'pending') continue;
              ?>
              <tr class="border-b border-gray-100 hover:bg-gray-50 transition duration-200">
                <td class="p-4 text-gray-900 font-medium">
                  <?php echo htmlspecialchars($prof['firstname'].' '.$prof['lastname']); ?>
                </td>
                <td class="p-4 text-gray-600">
                  <a href="mailto:<?php echo htmlspecialchars($prof['email']); ?>" class="hover:text-purple-600 hover:underline">
                    <?php echo htmlspecialchars($prof['email']); ?>
                  </a>
                </td>
                <td class="p-4 text-gray-500 text-sm">
                  <?php echo date('M d, Y', strtotime($prof['created_at'])); ?>
                </td>
                <td class="p-4 space-x-2">
                  <form action="professor_approval.php" method="POST" class="inline-block">
                    <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                    <button name="action" value="approve" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition duration-200 font-medium text-sm">
                      ✓ Approve
                    </button>
                  </form>
                  <form action="professor_approval.php" method="POST" class="inline-block">
                    <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                    <button name="action" value="reject" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200 font-medium text-sm">
                      ✕ Reject
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- All Professors Table -->
      <div class="bg-white rounded-2xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
          <h2 class="text-xl font-semibold text-gray-900">All Professors</h2>
        </div>
        
        <?php if($totalProfessors > 0): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white" id="professorsTable">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Name</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Email</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Status</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Joined</th>
                <th class="text-left p-4 font-semibold text-gray-700 text-sm uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach($professors as $prof): ?>
              <tr class="hover:bg-gray-50 transition duration-200" data-professor='<?php echo json_encode($prof); ?>'>
                <td class="p-4 text-gray-900 font-medium">
                  <?php echo htmlspecialchars($prof['firstname'].' '.$prof['lastname']); ?>
                </td>
                <td class="p-4 text-gray-600">
                  <a href="mailto:<?php echo htmlspecialchars($prof['email']); ?>" class="hover:text-purple-600 hover:underline">
                    <?php echo htmlspecialchars($prof['email']); ?>
                  </a>
                </td>
                <td class="p-4">
                  <?php 
                  $statusColors = [
                    'approved' => 'bg-green-100 text-green-700',
                    'pending' => 'bg-yellow-100 text-yellow-700',
                    'rejected' => 'bg-red-100 text-red-700'
                  ];
                  $status = $prof['status'];
                  $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
                  ?>
                  <span class="px-3 py-1 <?php echo $colorClass; ?> rounded-full text-xs font-semibold uppercase">
                    <?php echo htmlspecialchars($status); ?>
                  </span>
                </td>
                <td class="p-4 text-gray-500 text-sm">
                  <?php echo date('M d, Y', strtotime($prof['created_at'])); ?>
                </td>
                <td class="p-4">
                  <?php if($prof['status'] === 'pending'): ?>
                    <div class="flex gap-2">
                      <form action="professor_approval.php" method="POST" class="inline-block">
                        <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                        <button name="action" value="approve" class="bg-green-500 text-white px-3 py-1.5 rounded-lg hover:bg-green-600 transition text-xs font-medium">
                          Approve
                        </button>
                      </form>
                      <form action="professor_approval.php" method="POST" class="inline-block">
                        <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                        <button name="action" value="reject" class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-xs font-medium">
                          Reject
                        </button>
                      </form>
                    </div>
                  <?php elseif($prof['status'] === 'rejected'): ?>
                    <form action="professor_approval.php" method="POST" class="inline-block">
                      <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                      <button name="action" value="approve" class="bg-purple-500 text-white px-3 py-1.5 rounded-lg hover:bg-purple-600 transition text-xs font-medium">
                        Re-approve
                      </button>
                    </form>
                  <?php else: ?>
                    <form action="professor_approval.php" method="POST" class="inline-block">
                      <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                      <button name="action" value="reject" class="bg-gray-500 text-white px-3 py-1.5 rounded-lg hover:bg-gray-600 transition text-xs font-medium">
                        Revoke
                      </button>
                    </form>
                  <?php endif; ?>
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
          <h3 class="text-xl font-semibold text-gray-700 mb-2">No Professors Found</h3>
          <p class="text-gray-500">There are currently no professors in the system.</p>
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
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#professorsTable tbody tr');

    function filterTable() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedStatus = statusFilter.value.toLowerCase();

      tableRows.forEach(row => {
        const professorData = JSON.parse(row.getAttribute('data-professor'));
        const name = (professorData.firstname + ' ' + professorData.lastname).toLowerCase();
        const email = (professorData.email || '').toLowerCase();
        const status = (professorData.status || '').toLowerCase();

        const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
        const matchesStatus = !selectedStatus || status === selectedStatus;

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Confirmation for actions
    document.querySelectorAll('form button[name="action"]').forEach(button => {
      button.addEventListener('click', function(e) {
        const action = this.value;
        const form = this.closest('form');
        const professorRow = this.closest('tr');
        const professorName = professorRow.querySelector('td:first-child').textContent.trim();
        
        let message = '';
        if (action === 'approve') {
          message = `Are you sure you want to approve ${professorName}?`;
        } else if (action === 'reject') {
          message = `Are you sure you want to reject ${professorName}?`;
        }
        
        if (message && !confirm(message)) {
          e.preventDefault();
        }
      });
    });
  </script>

</body>
</html>