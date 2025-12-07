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

// Fetch subjects from existing table
try {
    $stmt = $conn->prepare("
        SELECT id, name, description, icon, color FROM subjects 
        WHERE status = 'active'
        ORDER BY display_order ASC, name ASC
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $subjects = [];
}

// Handle adding new subject via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    header('Content-Type: application/json');
    
    $subjectName = trim($_POST['subject_name'] ?? '');
    $subjectDescription = trim($_POST['subject_description'] ?? '');
    
    if (empty($subjectName)) {
        echo json_encode(['success' => false, 'error' => 'Subject name is required']);
        exit();
    }
    
    try {
        // Get max display_order
        $stmt = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM subjects");
        $nextOrder = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("INSERT INTO subjects (name, description, display_order, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
        $stmt->execute([$subjectName, $subjectDescription ?: null, $nextOrder]);
        $newId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'id' => $newId, 
            'name' => $subjectName
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'error' => 'Subject already exists']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit();
}

// Fetch modules (ordered by display_order if column exists, otherwise by created_at)
try {
    $stmt = $conn->prepare("SELECT id, title, description, content, status, subject, created_at, display_order FROM modules WHERE professor_id = :professor_id ORDER BY display_order ASC, created_at DESC");
    $stmt->bindParam(':professor_id', $professorId);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If display_order or subject column doesn't exist, fall back to created_at
    try {
        $stmt = $conn->prepare("SELECT id, title, description, content, status, subject, created_at FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
        $stmt->bindParam(':professor_id', $professorId);
        $stmt->execute();
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $stmt = $conn->prepare("SELECT id, title, description, content, status, created_at FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
        $stmt->bindParam(':professor_id', $professorId);
        $stmt->execute();
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle AJAX requests for reorder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reorder') {
        header('Content-Type: application/json');
        $order = json_decode($_POST['order'], true);
        try {
            foreach ($order as $index => $id) {
                $stmt = $conn->prepare("UPDATE modules SET display_order = :order WHERE id = :id AND professor_id = :professor_id");
                $stmt->execute([
                    'order' => $index,
                    'id' => $id,
                    'professor_id' => $professorId
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Modules - MedAce</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
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

        /* Scrollbar */
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

        /* Animations */
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }

        .animate-slide-in {
            animation: slideIn 0.4s ease-out;
        }

        /* Sidebar Transition */
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Desktop Sidebar States */
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

        /* Mobile Sidebar States */
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

        /* Sidebar Toggle Icon */
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
            border-color: #0ea5e9;
            background: #f0f9ff;
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
            border-color: #0ea5e9;
            background-color: #0ea5e9;
        }

        .sidebar-toggle-btn.active .toggle-icon::after {
            transform: rotate(135deg);
            right: 4px;
        }

        .sidebar-toggle-btn.active .toggle-icon::before {
            background-color: #0ea5e9;
        }

        .sidebar-toggle-btn.active {
            border-color: #0ea5e9;
            background: #f0f9ff;
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

        /* Table Row Hover */
        .table-row-hover {
            transition: all 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: #f0f9ff;
        }

        /* Sortable styles */
        .sortable-ghost {
            opacity: 0.4;
            background: #e0f2fe;
        }

        .sortable-drag {
            cursor: move !important;
        }

        .drag-handle {
            cursor: grab;
            touch-action: none;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        /* Modal */
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
            animation: fadeIn 0.3s;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 600px;
            width: 90%;
            animation: slideUp 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
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

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            max-width: 500px;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 9999;
            border-left: 4px solid #10b981;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.success { border-left-color: #10b981; }
        .toast.error { border-left-color: #ef4444; }
        .toast.info { border-left-color: #3b82f6; }

        .toast-icon {
            flex-shrink: 0;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toast.success .toast-icon { background: #d1fae5; color: #10b981; }
        .toast.error .toast-icon { background: #fee2e2; color: #ef4444; }
        .toast.info .toast-icon { background: #dbeafe; color: #3b82f6; }

        .toast-content { flex: 1; }
        .toast-title { font-weight: 600; color: #111827; margin-bottom: 0.25rem; }
        .toast-message { font-size: 0.875rem; color: #6b7280; }
        .toast-close { flex-shrink: 0; color: #9ca3af; cursor: pointer; transition: color 0.2s; }
        .toast-close:hover { color: #4b5563; }

        /* File upload indicator */
        .file-selected {
            background: #f0f9ff !important;
            border-color: #0ea5e9 !important;
        }

        .file-name-display {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            display: none;
        }

        .file-name-display.show {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Subject dropdown with add new option */
        .subject-dropdown-container {
            position: relative;
        }

        .add-subject-inline {
            display: none;
            margin-top: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 0.5rem;
        }

        .add-subject-inline.show {
            display: block;
        }

        @media (max-width: 1024px) {
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

        @media (max-width: 640px) {
            .toast {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
                transform: translateY(-100px);
            }

            .toast.show {
                transform: translateY(0);
            }
        }

        body {
            overflow-x: hidden;
        }

        #main-content {
            max-width: 100vw;
            overflow-x: hidden;
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
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                        <p class="text-xs text-gray-500">Professor</p>
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
                <a href="manage_modules.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-book text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Modules</span>
                </a>
                <a href="manage_quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="student_progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Student Progress</span>
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
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <button onclick="toggleSidebar()" class="sidebar-toggle-btn lg:hidden" id="mobileHamburgerBtn" aria-label="Toggle sidebar">
                    <div class="toggle-icon"></div>
                </button>
                <div class="flex items-center space-x-3 ml-auto">
                    <button id="reorderBtn" onclick="toggleReorderMode()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors font-semibold flex items-center space-x-2">
                        <i class="fas fa-arrows-alt"></i>
                        <span class="hidden sm:inline">Reorder</span>
                    </button>
                    <button onclick="openAddModal()" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors font-semibold flex items-center space-x-2 shadow-sm">
                        <i class="fas fa-plus"></i>
                        <span class="hidden sm:inline">Add Module</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3 text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Reorder Mode Banner -->
            <div id="reorderBanner" class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle mr-3 text-lg"></i>
                        <span class="font-medium">Drag and drop modules to reorder them</span>
                    </div>
                    <button onclick="saveOrder()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold">
                        <i class="fas fa-save mr-2"></i>Save Order
                    </button>
                </div>
            </div>

            <!-- Page Header -->
            <div class="mb-8 animate-fade-in-up">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Modules</h1>
                <p class="text-gray-600">Create, edit, reorder, and organize your course modules</p>
            </div>

            <!-- Search Bar -->
            <div class="mb-6 animate-slide-in">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Search modules by title, subject, or description..." 
                           class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <!-- Modules Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up">
                <div class="px-6 py-5 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-book text-primary-500 mr-2"></i>
                            Your Modules
                        </h2>
                        <span class="badge bg-primary-50 text-primary-700">
                            <?= count($modules) ?> Total
                        </span>
                    </div>
                </div>

                <?php if (count($modules) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider drag-column">
                                    <i class="fas fa-grip-vertical"></i>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Subject</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell">Description</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">Created</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="moduleTableBody" class="divide-y divide-gray-200">
                            <?php foreach ($modules as $index => $module): ?>
                            <tr class="table-row-hover" data-id="<?= $module['id'] ?>">
                                <td class="px-6 py-4 drag-handle">
                                    <i class="fas fa-grip-vertical text-gray-400"></i>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?= $index + 1 ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-file-alt text-primary-600"></i>
                                        </div>
                                        <div>
                                            <a href="view_module.php?id=<?= $module['id'] ?>" class="text-sm font-semibold text-gray-900 hover:text-primary-600 transition-colors">
    <?= htmlspecialchars($module['title']) ?>
</a>
                                            <p class="text-xs text-gray-500 sm:hidden truncate max-w-xs">
                                                <?php if (!empty($module['subject'])): ?>
                                                    <span class="font-medium"><?= htmlspecialchars($module['subject']) ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 hidden sm:table-cell">
                                    <?php if (!empty($module['subject'])): ?>
                                        <span class="badge bg-purple-50 text-purple-700">
                                            <i class="fas fa-book-open mr-1"></i>
                                            <?= htmlspecialchars($module['subject']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No subject</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 hidden md:table-cell">
                                    <p class="truncate max-w-xs"><?= htmlspecialchars($module['description'] ?? '') ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $statusValue = !empty($module['status']) ? $module['status'] : 'draft';
                                        $status = strtolower(trim($statusValue));
                                        
                                        $statusConfig = match($status) {
                                            'published', 'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle', 'label' => 'Published'],
                                            'draft' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'icon' => 'fa-pencil', 'label' => 'Draft'],
                                            'archived' => ['bg' => 'bg-gray-200', 'text' => 'text-gray-700', 'icon' => 'fa-archive', 'label' => 'Archived'],
                                            default => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-info-circle', 'label' => ucfirst($status)]
                                        };
                                    ?>
                                    <span class="badge <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>">
                                        <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                        <?= $statusConfig['label'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 hidden lg:table-cell">
                                    <?= date('M d, Y', strtotime($module['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                         <a href="view_module.php?id=<?= $module['id'] ?>" 
           class="inline-flex items-center px-3 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 font-medium transition-colors text-sm"
           title="View Module">
            <i class="fas fa-eye"></i>
        </a>
                                    <button onclick='openEditModal(<?= json_encode($module) ?>)' 
                                                class="inline-flex items-center px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-medium transition-colors text-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteModule(<?= $module['id'] ?>, '<?= htmlspecialchars(addslashes($module['title'])) ?>', '<?= htmlspecialchars($module['content'] ?? '') ?>')" 
                                                class="inline-flex items-center px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium transition-colors text-sm">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-16 px-4">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-book-open text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No modules yet</h3>
                    <p class="text-gray-600 mb-6">Get started by creating your first module</p>
                    <button onclick="openAddModal()" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors font-semibold inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Module</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add Module Modal -->
<div id="addModuleModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Add New Module</h2>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form action="../actions/add_module_action.php" method="POST" enctype="multipart/form-data" id="addModuleForm">
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-heading text-primary-500 mr-1"></i>
                        Module Title *
                    </label>
                    <input type="text" name="title" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                           placeholder="e.g., Introduction to Anatomy">
                </div>

                <div class="subject-dropdown-container">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-book-open text-primary-500 mr-1"></i>
                        Subject *
                    </label>
                    <div class="flex gap-2">
                        <select name="subject" id="addSubjectSelect" required 
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= htmlspecialchars($subject['name']) ?>" 
                                        data-icon="<?= htmlspecialchars($subject['icon'] ?? '') ?>"
                                        data-color="<?= htmlspecialchars($subject['color'] ?? '') ?>">
                                    <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__add_new__">+ Add New Subject</option>
                        </select>
                    </div>
                    
                    <!-- Add New Subject Inline Form -->
                    <div id="addNewSubjectForm" class="add-subject-inline">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-plus-circle text-green-500 mr-1"></i>
                            Add New Subject
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Subject Name *</label>
                                <input type="text" id="newSubjectName" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="e.g., Medical-Surgical Nursing">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Description (Optional)</label>
                                <input type="text" id="newSubjectDescription" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="e.g., Care of adult patients with medical conditions">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="saveNewSubject('add')" 
                                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">
                                    <i class="fas fa-check mr-1"></i> Save Subject
                                </button>
                                <button type="button" onclick="cancelNewSubject('add')" 
                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-align-left text-primary-500 mr-1"></i>
                        Description
                    </label>
                    <textarea name="description" rows="4" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"
                              placeholder="Brief description of the module content..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-primary-500 mr-1"></i>
                        Status
                    </label>
                    <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file-upload text-primary-500 mr-1"></i>
                        Upload File (PDF or PPT)
                    </label>
                    <div id="addFileUploadArea" class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-primary-400 transition-colors">
                        <input type="file" name="module_file" id="addModuleFile" accept=".pdf,.ppt,.pptx" 
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="text-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600">Click to upload or drag and drop</p>
                            <p class="text-xs text-gray-500 mt-1">PDF, PPT, PPTX (Max 50MB)</p>
                        </div>
                    </div>
                    <div id="addFileNameDisplay" class="file-name-display">
                        <div class="flex items-center">
                            <i class="fas fa-file-alt text-primary-600 mr-2"></i>
                            <span id="addFileName" class="text-sm font-medium text-gray-700"></span>
                        </div>
                        <button type="button" onclick="clearAddFile()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeAddModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Save Module
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Module Modal -->
<div id="editModuleModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Edit Module</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form action="../actions/edit_module_action.php" method="POST" enctype="multipart/form-data" id="editModuleForm">
            <input type="hidden" name="module_id" id="edit_module_id">
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-heading text-primary-500 mr-1"></i>
                        Module Title *
                    </label>
                    <input type="text" name="title" id="edit_title" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                </div>

                <div class="subject-dropdown-container">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-book-open text-primary-500 mr-1"></i>
                        Subject *
                    </label>
                    <div class="flex gap-2">
                        <select name="subject" id="editSubjectSelect" required 
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= htmlspecialchars($subject['name']) ?>"
                                        data-icon="<?= htmlspecialchars($subject['icon'] ?? '') ?>"
                                        data-color="<?= htmlspecialchars($subject['color'] ?? '') ?>">
                                    <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__add_new__">+ Add New Subject</option>
                        </select>
                    </div>
                    
                    <!-- Add New Subject Inline Form for Edit -->
                    <div id="editNewSubjectForm" class="add-subject-inline">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-plus-circle text-green-500 mr-1"></i>
                            Add New Subject
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Subject Name *</label>
                                <input type="text" id="editNewSubjectName" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="e.g., Medical-Surgical Nursing">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Description (Optional)</label>
                                <input type="text" id="editNewSubjectDescription" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="e.g., Care of adult patients with medical conditions">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="saveNewSubject('edit')" 
                                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">
                                    <i class="fas fa-check mr-1"></i> Save Subject
                                </button>
                                <button type="button" onclick="cancelNewSubject('edit')" 
                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-align-left text-primary-500 mr-1"></i>
                        Description
                    </label>
                    <textarea name="description" id="edit_description" rows="4" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-primary-500 mr-1"></i>
                        Status
                    </label>
                    <select name="status" id="edit_status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file-upload text-primary-500 mr-1"></i>
                        Replace File (Optional)
                    </label>
                    <div class="mb-2 text-sm text-gray-600" id="current_file_info">
                        Current file: <span id="current_file_name" class="font-medium"></span>
                    </div>
                    <div id="editFileUploadArea" class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-primary-400 transition-colors">
                        <input type="file" name="module_file" id="editModuleFile" accept=".pdf,.ppt,.pptx" 
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="text-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600">Click to upload a new file</p>
                            <p class="text-xs text-gray-500 mt-1">PDF, PPT, PPTX (Max 50MB)</p>
                        </div>
                    </div>
                    <div id="editFileNameDisplay" class="file-name-display">
                        <div class="flex items-center">
                            <i class="fas fa-file-alt text-primary-600 mr-2"></i>
                            <span id="editFileName" class="text-sm font-medium text-gray-700"></span>
                        </div>
                        <button type="button" onclick="clearEditFile()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Update Module
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Module?</h3>
            <p class="text-gray-600 mb-2">Are you sure you want to delete:</p>
            <p class="text-gray-900 font-semibold text-lg mb-4" id="deleteModuleName"></p>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-sm text-red-800">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    This action cannot be undone. The file and all student progress will be permanently deleted.
                </p>
            </div>
        </div>
        
        <div class="flex gap-3">
            <button type="button" onclick="closeDeleteModal()" 
                    class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 font-semibold transition-colors">
                Cancel
            </button>
            <button type="button" id="confirmDeleteBtn" 
                    class="flex-1 bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 font-semibold transition-colors">
                <i class="fas fa-trash-alt mr-2"></i>
                Yes, Delete
            </button>
        </div>
    </div>
</div>

<script>
    let sidebarExpanded = false;
    let reorderMode = false;
    let sortable = null;

    // Toast Notification System
    function showToast(type, title, message, duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const iconMap = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${iconMap[type]} text-xl"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <div class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </div>
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // Subject Dropdown Handlers
    document.getElementById('addSubjectSelect').addEventListener('change', function() {
        if (this.value === '__add_new__') {
            document.getElementById('addNewSubjectForm').classList.add('show');
            document.getElementById('newSubjectName').focus();
        } else {
            document.getElementById('addNewSubjectForm').classList.remove('show');
        }
    });

    document.getElementById('editSubjectSelect').addEventListener('change', function() {
        if (this.value === '__add_new__') {
            document.getElementById('editNewSubjectForm').classList.add('show');
            document.getElementById('editNewSubjectName').focus();
        } else {
            document.getElementById('editNewSubjectForm').classList.remove('show');
        }
    });

    function cancelNewSubject(formType) {
        if (formType === 'add') {
            document.getElementById('addNewSubjectForm').classList.remove('show');
            document.getElementById('addSubjectSelect').value = '';
            document.getElementById('newSubjectName').value = '';
            document.getElementById('newSubjectDescription').value = '';
        } else {
            document.getElementById('editNewSubjectForm').classList.remove('show');
            document.getElementById('editSubjectSelect').value = '';
            document.getElementById('editNewSubjectName').value = '';
            document.getElementById('editNewSubjectDescription').value = '';
        }
    }

    async function saveNewSubject(formType) {
        const nameInput = formType === 'add' ? document.getElementById('newSubjectName') : document.getElementById('editNewSubjectName');
        const descInput = formType === 'add' ? document.getElementById('newSubjectDescription') : document.getElementById('editNewSubjectDescription');
        const selectEl = formType === 'add' ? document.getElementById('addSubjectSelect') : document.getElementById('editSubjectSelect');
        const formEl = formType === 'add' ? document.getElementById('addNewSubjectForm') : document.getElementById('editNewSubjectForm');
        
        const subjectName = nameInput.value.trim();
        const subjectDescription = descInput.value.trim();
        
        if (!subjectName) {
            showToast('error', 'Error', 'Subject name is required');
            nameInput.focus();
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_subject');
            formData.append('subject_name', subjectName);
            formData.append('subject_description', subjectDescription);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Add new option to both selects
                const newOption = new Option(subjectName, subjectName);
                
                // Add to both dropdowns (before the "Add New" option)
                const addSelect = document.getElementById('addSubjectSelect');
                const editSelect = document.getElementById('editSubjectSelect');
                
                const addNewOptionAdd = addSelect.querySelector('option[value="__add_new__"]');
                const addNewOptionEdit = editSelect.querySelector('option[value="__add_new__"]');
                
                addSelect.insertBefore(newOption.cloneNode(true), addNewOptionAdd);
                editSelect.insertBefore(newOption.cloneNode(true), addNewOptionEdit);
                
                // Select the new subject
                selectEl.value = subjectName;
                
                // Hide the form
                formEl.classList.remove('show');
                
                // Clear inputs
                nameInput.value = '';
                descInput.value = '';
                
                showToast('success', 'Subject Added', `"${subjectName}" has been added successfully`);
            } else {
                showToast('error', 'Error', data.error || 'Failed to add subject');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to add subject. Please try again.');
        }
    }

    // File Upload Handlers
    document.getElementById('addModuleFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            document.getElementById('addFileName').textContent = file.name;
            document.getElementById('addFileNameDisplay').classList.add('show');
            document.getElementById('addFileUploadArea').classList.add('file-selected');
            showToast('info', 'File Selected', `${file.name} ready to upload`, 3000);
        }
    });

    document.getElementById('editModuleFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            document.getElementById('editFileName').textContent = file.name;
            document.getElementById('editFileNameDisplay').classList.add('show');
            document.getElementById('editFileUploadArea').classList.add('file-selected');
            showToast('info', 'File Selected', `${file.name} ready to upload`, 3000);
        }
    });

    function clearAddFile() {
        document.getElementById('addModuleFile').value = '';
        document.getElementById('addFileNameDisplay').classList.remove('show');
        document.getElementById('addFileUploadArea').classList.remove('file-selected');
    }

    function clearEditFile() {
        document.getElementById('editModuleFile').value = '';
        document.getElementById('editFileNameDisplay').classList.remove('show');
        document.getElementById('editFileUploadArea').classList.remove('file-selected');
    }

    // Form submission handlers
    document.getElementById('addModuleForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('addModuleFile');
        if (fileInput.files.length > 0) {
            showToast('info', 'Uploading Module', 'Please wait while your file is being uploaded...', 10000);
        }
    });

    document.getElementById('editModuleForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('editModuleFile');
        if (fileInput.files.length > 0) {
            showToast('info', 'Updating Module', 'Please wait while your file is being uploaded...', 10000);
        }
    });

    // Sidebar Toggle
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
            
            document.body.style.overflow = sidebarExpanded ? 'hidden' : 'auto';
        } else {
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.style.marginLeft = sidebarExpanded ? '18rem' : '5rem';
        }
    }

    function closeSidebar() {
        if (window.innerWidth < 1024 && sidebarExpanded) {
            toggleSidebar();
        }
    }

    // Reorder Mode
    function toggleReorderMode() {
        reorderMode = !reorderMode;
        const banner = document.getElementById('reorderBanner');
        const btn = document.getElementById('reorderBtn');
        const tbody = document.getElementById('moduleTableBody');
        
        if (reorderMode) {
            banner.classList.remove('hidden');
            btn.classList.add('bg-primary-600', 'text-white');
            btn.classList.remove('bg-gray-100', 'text-gray-700');
            
            sortable = new Sortable(tbody, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
            });
        } else {
            banner.classList.add('hidden');
            btn.classList.remove('bg-primary-600', 'text-white');
            btn.classList.add('bg-gray-100', 'text-gray-700');
            
            if (sortable) {
                sortable.destroy();
                sortable = null;
            }
        }
    }

    async function saveOrder() {
        const tbody = document.getElementById('moduleTableBody');
        const rows = tbody.querySelectorAll('tr');
        const order = Array.from(rows).map(row => row.dataset.id);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=reorder&order=${JSON.stringify(order)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                rows.forEach((row, index) => {
                    row.querySelector('td:nth-child(2)').textContent = index + 1;
                });
                
                showToast('success', 'Order Saved', 'Module order has been updated successfully');
                
                setTimeout(() => {
                    toggleReorderMode();
                    location.reload();
                }, 1500);
            }
        } catch (error) {
            console.error('Error saving order:', error);
            showToast('error', 'Save Failed', 'Failed to save the new order. Please try again.');
        }
    }

    // Modal Functions
    function openAddModal() {
        document.getElementById('addModuleModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        document.getElementById('addModuleModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        clearAddFile();
        cancelNewSubject('add');
    }

    function openEditModal(module) {
        document.getElementById('edit_module_id').value = module.id;
        document.getElementById('edit_title').value = module.title;
        document.getElementById('edit_description').value = module.description || '';
        
        // Set subject dropdown
        const subjectSelect = document.getElementById('editSubjectSelect');
        const subjectValue = module.subject || '';
        
        // Check if the subject exists in the dropdown
        let subjectFound = false;
        for (let option of subjectSelect.options) {
            if (option.value === subjectValue) {
                subjectFound = true;
                break;
            }
        }
        
        if (subjectFound) {
            subjectSelect.value = subjectValue;
        } else if (subjectValue) {
            // Add the subject as a new option if it doesn't exist
            const newOption = new Option(subjectValue, subjectValue);
            const addNewOption = subjectSelect.querySelector('option[value="__add_new__"]');
            subjectSelect.insertBefore(newOption, addNewOption);
            subjectSelect.value = subjectValue;
        } else {
            subjectSelect.value = '';
        }
        
        // Set status
        let statusValue = module.status ? module.status.toLowerCase().trim() : 'draft';
        if (statusValue === 'active') statusValue = 'published';
        document.getElementById('edit_status').value = statusValue;
        
        const fileName = module.content ? module.content.split('/').pop() : 'No file uploaded';
        document.getElementById('current_file_name').textContent = fileName;
        
        clearEditFile();
        cancelNewSubject('edit');
        document.getElementById('editModuleModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModuleModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        clearEditFile();
        cancelNewSubject('edit');
    }

    // Delete Module
    let deleteModuleId = null;
    let deleteModuleFile = null;

    function deleteModule(id, title, filePath) {
        deleteModuleId = id;
        deleteModuleFile = filePath;
        document.getElementById('deleteModuleName').textContent = title;
        document.getElementById('deleteModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        deleteModuleId = null;
        deleteModuleFile = null;
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (deleteModuleId) {
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
        const rows = document.querySelectorAll('#moduleTableBody tr');
        
        rows.forEach(row => {
            const title = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
            const subject = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
            const description = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
            
            if (title.includes(searchTerm) || subject.includes(searchTerm) || description.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
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

        // Handle window resize
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
                    
                    mainContent.style.marginLeft = sidebarExpanded ? '18rem' : '5rem';
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

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeDeleteModal();
                if (sidebarExpanded && window.innerWidth < 1024) {
                    closeSidebar();
                }
            }
        });

        // Close modals when clicking outside
        ['addModuleModal', 'editModuleModal', 'deleteModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                    document.body.style.overflow = 'auto';
                }
            });
        });
    });

    // Touch swipe support
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