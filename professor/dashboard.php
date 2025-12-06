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

        /* Desktop Sidebar States */
        @media (min-width: 1024px) {
            .sidebar-collapsed {
                width: 5rem;
                transform: translateX(0);
            }

            .sidebar-collapsed .nav-text,
            .sidebar-collapsed .profile-info,
            .sidebar-collapsed .logo-text,
            .sidebar-collapsed .sidebar-setting-btn {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }

            .sidebar-expanded .nav-text,
            .sidebar-expanded .profile-info,
            .sidebar-expanded .logo-text,
            .sidebar-expanded .sidebar-setting-btn {
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
            .sidebar-collapsed .profile-info,
            .sidebar-collapsed .logo-text,
            .sidebar-collapsed .sidebar-setting-btn {
                opacity: 1;
                width: auto;
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }

            .sidebar-expanded .nav-text,
            .sidebar-expanded .profile-info,
            .sidebar-expanded .logo-text,
            .sidebar-expanded .sidebar-setting-btn {
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

        /* Panel icon (left bar) */
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

        /* Chevron icon */
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

        /* Active state - chevron points left when sidebar is expanded */
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

        /* Mobile-specific toggle button styling */
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
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        /* Overlay fade effect */
        #sidebar-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        #sidebar-overlay.show {
            opacity: 1;
            pointer-events: auto;
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

        .modal-content-small {
            background-color: white;
            padding: 1.5rem;
            border-radius: 1rem;
            max-width: 500px;
            width: 90%;
            animation: scaleIn 0.3s;
            margin: 1rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

        .tab-button {
            position: relative;
            padding: 0.75rem 1rem;
            font-weight: 500;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            font-size: 0.875rem;
        }

        .tab-button.active {
            color: #0ea5e9;
            border-bottom-color: #0ea5e9;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeInUp 0.4s ease-out;
        }

        /* Responsive adjustments */
        @media (max-width: 1023px) {
            /* Ensure main content takes full width on mobile */
            #main-content {
                margin-left: 0 !important;
            }

            /* Sidebar overlay styling */
            #sidebar-overlay {
                display: none;
            }

            #sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                min-width: 100%;
            }

            .modal-content-small {
                padding: 1rem;
            }

            .tab-button {
                padding: 0.75rem 0.5rem;
                font-size: 0.8125rem;
            }

            .tab-button i {
                display: none;
            }

            /* Improve mobile spacing */
            .gradient-bg {
                padding: 1.25rem 1rem !important;
            }

            /* Better mobile stats grid */
            .stats-grid {
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .gradient-bg {
                padding: 1.5rem 1rem !important;
            }

            .modal-content,
            .modal-content-small {
                width: 95%;
                max-width: none;
                margin: 0.5rem;
            }

            /* Reduce padding on small screens */
            .px-4 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            /* Make buttons stack on very small screens */
            .button-group-mobile {
                flex-direction: column;
            }
        }

        /* Ensure tables are scrollable on mobile */
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

        /* Prevent horizontal overflow */
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
                <div class="flex items-center space-x-3 min-w-0 flex-1">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500 cursor-pointer" onclick="openUploadModal()">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                        <div class="profile-upload-btn" onclick="openUploadModal()">
                            <i class="fas fa-camera text-white text-xs"></i>
                        </div>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0 flex-1">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                        <p class="text-xs text-gray-500">Professor</p>
                    </div>
                    <!-- Settings Icon Button -->
                    <button onclick="openProfileSettingsModal()" class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-600 hover:text-primary-600 transition-colors sidebar-setting-btn sidebar-transition" title="Profile Settings">
                        <i class="fas fa-cog text-lg"></i>
                    </button>
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
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300" onclick="closeSidebar()"></div>

    <!-- Profile Picture Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content-small">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Update Profile Picture</h2>
                <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <div class="mb-6">
                    <div class="flex justify-center mb-4">
                        <div class="relative">
                            <img id="previewImage" src="<?= htmlspecialchars($profilePic) ?>" alt="Preview" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full object-cover ring-4 ring-primary-500">
                        </div>
                    </div>
                    
                    <label class="block text-sm font-medium text-gray-700 mb-2">Choose New Picture</label>
                    <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" 
                           class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-primary-500"
                           onchange="previewProfilePicture(event)">
                    <p class="mt-2 text-xs text-gray-500">Supported formats: JPG, PNG, GIF (Max 5MB)</p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                        <i class="fas fa-upload mr-2"></i>
                        Upload Picture
                    </button>
                    <button type="button" onclick="closeUploadModal()" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Settings Modal -->
    <div id="profileSettingsModal" class="modal">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="gradient-bg px-4 sm:px-6 py-4 sm:py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-user-circle mr-2 sm:mr-3"></i>
                        <span class="hidden sm:inline">Profile Settings</span>
                        <span class="sm:hidden">Profile</span>
                    </h2>
                    <button onclick="closeProfileSettingsModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 px-4 sm:px-6 overflow-x-auto">
                <button class="tab-button active whitespace-nowrap" onclick="switchTab(event, 'account')">
                    <i class="fas fa-user mr-2"></i><span>Account Details</span>
                </button>
                <button class="tab-button whitespace-nowrap" onclick="switchTab(event, 'password')">
                    <i class="fas fa-lock mr-2"></i><span>Change Password</span>
                </button>
            </div>

            <!-- Tab Contents -->
            <div class="p-4 sm:p-6">
                <!-- Account Details Tab -->
                <div id="account-tab" class="tab-content active">
                    <div class="space-y-4 sm:space-y-6">
                        <!-- Profile Picture Section -->
                        <div class="text-center pb-4 sm:pb-6 border-b border-gray-200">
                            <div class="relative inline-block">
                                <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full object-cover ring-4 ring-primary-500 mx-auto mb-3 sm:mb-4">
                                <button type="button" onclick="openUploadModal()" class="absolute bottom-3 sm:bottom-4 right-0 bg-primary-600 hover:bg-primary-700 text-white rounded-full p-2 sm:p-3 shadow-lg transition-colors">
                                    <i class="fas fa-camera text-sm"></i>
                                </button>
                            </div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mt-2"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                            <p class="text-sm text-gray-600">Professor Account</p>
                        </div>

                        <!-- Account Information -->
                        <div class="space-y-3 sm:space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-primary-500 mr-2"></i>First Name
                                </label>
                                <input type="text" value="<?= htmlspecialchars($prof['firstname']) ?>" readonly class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm sm:text-base">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-primary-500 mr-2"></i>Last Name
                                </label>
                                <input type="text" value="<?= htmlspecialchars($prof['lastname']) ?>" readonly class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm sm:text-base">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-primary-500 mr-2"></i>Email Address
                                </label>
                                <input type="email" value="<?= htmlspecialchars($prof['email']) ?>" readonly class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm sm:text-base">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-venus-mars text-primary-500 mr-2"></i>Gender
                                </label>
                                <input type="text" value="<?= htmlspecialchars(ucfirst($prof['gender'] ?? 'Not specified')) ?>" readonly class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm sm:text-base">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-id-badge text-primary-500 mr-2"></i>User ID
                                </label>
                                <input type="text" value="<?= htmlspecialchars($professorId) ?>" readonly class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm sm:text-base">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user-tie text-primary-500 mr-2"></i>Role
                                </label>
                                <input type="text" value="Professor" readonly class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm sm:text-base">
                            </div>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-primary-500 p-3 sm:p-4 rounded-r-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-primary-500 mt-0.5 mr-2 sm:mr-3 flex-shrink-0"></i>
                                <div>
                                    <p class="text-sm font-semibold text-primary-900 mb-1">Account Information</p>
                                    <p class="text-xs sm:text-sm text-primary-700">To update your account details, please contact your administrator.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password Tab -->
                <div id="password-tab" class="tab-content">
                    <form id="changePasswordForm" class="space-y-4 sm:space-y-6">
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 sm:p-4 rounded-r-lg mb-4 sm:mb-6">
                            <div class="flex">
                                <i class="fas fa-shield-alt text-yellow-500 mt-0.5 mr-2 sm:mr-3 flex-shrink-0"></i>
                                <div>
                                    <p class="text-sm font-semibold text-yellow-900 mb-1">Password Security</p>
                                    <p class="text-xs sm:text-sm text-yellow-700">Choose a strong password with at least 8 characters, including uppercase, lowercase, numbers, and symbols.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-lock text-primary-500 mr-2"></i>Current Password
                            </label>
                            <div class="relative">
                                <input type="password" id="currentPassword" name="currentPassword" required 
                                       class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                                       placeholder="Enter your current password">
                                <button type="button" onclick="togglePasswordVisibility('currentPassword')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-key text-primary-500 mr-2"></i>New Password
                            </label>
                            <div class="relative">
                                <input type="password" id="newPassword" name="newPassword" required 
                                       class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                                       placeholder="Enter your new password">
                                <button type="button" onclick="togglePasswordVisibility('newPassword')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordStrength" class="mt-2 hidden">
                                <div class="flex items-center space-x-2">
                                    <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div id="strengthBar" class="h-full transition-all duration-300"></div>
                                    </div>
                                    <span id="strengthText" class="text-xs sm:text-sm font-medium"></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-check-circle text-primary-500 mr-2"></i>Confirm New Password
                            </label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" name="confirmPassword" required 
                                       class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                                       placeholder="Confirm your new password">
                                <button type="button" onclick="togglePasswordVisibility('confirmPassword')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="passwordMatch" class="mt-2 text-xs sm:text-sm hidden"></p>
                        </div>

                        <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-3 sm:p-4 rounded-r-lg">
                            <div class="flex">
                                <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-2 sm:mr-3 flex-shrink-0"></i>
                                <p class="text-xs sm:text-sm text-red-700" id="passwordErrorText"></p>
                            </div>
                        </div>

                        <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-3 sm:p-4 rounded-r-lg">
                            <div class="flex">
                                <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 sm:mr-3 flex-shrink-0"></i>
                                <p class="text-xs sm:text-sm text-green-700">Password changed successfully!</p>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 pt-4">
                            <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors shadow-sm text-sm sm:text-base">
                                <i class="fas fa-save mr-2"></i>Update Password
                            </button>
                            <button type="button" onclick="resetPasswordForm()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors text-sm sm:text-base">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
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
                        <p class="text-xs sm:text-sm font-semibold text-gray-900" id="currentDate"></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-3 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-full">
            <?php if(isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
            <div class="mb-4 sm:mb-6 bg-green-50 border border-green-200 text-green-800 px-3 sm:px-4 py-2 sm:py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2 text-sm"></i>
                    <span class="text-xs sm:text-sm">Profile picture updated successfully!</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="gradient-bg rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 mb-4 sm:mb-6 lg:mb-8 text-white shadow-custom-lg animate-fade-in-up">
                <h1 class="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-bold mb-1 sm:mb-2">Welcome back, <?= htmlspecialchars($prof['firstname']) ?>! 👋</h1>
                <p class="text-blue-100 text-xs sm:text-sm lg:text-base">Here's an overview of your teaching resources and recent activity.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8 stats-grid">
                <!-- Total Modules -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-1 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-book text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Total Modules</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900" id="modulesCount">0</p>
                    </div>
                </div>

                <!-- Total Quizzes -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-2 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-clipboard-list text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Total Quizzes</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900" id="quizzesCount">0</p>
                    </div>
                </div>

                <!-- Total Students -->
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100 sm:col-span-2 lg:col-span-1" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-3 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-user-graduate text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Total Students</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900" id="studentsCount">0</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                <!-- Recent Modules -->
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                    <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-book text-primary-500 mr-2"></i>
                                <span class="hidden sm:inline">Recent Modules</span>
                                <span class="sm:hidden">Modules</span>
                            </h2>
                            <a href="manage_modules.php" class="text-xs sm:text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class="fas fa-arrow-right ml-1 sm:ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <?php if(empty($modules)): ?>
                            <div class="text-center py-8 sm:py-12">
                                <i class="fas fa-book-open text-4xl sm:text-5xl text-gray-300 mb-3 sm:mb-4"></i>
                                <p class="text-gray-500 text-xs sm:text-sm">No modules created yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container -mx-4 sm:-mx-6">
                                <table class="w-full min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach($modules as $m): ?>
                                            <tr class="table-row-hover">
                                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                                    <p class="text-xs sm:text-sm font-medium text-gray-900 line-clamp-2"><?= htmlspecialchars($m['title']) ?></p>
                                                </td>
                                                <td class="px-4 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                    <span class="badge bg-gray-100 text-gray-700 text-xs">
                                                        <span class="hidden sm:inline"><?= date("M j, Y", strtotime($m['created_at'])) ?></span>
                                                        <span class="sm:hidden"><?= date("M j", strtotime($m['created_at'])) ?></span>
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
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden" style="animation-delay: 0.1s;">
                    <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-clipboard-list text-primary-500 mr-2"></i>
                                <span class="hidden sm:inline">Recent Quizzes</span>
                                <span class="sm:hidden">Quizzes</span>
                            </h2>
                            <a href="manage_quizzes.php" class="text-xs sm:text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class="fas fa-arrow-right ml-1 sm:ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <?php if(empty($quizzes)): ?>
                            <div class="text-center py-8 sm:py-12">
                                <i class="fas fa-clipboard-question text-4xl sm:text-5xl text-gray-300 mb-3 sm:mb-4"></i>
                                <p class="text-gray-500 text-xs sm:text-sm">No quizzes available yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container -mx-4 sm:-mx-6">
                                <table class="w-full min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell whitespace-nowrap">Publish</th>
                                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell whitespace-nowrap">Deadline</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach($quizzes as $q): ?>
                                            <tr class="table-row-hover">
                                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                                    <p class="text-xs sm:text-sm font-medium text-gray-900 line-clamp-2 mb-2"><?= htmlspecialchars($q['title']) ?></p>
                                                    <!-- Mobile-only dates -->
                                                    <div class="md:hidden space-y-1">
                                                        <p class="text-xs text-gray-500 flex items-center">
                                                            <i class="fas fa-calendar-plus mr-1 w-4 flex-shrink-0"></i>
                                                            <span><?= $q['publish_time'] ? date("M j, Y", strtotime($q['publish_time'])) : '-' ?></span>
                                                        </p>
                                                        <p class="text-xs text-gray-500 flex items-center">
                                                            <i class="fas fa-calendar-xmark mr-1 w-4 flex-shrink-0"></i>
                                                            <span><?= $q['deadline_time'] ? date("M j, Y", strtotime($q['deadline_time'])) : '-' ?></span>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-4 sm:px-6 py-3 sm:py-4 hidden md:table-cell whitespace-nowrap">
                                                    <span class="badge bg-blue-50 text-blue-700 text-xs">
                                                        <?= $q['publish_time'] ? date("M j", strtotime($q['publish_time'])) : '-' ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 sm:px-6 py-3 sm:py-4 hidden md:table-cell whitespace-nowrap">
                                                    <span class="badge bg-orange-50 text-orange-700 text-xs">
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

<script>
    let sidebarExpanded = false;

    // Sidebar Toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileHamburgerBtn = document.getElementById('mobileHamburgerBtn');
        
        sidebarExpanded = !sidebarExpanded;
        
        // Toggle hamburger animation
        hamburgerBtn.classList.toggle('active');
        mobileHamburgerBtn.classList.toggle('active');
        
        if (window.innerWidth < 1024) {
            // Mobile behavior
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

    // Upload Modal Functions
    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('show');
        document.body.style.overflow = 'auto';
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

    // Profile Settings Modal Functions
    function openProfileSettingsModal() {
        document.getElementById('profileSettingsModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeProfileSettingsModal() {
        document.getElementById('profileSettingsModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        resetPasswordForm();
    }

    function switchTab(event, tabName) {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        // Add active class to selected tab
        event.currentTarget.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    }

    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = event.currentTarget.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Password strength checker
    document.getElementById('newPassword')?.addEventListener('input', function() {
        const password = this.value;
        const strengthContainer = document.getElementById('passwordStrength');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (password.length === 0) {
            strengthContainer.classList.add('hidden');
            return;
        }
        
        strengthContainer.classList.remove('hidden');
        
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        const strengthLevels = [
            { width: '20%', color: 'bg-red-500', text: 'Very Weak', textColor: 'text-red-600' },
            { width: '40%', color: 'bg-orange-500', text: 'Weak', textColor: 'text-orange-600' },
            { width: '60%', color: 'bg-yellow-500', text: 'Fair', textColor: 'text-yellow-600' },
            { width: '80%', color: 'bg-blue-500', text: 'Good', textColor: 'text-blue-600' },
            { width: '100%', color: 'bg-green-500', text: 'Strong', textColor: 'text-green-600' }
        ];
        
        const level = strengthLevels[strength - 1] || strengthLevels[0];
        strengthBar.style.width = level.width;
        strengthBar.className = 'h-full transition-all duration-300 ' + level.color;
        strengthText.textContent = level.text;
        strengthText.className = 'text-xs sm:text-sm font-medium ' + level.textColor;
    });

    // Password match checker
    document.getElementById('confirmPassword')?.addEventListener('input', function() {
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = this.value;
        const matchIndicator = document.getElementById('passwordMatch');
        
        if (confirmPassword.length === 0) {
            matchIndicator.classList.add('hidden');
            return;
        }
        
        matchIndicator.classList.remove('hidden');
        
        if (newPassword === confirmPassword) {
            matchIndicator.textContent = '✓ Passwords match';
            matchIndicator.className = 'mt-2 text-xs sm:text-sm text-green-600 font-medium';
        } else {
            matchIndicator.textContent = '✗ Passwords do not match';
            matchIndicator.className = 'mt-2 text-xs sm:text-sm text-red-600 font-medium';
        }
    });

    // Change password form submission
    document.getElementById('changePasswordForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        const errorDiv = document.getElementById('passwordError');
        const errorText = document.getElementById('passwordErrorText');
        const successDiv = document.getElementById('passwordSuccess');
        
        // Hide previous messages
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        
        // Validation
        if (newPassword !== confirmPassword) {
            errorText.textContent = 'New passwords do not match!';
            errorDiv.classList.remove('hidden');
            return;
        }
        
        if (newPassword.length < 8) {
            errorText.textContent = 'Password must be at least 8 characters long!';
            errorDiv.classList.remove('hidden');
            return;
        }
        
        if (newPassword === currentPassword) {
            errorText.textContent = 'New password must be different from current password!';
            errorDiv.classList.remove('hidden');
            return;
        }
        
        // Send to server
        try {
            const formData = new FormData();
            formData.append('currentPassword', currentPassword);
            formData.append('newPassword', newPassword);
            
            const response = await fetch('../actions/change_password_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                successDiv.classList.remove('hidden');
                setTimeout(() => {
                    resetPasswordForm();
                    successDiv.classList.add('hidden');
                }, 3000);
            } else {
                errorText.textContent = result.message || 'Failed to change password. Please try again.';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            errorText.textContent = 'An error occurred. Please try again.';
            errorDiv.classList.remove('hidden');
        }
    });

    function resetPasswordForm() {
        document.getElementById('changePasswordForm')?.reset();
        document.getElementById('passwordStrength').classList.add('hidden');
        document.getElementById('passwordMatch').classList.add('hidden');
        document.getElementById('passwordError').classList.add('hidden');
        document.getElementById('passwordSuccess').classList.add('hidden');
    }

    // Display current date
    function displayCurrentDate() {
        const dateElement = document.getElementById('currentDate');
        const today = new Date();
        
        // Different format for mobile vs desktop
        if (window.innerWidth < 640) {
            const options = { month: 'short', day: 'numeric' };
            dateElement.textContent = today.toLocaleDateString('en-US', options);
        } else {
            const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
            dateElement.textContent = today.toLocaleDateString('en-US', options);
        }
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
        
        // Initialize sidebar state based on screen size
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        if (window.innerWidth >= 1024) {
            // Desktop: Start collapsed
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-expanded');
            mainContent.style.marginLeft = '5rem';
            sidebarExpanded = false;
        } else {
            // Mobile: Start hidden (collapsed off-screen)
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-expanded');
            mainContent.style.marginLeft = '0';
            sidebarExpanded = false;
        }
        
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
                const hamburgerBtn = document.getElementById('hamburgerBtn');
                const mobileHamburgerBtn = document.getElementById('mobileHamburgerBtn');
                
                // Update date format based on screen size
                displayCurrentDate();
                
                if (window.innerWidth >= 1024) {
                    // Desktop mode
                    overlay.classList.add('hidden');
                    overlay.classList.remove('show');
                    document.body.style.overflow = 'auto';
                    
                    // Reset sidebar to proper desktop state
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
                    // Mobile mode
                    mainContent.style.marginLeft = '0';
                    
                    // Reset sidebar to proper mobile state
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
                closeUploadModal();
                closeProfileSettingsModal();
                if (sidebarExpanded && window.innerWidth < 1024) {
                    closeSidebar();
                }
            }
        });

        // Close modals when clicking outside
        document.getElementById('uploadModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });

        document.getElementById('profileSettingsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeProfileSettingsModal();
            }
        });

        // Prevent horizontal scroll
        document.body.style.overflowX = 'hidden';
    });

  // Touch swipe support for mobile sidebar
let touchStartX = 0;
let touchStartY = 0;
let touchEndX = 0;
let touchEndY = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
    touchStartY = e.changedTouches[0].screenY;
}, false);

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    touchEndY = e.changedTouches[0].screenY;
    handleSwipe();
}, false);

function handleSwipe() {
    if (window.innerWidth >= 1024) return;

    const dx = touchEndX - touchStartX; // horizontal drag
    const dy = touchEndY - touchStartY; // vertical drag

    const swipeThreshold = 90;         // horizontal distance needed
    const verticalTolerance = 60;      // ignore if vertical movement is large

    // ❌ If vertical movement is bigger than horizontal → user is scrolling → IGNORE
    if (Math.abs(dy) > Math.abs(dx) || Math.abs(dy) > verticalTolerance) return;

    // Swipe RIGHT → open sidebar
    if (dx > swipeThreshold && !sidebarExpanded) {
        toggleSidebar();
    }

    // Swipe LEFT → close sidebar
    if (-dx > swipeThreshold && sidebarExpanded) {
        toggleSidebar();
    }
}

</script>

</body>
</html>