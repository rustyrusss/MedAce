<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php'; 

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// Fetch professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";

// Avatar
$profilePic = getProfilePicture($prof, "../");

// Dashboard counts
$totalModules = $conn->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$totalQuizzes = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalStudents = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// Recent modules & quizzes
$modules = $conn->query("SELECT id, title, created_at FROM modules ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$quizzes = $conn->query("SELECT id, title, publish_time, deadline_time FROM quizzes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $uploadDir = '../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    
    if (in_array($file['type'], $allowedTypes) && $file['error'] === 0) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $professorId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old profile picture if exists
            if (!empty($prof['profile_pic']) && file_exists('../' . $prof['profile_pic'])) {
                unlink('../' . $prof['profile_pic']);
            }
            
            // Update database
            $relativePath = 'uploads/profiles/' . $filename;
            $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $updateStmt->execute([$relativePath, $professorId]);
            
            // Refresh page to show new picture
            header("Location: dashboard.php?upload=success");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard - MedAce</title>
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

        /* Custom Shadow */
        .shadow-custom {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        }

        .shadow-custom-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        /* Gradient Background */
        .gradient-bg {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }

        /* Card Hover Effect */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        /* Stat Card Gradient Icons */
        .stat-icon-1 {
            background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
        }

        .stat-icon-2 {
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
        }

        .stat-icon-3 {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
        }

        /* Table Row Hover */
        .table-row-hover {
            transition: background-color 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: #f0f9ff;
        }

        /* Sidebar Transition */
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Sidebar Collapsed State */
        .sidebar-collapsed {
            width: 5rem;
        }

        .sidebar-collapsed .nav-text,
        .sidebar-collapsed .profile-info,
        .sidebar-collapsed .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-expanded {
            width: 18rem;
        }

        .sidebar-expanded .nav-text,
        .sidebar-expanded .profile-info,
        .sidebar-expanded .logo-text {
            opacity: 1;
            width: auto;
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        /* Profile Picture Upload */
        .profile-upload-btn {
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 32px;
            height: 32px;
            background: #0ea5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .profile-upload-btn:hover {
            background: #0284c7;
            transform: scale(1.1);
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
            max-width: 500px;
            width: 90%;
            animation: scaleIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .sidebar-collapsed {
                width: 0;
                transform: translateX(-100%);
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                min-width: 100%;
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
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500 cursor-pointer" onclick="openProfileModal()">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                        <div class="profile-upload-btn" onclick="openProfileModal()">
                            <i class="fas fa-camera text-white text-xs"></i>
                        </div>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                        <p class="text-xs text-gray-500">Professor</p>
                    </div>
                </div>
            </div>

            <!-- Toggle Button -->
            <div class="px-4 py-3 border-b border-gray-200">
                <button onclick="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-home text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="manage_modules.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-book text-gray-400 w-5 text-center flex-shrink-0"></i>
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
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 transition-all duration-300" style="margin-left: 5rem;">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars text-gray-600 text-xl"></i>
                </button>
                <div class="flex items-center space-x-4">
                    <div class="hidden sm:block">
                        <p class="text-sm text-gray-500">Today</p>
                        <p class="text-sm font-semibold text-gray-900" id="currentDate"></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <?php if(isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Profile picture updated successfully!</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="gradient-bg rounded-2xl p-6 sm:p-8 mb-8 text-white shadow-custom-lg animate-fade-in-up">
                <h1 class="text-2xl sm:text-3xl font-bold mb-2">Welcome back, <?= htmlspecialchars($prof['firstname']) ?>! 👋</h1>
                <p class="text-blue-100 text-sm sm:text-base">Here's an overview of your teaching resources and recent activity.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Total Modules -->
                <div class="bg-white rounded-2xl p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="stat-icon-1 w-14 h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-book text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-sm font-medium mb-1">Total Modules</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-4xl font-bold text-gray-900" id="modulesCount">0</p>
                    </div>
                </div>

                <!-- Total Quizzes -->
                <div class="bg-white rounded-2xl p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="stat-icon-2 w-14 h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-clipboard-list text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-sm font-medium mb-1">Total Quizzes</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-4xl font-bold text-gray-900" id="quizzesCount">0</p>
                    </div>
                </div>

                <!-- Total Students -->
                <div class="bg-white rounded-2xl p-6 shadow-custom card-hover animate-scale-in border border-gray-100 sm:col-span-2 lg:col-span-1" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="stat-icon-3 w-14 h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-user-graduate text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-sm font-medium mb-1">Total Students</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-4xl font-bold text-gray-900" id="studentsCount">0</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Modules -->
                <div class="bg-white rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-book text-primary-500 mr-2"></i>
                                Recent Modules
                            </h2>
                            <a href="manage_modules.php" class="text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class="fas fa-arrow-right ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if(empty($modules)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-book-open text-5xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-sm">No modules created yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto -mx-6">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach($modules as $m): ?>
                                            <tr class="table-row-hover">
                                                <td class="px-6 py-4">
                                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($m['title']) ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="badge bg-gray-100 text-gray-700">
                                                        <?= date("M j, Y", strtotime($m['created_at'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Quizzes -->
                <div class="bg-white rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-clipboard-list text-primary-500 mr-2"></i>
                                Recent Quizzes
                            </h2>
                            <a href="manage_quizzes.php" class="text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class="fas fa-arrow-right ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if(empty($quizzes)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-clipboard-question text-5xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-sm">No quizzes available yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto -mx-6">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Publish</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Deadline</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach($quizzes as $q): ?>
                                            <tr class="table-row-hover">
                                                <td class="px-6 py-4">
                                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($q['title']) ?></p>
                                                    <!-- Mobile-only dates -->
                                                    <div class="sm:hidden mt-2 space-y-1">
                                                        <p class="text-xs text-gray-500">
                                                            <i class="fas fa-calendar-plus mr-1"></i>
                                                            <?= $q['publish_time'] ? date("M j, Y", strtotime($q['publish_time'])) : '-' ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500">
                                                            <i class="fas fa-calendar-xmark mr-1"></i>
                                                            <?= $q['deadline_time'] ? date("M j, Y", strtotime($q['deadline_time'])) : '-' ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 hidden sm:table-cell">
                                                    <span class="badge bg-blue-50 text-blue-700">
                                                        <?= $q['publish_time'] ? date("M j", strtotime($q['publish_time'])) : '-' ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 hidden sm:table-cell">
                                                    <span class="badge bg-orange-50 text-orange-700">
                                                        <?= $q['deadline_time'] ? date("M j", strtotime($q['deadline_time'])) : '-' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Profile Picture Modal -->
<div id="profileModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Update Profile Picture</h2>
            <button onclick="closeProfileModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <div class="mb-6">
                <div class="flex justify-center mb-4">
                    <div class="relative">
                        <img id="previewImage" src="<?= htmlspecialchars($profilePic) ?>" alt="Preview" class="w-32 h-32 rounded-full object-cover ring-4 ring-primary-500">
                    </div>
                </div>
                
                <label class="block text-sm font-medium text-gray-700 mb-2">Choose New Picture</label>
                <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" 
                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-primary-500"
                       onchange="previewProfilePicture(event)">
                <p class="mt-2 text-xs text-gray-500">Supported formats: JPG, PNG, GIF (Max 5MB)</p>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    <i class="fas fa-upload mr-2"></i>
                    Upload Picture
                </button>
                <button type="button" onclick="closeProfileModal()" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let sidebarExpanded = false;

    // Sidebar Toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebarExpanded = !sidebarExpanded;
        
        if (window.innerWidth < 1024) {
            // Mobile behavior
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            overlay.classList.toggle('hidden');
            if (sidebarExpanded) {
                mainContent.style.marginLeft = '0';
            }
        } else {
            // Desktop behavior
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

    // Profile Modal Functions
    function openProfileModal() {
        document.getElementById('profileModal').classList.add('show');
    }

    function closeProfileModal() {
        document.getElementById('profileModal').classList.remove('show');
    }

    function previewProfilePicture(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    }

    // Display current date
    function displayCurrentDate() {
        const dateElement = document.getElementById('currentDate');
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        const today = new Date();
        dateElement.textContent = today.toLocaleDateString('en-US', options);
    }

    // Animated counter
    function animateCounter(elementId, targetValue, duration = 1000) {
        const element = document.getElementById(elementId);
        const start = 0;
        const increment = targetValue / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if (current >= targetValue) {
                element.textContent = targetValue;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, 16);
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        displayCurrentDate();
        
        // Animate counters
        setTimeout(() => {
            animateCounter('modulesCount', <?= (int)$totalModules ?>, 1200);
            animateCounter('quizzesCount', <?= (int)$totalQuizzes ?>, 1200);
            animateCounter('studentsCount', <?= (int)$totalStudents ?>, 1200);
        }, 300);

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('main-content');
                const overlay = document.getElementById('sidebar-overlay');
                
                if (window.innerWidth >= 1024) {
                    overlay.classList.add('hidden');
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
                    }
                }
            }, 250);
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('profileModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProfileModal();
            }
        });
    });
</script>

</body>
</html>