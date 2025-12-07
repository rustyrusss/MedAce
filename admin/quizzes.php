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

// Handle quiz publish/unpublish actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_quiz_status'])) {
        $quizId = intval($_POST['quiz_id']);
        $action = $_POST['new_status'];
        
        // Convert to database values: 'active' or 'inactive'
        $newStatus = ($action === 'published' || $action === 'active') ? 'active' : 'inactive';
        
        try {
            $stmt = $conn->prepare("UPDATE quizzes SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
            $stmt->bindParam(':id', $quizId, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Quiz " . ($newStatus === 'active' ? 'published' : 'unpublished') . " successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update quiz status.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
        header("Location: quizzes.php");
        exit();
    }
    
    if (isset($_POST['toggle_module_status'])) {
        $moduleId = intval($_POST['module_id']);
        $action = $_POST['new_status'];
        
        // Convert to database values: 'active' or 'inactive'
        $newStatus = ($action === 'published' || $action === 'active') ? 'active' : 'inactive';
        
        try {
            $stmt = $conn->prepare("UPDATE modules SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
            $stmt->bindParam(':id', $moduleId, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Module " . ($newStatus === 'active' ? 'published' : 'unpublished') . " successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update module status.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
        header("Location: quizzes.php");
        exit();
    }

    // Bulk actions for quizzes
    if (isset($_POST['bulk_quiz_action'])) {
        $quizIds = $_POST['quiz_ids'] ?? [];
        $action = $_POST['bulk_quiz_action'];
        
        if (!empty($quizIds)) {
            // Use 'active' for publish and 'inactive' for unpublish
            $newStatus = ($action === 'publish') ? 'active' : 'inactive';
            $placeholders = implode(',', array_fill(0, count($quizIds), '?'));
            $stmt = $conn->prepare("UPDATE quizzes SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$newStatus], $quizIds);
            
            if ($stmt->execute($params)) {
                $_SESSION['success_message'] = count($quizIds) . " quiz(zes) " . ($action === 'publish' ? 'published' : 'unpublished') . " successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update quizzes.";
            }
        }
        header("Location: quizzes.php");
        exit();
    }

    // Bulk actions for modules
    if (isset($_POST['bulk_module_action'])) {
        $moduleIds = $_POST['module_ids'] ?? [];
        $action = $_POST['bulk_module_action'];
        
        if (!empty($moduleIds)) {
            // Use 'active' for publish and 'inactive' for unpublish
            $newStatus = ($action === 'publish') ? 'active' : 'inactive';
            $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
            $stmt = $conn->prepare("UPDATE modules SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$newStatus], $moduleIds);
            
            if ($stmt->execute($params)) {
                $_SESSION['success_message'] = count($moduleIds) . " module(s) " . ($action === 'publish' ? 'published' : 'unpublished') . " successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update modules.";
            }
        }
        header("Location: quizzes.php");
        exit();
    }
}

// Get pending counts for sidebar
$pendingStudents = $conn->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='pending'")->fetchColumn();
$pendingProfs = $conn->query("SELECT COUNT(*) FROM users WHERE role='professor' AND status='pending'")->fetchColumn();

// Get all quizzes with professor info
$quizzes = $conn->query("
    SELECT q.*, u.firstname as prof_firstname, u.lastname as prof_lastname, u.email as prof_email
    FROM quizzes q
    LEFT JOIN users u ON q.professor_id = u.id
    ORDER BY q.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalQuizzes = count($quizzes);
$publishedQuizzes = count(array_filter($quizzes, function($q) {
    // 'inactive' is the only unpublished state
    return ($q['status'] !== 'inactive');
}));
$unpublishedQuizzes = $totalQuizzes - $publishedQuizzes;

// Get all modules with professor info (if modules table exists)
$modules = [];
$totalModules = 0;
$publishedModules = 0;
$unpublishedModules = 0;

try {
    $modules = $conn->query("
        SELECT m.*, u.firstname as prof_firstname, u.lastname as prof_lastname, u.email as prof_email
        FROM modules m
        LEFT JOIN users u ON m.professor_id = u.id
        ORDER BY m.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $totalModules = count($modules);
    $publishedModules = count(array_filter($modules, function($m) {
        // 'inactive' is the only unpublished state
        return ($m['status'] !== 'inactive');
    }));
    $unpublishedModules = $totalModules - $publishedModules;
} catch (PDOException $e) {
    // Modules table might not exist
    $modules = [];
}

// Get unique professors who have created content
$professors = $conn->query("
    SELECT DISTINCT u.id, u.firstname, u.lastname 
    FROM users u 
    WHERE u.role = 'professor' AND u.status = 'approved'
    ORDER BY u.lastname, u.firstname
")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Quizzes & Modules - Dean Dashboard</title>
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
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
        }

        .stat-icon-2 {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon-3 {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .stat-icon-4 {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        }

        .stat-icon-5 {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon-6 {
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

        /* Tab styles */
        .tab-btn {
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #9333ea;
            border-bottom-color: #9333ea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Checkbox styling */
        .custom-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.375rem;
            cursor: pointer;
            accent-color: #9333ea;
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
            max-width: 600px;
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
                    <?php if($pendingProfs > 0): ?>
                    <span class="nav-text sidebar-transition ml-auto bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pendingProfs ?></span>
                    <?php endif; ?>
                </a>
                <a href="students.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-user-graduate text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Students</span>
                    <?php if($pendingStudents > 0): ?>
                    <span class="nav-text sidebar-transition ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pendingStudents ?></span>
                    <?php endif; ?>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-primary-600 w-5 text-center flex-shrink-0"></i>
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

    <!-- View Quiz Modal -->
    <div id="viewQuizModal" class="modal">
        <div class="modal-content">
            <div class="gradient-bg px-6 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clipboard-list mr-3"></i>
                        <span id="modalQuizTitle">Quiz Details</span>
                    </h2>
                    <button onclick="closeViewModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6" id="modalQuizContent">
                <!-- Content will be populated by JavaScript -->
            </div>
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
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Quizzes & Modules Management</h1>
                <p class="text-gray-600 text-sm sm:text-base">View, manage, and control the visibility of all quizzes and modules</p>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Total Quizzes -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <div class="stat-icon-1 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-clipboard-list text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs font-medium mb-1">Total Quizzes</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900"><?= $totalQuizzes ?></p>
                </div>

                <!-- Published Quizzes -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.05s;">
                    <div class="flex items-center justify-between mb-3">
                        <div class="stat-icon-2 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-eye text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs font-medium mb-1">Published</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-green-600"><?= $publishedQuizzes ?></p>
                </div>

                <!-- Unpublished Quizzes -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-3">
                        <div class="stat-icon-3 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-eye-slash text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs font-medium mb-1">Unpublished</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-red-600"><?= $unpublishedQuizzes ?></p>
                </div>

                <!-- Total Modules -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.15s;">
                    <div class="flex items-center justify-between mb-3">
                        <div class="stat-icon-4 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-book text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs font-medium mb-1">Total Modules</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900"><?= $totalModules ?></p>
                </div>

                <!-- Published Modules -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-3">
                        <div class="stat-icon-5 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-book-open text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs font-medium mb-1">Mod. Published</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-green-600"><?= $publishedModules ?></p>
                </div>

                <!-- Unpublished Modules -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.25s;">
                    <div class="flex items-center justify-between mb-3">
                        <div class="stat-icon-6 w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-book text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs font-medium mb-1">Mod. Unpublished</h3>
                    <p class="text-2xl sm:text-3xl font-bold text-amber-600"><?= $unpublishedModules ?></p>
                </div>
            </div>

            <!-- Main Content Tabs -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                <!-- Tabs Header -->
                <div class="border-b border-gray-200">
                    <div class="flex">
                        <button onclick="switchTab('quizzes')" id="tab-quizzes" class="tab-btn active flex-1 py-4 px-6 text-sm font-semibold border-b-2 border-primary-500 text-primary-600 focus:outline-none">
                            <i class="fas fa-clipboard-list mr-2"></i>Quizzes <span class="bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full text-xs ml-1"><?= $totalQuizzes ?></span>
                        </button>
                        <button onclick="switchTab('modules')" id="tab-modules" class="tab-btn flex-1 py-4 px-6 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 focus:outline-none">
                            <i class="fas fa-book mr-2"></i>Modules <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?= $totalModules ?></span>
                        </button>
                    </div>
                </div>

                <!-- Quizzes Tab Content -->
                <div id="content-quizzes" class="tab-content active">
                    <!-- Search and Filter -->
                    <div class="p-4 sm:p-6 border-b border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <div class="flex-1">
                                <input type="text" id="quizSearchInput" placeholder="Search quizzes..." 
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div class="flex gap-3">
                                <select id="quizStatusFilter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm">
                                    <option value="">All Status</option>
                                    <option value="published">Published</option>
                                    <option value="unpublished">Unpublished</option>
                                </select>
                                <select id="quizProfFilter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm">
                                    <option value="">All Professors</option>
                                    <?php foreach($professors as $prof): ?>
                                    <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['firstname'] . ' ' . $prof['lastname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <form method="POST" id="quizBulkForm" class="mt-4 flex flex-wrap gap-2 items-center">
                            <span class="text-sm text-gray-600 mr-2">Bulk Actions:</span>
                            <button type="submit" name="bulk_quiz_action" value="publish" onclick="return confirmBulkQuizAction('publish')"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50" id="bulkPublishQuiz">
                                <i class="fas fa-eye mr-1"></i> Publish Selected
                            </button>
                            <button type="submit" name="bulk_quiz_action" value="unpublish" onclick="return confirmBulkQuizAction('unpublish')"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50" id="bulkUnpublishQuiz">
                                <i class="fas fa-eye-slash mr-1"></i> Unpublish Selected
                            </button>
                            <span class="text-sm text-gray-500 ml-2" id="quizSelectedCount">0 selected</span>
                        </form>
                    </div>

                    <!-- Quizzes Table -->
                    <?php if($totalQuizzes > 0): ?>
                    <div class="table-container">
                        <table class="w-full min-w-full" id="quizzesTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 sm:px-6 py-3 text-left">
                                        <input type="checkbox" id="selectAllQuizzes" class="custom-checkbox" onchange="toggleAllQuizzes(this)">
                                    </th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Quiz Title</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell">Professor</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">Created</th>
                                    <th class="px-4 sm:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-4 sm:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            <?php foreach($quizzes as $quiz): 
                                    $quizStatus = $quiz['status'];
                                    // Database uses 'active' and 'inactive'
                                    // 'inactive' means unpublished, everything else (including NULL, empty, 'active') means published
                                    $isPublished = ($quizStatus !== 'inactive');
                                ?>
                                <tr class="table-row-hover quiz-row" 
                                    data-quiz='<?= htmlspecialchars(json_encode($quiz), ENT_QUOTES, "UTF-8") ?>'
                                    data-status="<?= $isPublished ? 'published' : 'unpublished' ?>"
                                    data-prof="<?= $quiz['professor_id'] ?? '' ?>">
                                    <td class="px-4 sm:px-6 py-4">
                                        <input type="checkbox" name="quiz_ids[]" value="<?= $quiz['id'] ?>" form="quizBulkForm" 
                                               class="custom-checkbox quiz-checkbox" onchange="updateQuizSelectedCount()">
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center mr-3 flex-shrink-0">
                                                <i class="fas fa-clipboard-list text-primary-600"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($quiz['title'] ?? 'Untitled Quiz') ?></p>
                                                <p class="text-xs text-gray-500 truncate hidden sm:block"><?= htmlspecialchars(substr($quiz['description'] ?? 'No description', 0, 50)) ?>...</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars(($quiz['prof_firstname'] ?? '') . ' ' . ($quiz['prof_lastname'] ?? 'Unknown')) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($quiz['prof_email'] ?? '') ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500 hidden lg:table-cell">
                                        <?= isset($quiz['created_at']) ? date('M d, Y', strtotime($quiz['created_at'])) : 'N/A' ?>
                                        <br>
                                        <span class="text-xs"><?= isset($quiz['created_at']) ? date('h:i A', strtotime($quiz['created_at'])) : '' ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-center">
                                        <?php if($isPublished): ?>
                                        <span class="badge bg-green-100 text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i>Published
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-gray-200 text-gray-600">
                                            <i class="fas fa-eye-slash mr-1"></i>Unpublished
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='viewQuizDetails(<?= htmlspecialchars(json_encode($quiz), ENT_QUOTES, "UTF-8") ?>)'
                                                    class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="toggle_quiz_status" value="1">
                                                <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
                                                <?php if($isPublished): ?>
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" onclick="return confirm('Unpublish this quiz?')" 
                                                        class="bg-red-100 text-red-600 hover:bg-red-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                                <?php else: ?>
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" onclick="return confirm('Publish this quiz?')" 
                                                        class="bg-green-100 text-green-600 hover:bg-green-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-clipboard-list text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Quizzes Found</h3>
                        <p class="text-gray-500">There are currently no quizzes in the system.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Modules Tab Content -->
                <div id="content-modules" class="tab-content">
                    <!-- Search and Filter -->
                    <div class="p-4 sm:p-6 border-b border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <div class="flex-1">
                                <input type="text" id="moduleSearchInput" placeholder="Search modules..." 
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div class="flex gap-3">
                                <select id="moduleStatusFilter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm">
                                    <option value="">All Status</option>
                                    <option value="published">Published</option>
                                    <option value="unpublished">Unpublished</option>
                                </select>
                                <select id="moduleProfFilter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm">
                                    <option value="">All Professors</option>
                                    <?php foreach($professors as $prof): ?>
                                    <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['firstname'] . ' ' . $prof['lastname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <form method="POST" id="moduleBulkForm" class="mt-4 flex flex-wrap gap-2 items-center">
                            <span class="text-sm text-gray-600 mr-2">Bulk Actions:</span>
                            <button type="submit" name="bulk_module_action" value="publish" onclick="return confirmBulkModuleAction('publish')"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                <i class="fas fa-eye mr-1"></i> Publish Selected
                            </button>
                            <button type="submit" name="bulk_module_action" value="unpublish" onclick="return confirmBulkModuleAction('unpublish')"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                <i class="fas fa-eye-slash mr-1"></i> Unpublish Selected
                            </button>
                            <span class="text-sm text-gray-500 ml-2" id="moduleSelectedCount">0 selected</span>
                        </form>
                    </div>

                    <!-- Modules Table -->
                    <?php if($totalModules > 0): ?>
                    <div class="table-container">
                        <table class="w-full min-w-full" id="modulesTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 sm:px-6 py-3 text-left">
                                        <input type="checkbox" id="selectAllModules" class="custom-checkbox" onchange="toggleAllModules(this)">
                                    </th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Module Title</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell">Professor</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">Created</th>
                                    <th class="px-4 sm:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-4 sm:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($modules as $module): 
                                    $moduleStatus = $module['status'];
                                    // Database uses 'active' and 'inactive'
                                    // 'inactive' means unpublished, everything else means published
                                    $isPublished = ($moduleStatus !== 'inactive');
                                ?>
                                <tr class="table-row-hover module-row" 
                                    data-module='<?= htmlspecialchars(json_encode($module), ENT_QUOTES, "UTF-8") ?>'
                                    data-status="<?= $isPublished ? 'published' : 'unpublished' ?>"
                                    data-prof="<?= $module['professor_id'] ?? '' ?>">
                                    <td class="px-4 sm:px-6 py-4">
                                        <input type="checkbox" name="module_ids[]" value="<?= $module['id'] ?>" form="moduleBulkForm" 
                                               class="custom-checkbox module-checkbox" onchange="updateModuleSelectedCount()">
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center mr-3 flex-shrink-0">
                                                <i class="fas fa-book text-cyan-600"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($module['title'] ?? 'Untitled Module') ?></p>
                                                <p class="text-xs text-gray-500 truncate hidden sm:block"><?= htmlspecialchars(substr($module['description'] ?? 'No description', 0, 50)) ?>...</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars(($module['prof_firstname'] ?? '') . ' ' . ($module['prof_lastname'] ?? 'Unknown')) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($module['prof_email'] ?? '') ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500 hidden lg:table-cell">
                                        <?= isset($module['created_at']) ? date('M d, Y', strtotime($module['created_at'])) : 'N/A' ?>
                                        <br>
                                        <span class="text-xs"><?= isset($module['created_at']) ? date('h:i A', strtotime($module['created_at'])) : '' ?></span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-center">
                                        <?php if($isPublished): ?>
                                        <span class="badge bg-green-100 text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i>Published
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-gray-200 text-gray-600">
                                            <i class="fas fa-eye-slash mr-1"></i>Unpublished
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="window.location.href='view_module.php?id=<?= $module['id'] ?>'"
        class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
    <i class="fas fa-eye"></i>
</button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="toggle_module_status" value="1">
                                                <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                                                <?php if($isPublished): ?>
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" onclick="return confirm('Unpublish this module?')" 
                                                        class="bg-red-100 text-red-600 hover:bg-red-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                                <?php else: ?>
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" onclick="return confirm('Publish this module?')" 
                                                        class="bg-green-100 text-green-600 hover:bg-green-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-book text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Modules Found</h3>
                        <p class="text-gray-500">There are currently no modules in the system.</p>
                    </div>
                    <?php endif; ?>
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

    // Tab switching function
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'text-primary-600', 'border-primary-500');
            btn.classList.add('text-gray-500', 'border-transparent');
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        const selectedTab = document.getElementById('tab-' + tabName);
        const selectedContent = document.getElementById('content-' + tabName);
        
        selectedTab.classList.add('active', 'text-primary-600', 'border-primary-500');
        selectedTab.classList.remove('text-gray-500', 'border-transparent');
        selectedContent.classList.add('active');
    }

    // View Quiz Details Modal
    function viewQuizDetails(quiz) {
        document.getElementById('modalQuizTitle').textContent = quiz.title || 'Quiz Details';
        
        const status = quiz.status || 'published';
        const isPublished = (status === 'published' || status === 'active');
        const statusBadge = isPublished 
            ? '<span class="badge bg-green-100 text-green-700"><i class="fas fa-check-circle mr-1"></i>Published</span>'
            : '<span class="badge bg-gray-200 text-gray-600"><i class="fas fa-eye-slash mr-1"></i>Unpublished</span>';

        let content = `
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">${quiz.title || 'Untitled Quiz'}</h3>
                    ${statusBadge}
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Description</h4>
                    <p class="text-gray-600 text-sm">${quiz.description || 'No description available'}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-1">Professor</h4>
                        <p class="text-gray-600 text-sm">${(quiz.prof_firstname || '') + ' ' + (quiz.prof_lastname || 'Unknown')}</p>
                        <p class="text-gray-500 text-xs">${quiz.prof_email || ''}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-1">Created</h4>
                        <p class="text-gray-600 text-sm">${quiz.created_at ? new Date(quiz.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalQuizContent').innerHTML = content;
        document.getElementById('viewQuizModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // View Module Details
    function viewModuleDetails(module) {
        document.getElementById('modalQuizTitle').textContent = module.title || 'Module Details';
        
        const status = module.status || 'published';
        const isPublished = (status === 'published' || status === 'active');
        const statusBadge = isPublished 
            ? '<span class="badge bg-green-100 text-green-700"><i class="fas fa-check-circle mr-1"></i>Published</span>'
            : '<span class="badge bg-gray-200 text-gray-600"><i class="fas fa-eye-slash mr-1"></i>Unpublished</span>';

        let content = `
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">${module.title || 'Untitled Module'}</h3>
                    ${statusBadge}
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Description</h4>
                    <p class="text-gray-600 text-sm">${module.description || 'No description available'}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-1">Professor</h4>
                        <p class="text-gray-600 text-sm">${(module.prof_firstname || '') + ' ' + (module.prof_lastname || 'Unknown')}</p>
                        <p class="text-gray-500 text-xs">${module.prof_email || ''}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-1">Created</h4>
                        <p class="text-gray-600 text-sm">${module.created_at ? new Date(module.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalQuizContent').innerHTML = content;
        document.getElementById('viewQuizModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
        document.getElementById('viewQuizModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Quiz search and filter
    const quizSearchInput = document.getElementById('quizSearchInput');
    const quizStatusFilter = document.getElementById('quizStatusFilter');
    const quizProfFilter = document.getElementById('quizProfFilter');

    function filterQuizzes() {
        const searchTerm = quizSearchInput.value.toLowerCase();
        const statusFilter = quizStatusFilter.value;
        const profFilter = quizProfFilter.value;

        document.querySelectorAll('.quiz-row').forEach(row => {
            const quizData = JSON.parse(row.getAttribute('data-quiz'));
            const title = (quizData.title || '').toLowerCase();
            const description = (quizData.description || '').toLowerCase();
            const status = row.getAttribute('data-status');
            const profId = row.getAttribute('data-prof');

            const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
            const matchesStatus = !statusFilter || status === statusFilter;
            const matchesProf = !profFilter || profId === profFilter;

            row.style.display = (matchesSearch && matchesStatus && matchesProf) ? '' : 'none';
        });
    }

    if(quizSearchInput) quizSearchInput.addEventListener('input', filterQuizzes);
    if(quizStatusFilter) quizStatusFilter.addEventListener('change', filterQuizzes);
    if(quizProfFilter) quizProfFilter.addEventListener('change', filterQuizzes);

    // Module search and filter
    const moduleSearchInput = document.getElementById('moduleSearchInput');
    const moduleStatusFilter = document.getElementById('moduleStatusFilter');
    const moduleProfFilter = document.getElementById('moduleProfFilter');

    function filterModules() {
        const searchTerm = moduleSearchInput.value.toLowerCase();
        const statusFilter = moduleStatusFilter.value;
        const profFilter = moduleProfFilter.value;

        document.querySelectorAll('.module-row').forEach(row => {
            const moduleData = JSON.parse(row.getAttribute('data-module'));
            const title = (moduleData.title || '').toLowerCase();
            const description = (moduleData.description || '').toLowerCase();
            const status = row.getAttribute('data-status');
            const profId = row.getAttribute('data-prof');

            const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
            const matchesStatus = !statusFilter || status === statusFilter;
            const matchesProf = !profFilter || profId === profFilter;

            row.style.display = (matchesSearch && matchesStatus && matchesProf) ? '' : 'none';
        });
    }

    if(moduleSearchInput) moduleSearchInput.addEventListener('input', filterModules);
    if(moduleStatusFilter) moduleStatusFilter.addEventListener('change', filterModules);
    if(moduleProfFilter) moduleProfFilter.addEventListener('change', filterModules);

    // Bulk selection functions
    function toggleAllQuizzes(checkbox) {
        document.querySelectorAll('.quiz-checkbox').forEach(cb => {
            if(cb.closest('tr').style.display !== 'none') {
                cb.checked = checkbox.checked;
            }
        });
        updateQuizSelectedCount();
    }

    function toggleAllModules(checkbox) {
        document.querySelectorAll('.module-checkbox').forEach(cb => {
            if(cb.closest('tr').style.display !== 'none') {
                cb.checked = checkbox.checked;
            }
        });
        updateModuleSelectedCount();
    }

    function updateQuizSelectedCount() {
        const count = document.querySelectorAll('.quiz-checkbox:checked').length;
        document.getElementById('quizSelectedCount').textContent = count + ' selected';
    }

    function updateModuleSelectedCount() {
        const count = document.querySelectorAll('.module-checkbox:checked').length;
        document.getElementById('moduleSelectedCount').textContent = count + ' selected';
    }

    function confirmBulkQuizAction(action) {
        const count = document.querySelectorAll('.quiz-checkbox:checked').length;
        if(count === 0) {
            alert('Please select at least one quiz.');
            return false;
        }
        return confirm(`Are you sure you want to ${action} ${count} quiz(zes)?`);
    }

    function confirmBulkModuleAction(action) {
        const count = document.querySelectorAll('.module-checkbox:checked').length;
        if(count === 0) {
            alert('Please select at least one module.');
            return false;
        }
        return confirm(`Are you sure you want to ${action} ${count} module(s)?`);
    }

    // Close modal on escape key or outside click
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeViewModal();
            if (sidebarExpanded && window.innerWidth < 1024) {
                closeSidebar();
            }
        }
    });

    document.getElementById('viewQuizModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeViewModal();
        }
    });

    // Initialize
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
                    
                    if (sidebarExpanded) {
                        mainContent.style.marginLeft = '18rem';
                    } else {
                        mainContent.style.marginLeft = '5rem';
                    }
                } else {
                    mainContent.style.marginLeft = '0';
                    
                    if (!sidebarExpanded) {
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

    // Touch swipe for mobile
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