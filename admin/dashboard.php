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
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Dashboard - MedAce</title>
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

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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

        .animate-slide-in {
            animation: slideInRight 0.5s ease-out;
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon-2 {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon-3 {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
        }

        .stat-icon-4 {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-home text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="professors.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chalkboard-teacher text-gray-400 w-5 text-center flex-shrink-0"></i>
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
        <div class="px-3 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-full">
            <!-- Welcome Banner -->
            <div class="gradient-bg rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 mb-4 sm:mb-6 lg:mb-8 text-white shadow-custom-lg animate-fade-in-up">
                <h1 class="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-bold mb-1 sm:mb-2">Welcome back, Dean! 👋</h1>
                <p class="text-purple-100 text-xs sm:text-sm lg:text-base">Here's an overview of your institution's activity and management.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Total Professors -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-1 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-chalkboard-teacher text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Professors</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $totalProfessors ?></p>
                    </div>
                </div>

                <!-- Total Students -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-2 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-user-graduate text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Students</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $totalStudents ?></p>
                    </div>
                </div>

                <!-- Total Quizzes -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-3 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-clipboard-list text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Quizzes</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $totalQuizzes ?></p>
                    </div>
                </div>

                <!-- Pending Approvals -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-4 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-clock text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Pending Approvals</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $pendingProfs ?></p>
                    </div>
                </div>
            </div>

            <!-- Pending Professors and Approved Professors -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 mb-6 sm:mb-8">
                <!-- Pending Professors -->
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                    <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-clock text-yellow-600 mr-2"></i>
                                Pending Professors
                            </h2>
                            <a href="professors.php" class="text-xs sm:text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class="fas fa-arrow-right ml-1 sm:ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <?php if(count($pendingProfessors) > 0): ?>
                        <div class="table-container -mx-4 sm:-mx-6">
                            <table class="w-full min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Email</th>
                                        <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($pendingProfessors as $prof): ?>
                                    <tr class="table-row-hover">
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($prof['firstname'].' '.$prof['lastname']) ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-600 hidden sm:table-cell">
                                            <?= htmlspecialchars($prof['email']) ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 text-right space-x-2">
                                            <form action="professor_approval.php" method="POST" class="inline-block">
                                                <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                                <button name="action" value="approve" class="bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-green-600 transition">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form action="professor_approval.php" method="POST" class="inline-block">
                                                <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                                <button name="action" value="reject" class="bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-red-600 transition">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8 sm:py-12">
                            <i class="fas fa-user-check text-4xl sm:text-5xl text-gray-300 mb-3 sm:mb-4"></i>
                            <p class="text-gray-500 text-xs sm:text-sm">No pending professors.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Approved Professors -->
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden" style="animation-delay: 0.1s;">
                    <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                Approved Professors
                            </h2>
                            <a href="professors.php" class="text-xs sm:text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class="fas fa-arrow-right ml-1 sm:ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="table-container -mx-4 sm:-mx-6">
                            <table class="w-full min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Email</th>
                                        <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Joined</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($approvedProfessors as $prof): ?>
                                    <tr class="table-row-hover">
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($prof['firstname'].' '.$prof['lastname']) ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-600 hidden sm:table-cell">
                                            <?= htmlspecialchars($prof['email']) ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <span class="badge bg-gray-100 text-gray-700 text-xs">
                                                <?= date('M d, Y', strtotime($prof['created_at'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Students -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-user-graduate text-primary-600 mr-2"></i>
                            Recently Added Students
                        </h2>
                        <a href="students.php" class="text-xs sm:text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                            View All
                            <i class="fas fa-arrow-right ml-1 sm:ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="table-container -mx-4 sm:-mx-6">
                        <table class="w-full min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Email</th>
                                    <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($recentStudents as $student): ?>
                                <tr class="table-row-hover">
                                    <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($student['firstname'].' '.$student['lastname']) ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-gray-600 hidden sm:table-cell">
                                        <?= htmlspecialchars($student['email']) ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                        <span class="badge bg-gray-100 text-gray-700 text-xs">
                                            <?= date('M d, Y', strtotime($student['created_at'])) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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