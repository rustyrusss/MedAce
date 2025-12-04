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

$deanId = $_SESSION['user_id'];

// Fetch dean info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$deanId]);
$dean = $stmt->fetch(PDO::FETCH_ASSOC);
$deanName = $dean ? $dean['firstname'] . " " . $dean['lastname'] : "Dean";

// Default avatar
if (!empty($dean['gender'])) {
    if (strtolower($dean['gender']) === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif (strtolower($dean['gender']) === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}

$profilePic = !empty($dean['profile_pic']) ? "../" . $dean['profile_pic'] : $defaultAvatar;

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
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professors - Dean Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#faf5ff',
                            100: '#f3e8ff',
                            200: '#e9d5ff',
                            300: '#d8b4fe',
                            400: '#c084fc',
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7e22ce',
                            800: '#6b21a8',
                            900: '#581c87',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-scale-in {
            animation: scaleIn 0.4s ease-out;
        }

        .shadow-custom {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        }

        .shadow-custom-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .stat-icon-1 {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon-2 {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon-3 {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .table-row-hover {
            transition: background-color 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: #faf5ff;
        }

        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (min-width: 1024px) {
            .sidebar-collapsed {
                width: 5rem;
                transform: translateX(0);
            }

            .sidebar-collapsed .nav-text,
            .sidebar-collapsed .profile-info {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }

            .sidebar-expanded .nav-text,
            .sidebar-expanded .profile-info {
                opacity: 1;
                width: auto;
            }
        }

        @media (max-width: 1023px) {
            .sidebar-collapsed {
                width: 18rem;
                transform: translateX(-100%);
            }

            .sidebar-collapsed .nav-text,
            .sidebar-collapsed .profile-info {
                opacity: 1;
                width: auto;
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }

            .sidebar-expanded .nav-text,
            .sidebar-expanded .profile-info {
                opacity: 1;
                width: auto;
            }
        }

        .sidebar-toggle-btn {
            width: 40px;
            height: 40px;
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0;
            flex-shrink: 0;
        }

        .sidebar-toggle-btn:hover {
            border-color: #a855f7;
            background: #faf5ff;
        }

        .sidebar-toggle-btn:active {
            transform: scale(0.95);
        }

        .sidebar-toggle-btn .toggle-icon {
            width: 24px;
            height: 24px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle-btn .toggle-icon::before {
            content: '';
            position: absolute;
            left: 2px;
            width: 3px;
            height: 16px;
            background-color: #64748b;
            border-radius: 2px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-toggle-btn .toggle-icon::after {
            content: '';
            position: absolute;
            right: 2px;
            width: 6px;
            height: 6px;
            border-right: 2px solid #64748b;
            border-bottom: 2px solid #64748b;
            transform: rotate(-45deg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-toggle-btn:hover .toggle-icon::before,
        .sidebar-toggle-btn:hover .toggle-icon::after {
            border-color: #a855f7;
            background-color: #a855f7;
        }

        .sidebar-toggle-btn.active .toggle-icon::after {
            transform: rotate(135deg);
            right: 4px;
        }

        .sidebar-toggle-btn.active .toggle-icon::before {
            background-color: #a855f7;
        }

        .sidebar-toggle-btn.active {
            border-color: #a855f7;
            background: #faf5ff;
        }

        @media (max-width: 1023px) {
            .sidebar-toggle-btn {
                width: 36px;
                height: 36px;
            }

            .sidebar-toggle-btn .toggle-icon {
                width: 20px;
                height: 20px;
            }

            .sidebar-toggle-btn .toggle-icon::before {
                height: 14px;
            }

            .sidebar-toggle-btn .toggle-icon::after {
                width: 5px;
                height: 5px;
            }
        }

        #sidebar-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        #sidebar-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width: 1023px) {
            #main-content {
                margin-left: 0 !important;
            }

            #sidebar-overlay {
                display: none;
            }

            #sidebar-overlay.show {
                display: block;
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        body {
            overflow-x: hidden;
        }

        #main-content {
            max-width: 100vw;
            overflow-x: hidden;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-container::-webkit-scrollbar {
            height: 6px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition sidebar-collapsed">
        <div class="flex flex-col h-full">
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between px-4 py-5 border-b border-gray-200">
                <div class="flex items-center space-x-3 min-w-0 flex-1">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0 flex-1">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($deanName))) ?></h3>
                        <p class="text-xs text-gray-500">Dean</p>
                    </div>
                </div>
            </div>

            <!-- Toggle Button -->
            <div class="px-4 py-3 border-b border-gray-200">
                <button onclick="toggleSidebar()" class="sidebar-toggle-btn w-full" id="hamburgerBtn" aria-label="Toggle sidebar">
                    <div class="toggle-icon"></div>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="professors.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-chalkboard-teacher text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Professors</span>
                </a>
                <a href="students.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-user-graduate text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Students</span>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
            </nav>

            <!-- Logout Button -->
            <div class="px-3 py-4 border-t border-gray-200">
                <a href="../actions/logout_action.php" class="flex items-center space-x-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-all">
                    <i class="fas fa-sign-out-alt w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 w-full transition-all duration-300 lg:ml-20">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-3 sm:px-6 lg:px-8 py-3 sm:py-4">
            <div class="flex items-center justify-between gap-3">
                <button onclick="toggleSidebar()" class="sidebar-toggle-btn lg:hidden" id="mobileHamburgerBtn" aria-label="Toggle sidebar">
                    <div class="toggle-icon"></div>
                </button>
                <div class="flex items-center space-x-3 sm:space-x-4 ml-auto">
                    <div class="text-right">
                        <p class="text-xs text-gray-500 hidden sm:block">Today</p>
                        <p class="text-xs sm:text-sm font-semibold text-gray-900"><?= date('D, M d, Y') ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-3 sm:px-6 lg:px-8 py-4 sm:py-6 lg:px-8 max-w-full">
            <!-- Title -->
            <div class="mb-6 sm:mb-8 animate-fade-in-up">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Professors Management</h1>
                <p class="text-gray-600 text-sm sm:text-base">Total Professors: <span class="font-semibold text-primary-600"><?= $totalProfessors ?></span></p>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-1 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-check-circle text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Approved</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $approvedCount ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-2 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-clock text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Pending</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $pendingCount ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-3 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-times-circle text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Rejected</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $rejectedCount ?></p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom p-4 sm:p-6 mb-6 sm:mb-8 border border-gray-100 animate-fade-in-up">
                <div class="flex flex-col md:flex-row gap-3 sm:gap-4">
                    <div class="flex-1">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by name or email..." 
                            class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                        >
                    </div>
                    <div class="w-full md:w-48">
                        <select 
                            id="statusFilter" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
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
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 sm:p-6 mb-6 sm:mb-8 rounded-xl sm:rounded-2xl shadow-custom animate-fade-in-up">
                <div class="flex items-center mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 text-lg sm:text-xl"></i>
                    <h2 class="text-lg sm:text-xl font-semibold text-yellow-800">Pending Approvals (<?= $pendingCount ?>)</h2>
                </div>
                
                <div class="bg-white rounded-lg sm:rounded-xl overflow-hidden shadow-sm">
                    <div class="table-container">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs sm:text-sm">Name</th>
                                    <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs sm:text-sm hidden sm:table-cell">Email</th>
                                    <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs sm:text-sm hidden md:table-cell">Applied</th>
                                    <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs sm:text-sm">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach($professors as $prof): 
                                    if($prof['status'] !== 'pending') continue;
                                ?>
                                <tr class="border-b border-gray-100 table-row-hover">
                                    <td class="p-3 sm:p-4 text-gray-900 font-medium text-sm sm:text-base">
                                        <?= htmlspecialchars($prof['firstname'].' '.$prof['lastname']) ?>
                                    </td>
                                    <td class="p-3 sm:p-4 text-gray-600 text-sm sm:text-base hidden sm:table-cell">
                                        <a href="mailto:<?= htmlspecialchars($prof['email']) ?>" class="hover:text-primary-600 hover:underline">
                                            <?= htmlspecialchars($prof['email']) ?>
                                        </a>
                                    </td>
                                    <td class="p-3 sm:p-4 text-gray-500 text-xs sm:text-sm hidden md:table-cell">
                                        <?= date('M d, Y', strtotime($prof['created_at'])) ?>
                                    </td>
                                    <td class="p-3 sm:p-4">
                                        <div class="flex gap-2">
                                            <form action="professor_approval.php" method="POST" class="inline-block">
                                                <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                                <button name="action" value="approve" class="bg-green-500 text-white px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg hover:bg-green-600 transition font-medium text-xs sm:text-sm">
                                                    <i class="fas fa-check"></i><span class="hidden sm:inline ml-1">Approve</span>
                                                </button>
                                            </form>
                                            <form action="professor_approval.php" method="POST" class="inline-block">
                                                <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                                <button name="action" value="reject" class="bg-red-500 text-white px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg hover:bg-red-600 transition font-medium text-xs sm:text-sm">
                                                    <i class="fas fa-times"></i><span class="hidden sm:inline ml-1">Reject</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- All Professors Table -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900">All Professors</h2>
                </div>
                
                <?php if($totalProfessors > 0): ?>
                <div class="table-container">
                    <table class="min-w-full bg-white" id="professorsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Name</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider hidden md:table-cell">Email</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Status</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider hidden sm:table-cell">Joined</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($professors as $prof): ?>
                            <tr class="table-row-hover" data-professor='<?= json_encode($prof) ?>'>
                                <td class="p-3 sm:p-4 text-gray-900 font-medium text-sm sm:text-base">
                                    <?= htmlspecialchars($prof['firstname'].' '.$prof['lastname']) ?>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-600 text-sm sm:text-base hidden md:table-cell">
                                    <a href="mailto:<?= htmlspecialchars($prof['email']) ?>" class="hover:text-primary-600 hover:underline">
                                        <?= htmlspecialchars($prof['email']) ?>
                                    </a>
                                </td>
                                <td class="p-3 sm:p-4">
                                    <?php 
                                    $statusColors = [
                                        'approved' => 'bg-green-100 text-green-700',
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'rejected' => 'bg-red-100 text-red-700'
                                    ];
                                    $status = $prof['status'];
                                    $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span class="badge <?= $colorClass ?> text-xs">
                                        <?= htmlspecialchars(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-500 text-xs sm:text-sm hidden sm:table-cell">
                                    <?= date('M d, Y', strtotime($prof['created_at'])) ?>
                                </td>
                                <td class="p-3 sm:p-4">
                                    <?php if($prof['status'] === 'pending'): ?>
                                        <div class="flex gap-2">
                                            <form action="professor_approval.php" method="POST" class="inline-block">
                                                <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                                <button name="action" value="approve" class="bg-green-500 text-white px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg hover:bg-green-600 transition text-xs font-medium">
                                                    Approve
                                                </button>
                                            </form>
                                            <form action="professor_approval.php" method="POST" class="inline-block">
                                                <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                                <button name="action" value="reject" class="bg-red-500 text-white px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg hover:bg-red-600 transition text-xs font-medium">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif($prof['status'] === 'rejected'): ?>
                                        <form action="professor_approval.php" method="POST" class="inline-block">
                                            <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                            <button name="action" value="approve" class="bg-primary-500 text-white px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg hover:bg-primary-600 transition text-xs font-medium">
                                                Re-approve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="professor_approval.php" method="POST" class="inline-block">
                                            <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                            <button name="action" value="reject" class="bg-gray-500 text-white px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg hover:bg-gray-600 transition text-xs font-medium">
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
                <div class="p-8 sm:p-12 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-chalkboard-teacher text-5xl sm:text-6xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-700 mb-2">No Professors Found</h3>
                    <p class="text-sm sm:text-base text-gray-500">There are currently no professors in the system.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
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

    document.addEventListener('DOMContentLoaded', function() {
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
                if (sidebarExpanded && window.innerWidth < 1024) {
                    closeSidebar();
                }
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