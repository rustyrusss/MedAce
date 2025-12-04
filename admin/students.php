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

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $studentId = intval($_POST['student_id']);
    $section = trim($_POST['section']);
    $year = trim($_POST['year']);
    
    $updateStmt = $conn->prepare("UPDATE users SET section = ?, year = ? WHERE id = ? AND role = 'student'");
    if ($updateStmt->execute([$section, $year, $studentId])) {
        $_SESSION['success_message'] = "Student information updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update student information.";
    }
    header("Location: students.php");
    exit();
}

// Get all students with their details
$students = $conn->query("
    SELECT id, firstname, lastname, email, section, student_id, year, created_at
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

// Get success/error messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Dean Dashboard</title>
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-down {
            animation: slideDown 0.4s ease-out;
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            margin: 1rem;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
                <a href="professors.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chalkboard-teacher text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Professors</span>
                </a>
                <a href="students.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-user-graduate text-primary-600 w-5 text-center flex-shrink-0"></i>
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

    <!-- Edit Student Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="gradient-bg px-6 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-user-edit mr-3"></i>
                        Edit Student
                    </h2>
                    <button onclick="closeEditModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <form method="POST" action="" class="p-6">
                <input type="hidden" name="update_student" value="1">
                <input type="hidden" name="student_id" id="edit_student_id">

                <div class="space-y-4">
                    <!-- Student Name (Read-only) -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-primary-500 mr-2"></i>Student Name
                        </label>
                        <input type="text" id="edit_student_name" readonly 
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                    </div>

                    <!-- Student ID (Read-only) -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-id-card text-primary-500 mr-2"></i>Student ID
                        </label>
                        <input type="text" id="edit_student_id_display" readonly 
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                    </div>

                    <!-- Section -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-users text-primary-500 mr-2"></i>Section
                        </label>
                        <input type="text" name="section" id="edit_section" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                               placeholder="Enter section (e.g., A, B, 1A)">
                    </div>

                    <!-- Year -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt text-primary-500 mr-2"></i>Year Level
                        </label>
                        <select name="year" id="edit_year" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors shadow-sm">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

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
            <!-- Success/Error Messages -->
            <?php if ($successMessage): ?>
            <div class="mb-6 animate-slide-down">
                <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 flex items-start justify-between shadow-sm">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-green-800 font-medium"><?= htmlspecialchars($successMessage) ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div class="mb-6 animate-slide-down">
                <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 flex items-start justify-between shadow-sm">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-red-800 font-medium"><?= htmlspecialchars($errorMessage) ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Title -->
            <div class="mb-6 sm:mb-8 animate-fade-in-up">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Students Management</h1>
                <p class="text-gray-600 text-sm sm:text-base">Total Students: <span class="font-semibold text-primary-600"><?= $totalStudents ?></span></p>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-1 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-user-graduate text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Total Students</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $totalStudents ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-2 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-users text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Sections</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= count($sections) ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-3 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-calendar-alt text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Year Levels</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= count($years) ?></p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom p-4 sm:p-6 mb-6 sm:mb-8 border border-gray-100 animate-fade-in-up">
                <div class="flex flex-col gap-3 sm:gap-4">
                    <div class="flex-1">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by name, email, or student ID..." 
                            class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                        >
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                        <div class="flex-1 sm:w-48">
                            <select 
                                id="sectionFilter" 
                                class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                            >
                                <option value="">All Sections</option>
                                <?php foreach($sections as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 sm:w-48">
                            <select 
                                id="yearFilter" 
                                class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                            >
                                <option value="">All Years</option>
                                <?php foreach($years as $year): ?>
                                    <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900">All Students</h2>
                </div>
                
                <?php if($totalStudents > 0): ?>
                <div class="table-container">
                    <table class="min-w-full bg-white" id="studentsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Student ID</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Name</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider hidden md:table-cell">Email</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Section</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider hidden sm:table-cell">Year</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($students as $student): ?>
                            <tr class="table-row-hover" data-student='<?= json_encode($student) ?>'>
                                <td class="p-3 sm:p-4 text-gray-800 font-medium text-sm sm:text-base">
                                    <?= htmlspecialchars($student['student_id'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-900 font-medium text-sm sm:text-base">
                                    <?= htmlspecialchars($student['firstname'].' '.$student['lastname']) ?>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-600 text-sm sm:text-base hidden md:table-cell">
                                    <a href="mailto:<?= htmlspecialchars($student['email']) ?>" class="hover:text-primary-600 hover:underline">
                                        <?= htmlspecialchars($student['email']) ?>
                                    </a>
                                </td>
                                <td class="p-3 sm:p-4">
                                    <span class="badge bg-blue-100 text-blue-700 text-xs">
                                        <?= htmlspecialchars($student['section'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-700 text-sm sm:text-base hidden sm:table-cell">
                                    <?= htmlspecialchars($student['year'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3 sm:p-4">
                                    <button onclick='openEditModal(<?= json_encode($student) ?>)' 
                                            class="bg-primary-500 text-white px-3 py-1.5 rounded-lg hover:bg-primary-600 transition text-xs font-medium">
                                        <i class="fas fa-edit"></i>
                                        <span class="hidden sm:inline ml-1">Edit</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-8 sm:p-12 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-user-graduate text-5xl sm:text-6xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-700 mb-2">No Students Found</h3>
                    <p class="text-sm sm:text-base text-gray-500">There are currently no enrolled students in the system.</p>
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

    // Edit Modal Functions
    function openEditModal(student) {
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_student_name').value = student.firstname + ' ' + student.lastname;
        document.getElementById('edit_student_id_display').value = student.student_id || 'N/A';
        document.getElementById('edit_section').value = student.section || '';
        document.getElementById('edit_year').value = student.year || '';
        document.getElementById('editModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
            if (sidebarExpanded && window.innerWidth < 1024) {
                closeSidebar();
            }
        }
    });

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

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