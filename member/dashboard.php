<?php
// ✅ Start session only if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_conn.php';
require_once __DIR__ . '/../includes/journey_fetch.php';

// ✅ Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// ✅ Fetch user info safely
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender, section, student_id FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// ✅ Default avatar logic
$defaultAvatar = "../assets/img/avatar_neutral.png";
if (!empty($student['gender'])) {
    $g = strtolower($student['gender']);
    if ($g === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif ($g === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    }
}
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

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
        $filename = 'profile_' . $studentId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old profile picture if exists and is not default
            if (!empty($student['profile_pic']) && file_exists('../' . $student['profile_pic'])) {
                unlink('../' . $student['profile_pic']);
            }
            
            // Update database
            $relativePath = 'uploads/profiles/' . $filename;
            $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $updateStmt->execute([$relativePath, $studentId]);
            
            // Refresh page to show new picture
            header("Location: dashboard.php?upload=success");
            exit();
        }
    }
}

// ✅ Fetch journey data
$journeyData = getStudentJourney($conn, $studentId);
$steps = $journeyData['steps'] ?? [];
$stats = $journeyData['stats'] ?? [
    'completed' => 0,
    'total' => 1,
    'current' => 0,
    'pending' => 0,
    'progress' => 0
];
$quizzes = $journeyData['quizzes'] ?? [];

// ✅ Fetch daily tip safely
$dailyTipStmt = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1");
$dailyTip = $dailyTipStmt ? $dailyTipStmt->fetchColumn() : null;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - MedAce</title>
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

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-scale-in {
            animation: scaleIn 0.4s ease-out;
        }

        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-collapsed {
            width: 5rem;
        }

        .sidebar-collapsed .nav-text,
        .sidebar-collapsed .profile-info,
        .sidebar-collapsed .sidebar-setting-btn {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-expanded {
            width: 18rem;
        }

        .sidebar-expanded .nav-text,
        .sidebar-expanded .profile-info,
        .sidebar-expanded .sidebar-setting-btn {
            opacity: 1;
            width: auto;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
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

        /* Settings Button Hover */
        .settings-btn {
            transition: all 0.2s ease;
        }

        .settings-btn:hover {
            background-color: #f1f5f9 !important;
            color: #0ea5e9 !important;
        }

        .settings-btn:active {
            transform: scale(0.95);
        }

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
            animation: fadeIn 0.3s ease-out;
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
        }

        .modal-content-small {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 500px;
            width: 90%;
            animation: scaleIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .tab-button {
            position: relative;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
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
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                        <p class="text-xs text-gray-500">Student</p>
                    </div>
                    <!-- Settings Icon Button -->
                    <button onclick="openProfileSettingsModal()" class="settings-btn sidebar-setting-btn flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-transparent text-gray-600 sidebar-transition" title="Profile Settings">
                        <i class="fas fa-cog text-lg"></i>
                    </button>
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
                <a href="progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">My Progress</span>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="resources.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-book text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Resources</span>
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

    <!-- Profile Picture Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content-small">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Update Profile Picture</h2>
                <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
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
            <div class="gradient-bg px-6 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-user-circle mr-3"></i>
                        Profile Settings
                    </h2>
                    <button onclick="closeProfileSettingsModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 px-6">
                <button class="tab-button active" onclick="switchTab(event, 'account')">
                    <i class="fas fa-user mr-2"></i>Account Details
                </button>
                <button class="tab-button" onclick="switchTab(event, 'password')">
                    <i class="fas fa-lock mr-2"></i>Change Password
                </button>
            </div>

            <!-- Tab Contents -->
            <div class="p-6">
                <!-- Account Details Tab -->
                <div id="account-tab" class="tab-content active">
                    <div class="space-y-6">
                        <!-- Profile Picture Section -->
                        <div class="text-center pb-6 border-b border-gray-200">
                            <div class="relative inline-block">
                                <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-32 h-32 rounded-full object-cover ring-4 ring-primary-500 mx-auto mb-4">
                                <button type="button" onclick="openUploadModal()" class="absolute bottom-4 right-0 bg-primary-600 hover:bg-primary-700 text-white rounded-full p-3 shadow-lg transition-colors">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mt-2"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                            <p class="text-gray-600">Student Account</p>
                        </div>

                        <!-- Account Information -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-primary-500 mr-2"></i>First Name
                                </label>
                                <input type="text" value="<?= htmlspecialchars($student['firstname']) ?>" readonly class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-primary-500 mr-2"></i>Last Name
                                </label>
                                <input type="text" value="<?= htmlspecialchars($student['lastname']) ?>" readonly class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-primary-500 mr-2"></i>Email Address
                                </label>
                                <input type="email" value="<?= htmlspecialchars($student['email']) ?>" readonly class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-venus-mars text-primary-500 mr-2"></i>Gender
                                </label>
                                <input type="text" value="<?= htmlspecialchars(ucfirst($student['gender'] ?? 'Not specified')) ?>" readonly class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-primary-500 mr-2"></i>Student ID
                                </label>
                                <input type="text" value="<?= htmlspecialchars($student['student_id'] ?? 'Not assigned') ?>" readonly class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-users text-primary-500 mr-2"></i>Section
                                </label>
                                <input type="text" value="<?= htmlspecialchars($student['section'] ?? 'Not assigned') ?>" readonly class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                            </div>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-primary-500 p-4 rounded-r-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-primary-500 mt-0.5 mr-3"></i>
                                <div>
                                    <p class="text-sm font-semibold text-primary-900 mb-1">Account Information</p>
                                    <p class="text-sm text-primary-700">To update your account details, please contact your administrator.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password Tab -->
                <div id="password-tab" class="tab-content">
                    <form id="changePasswordForm" class="space-y-6">
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r-lg mb-6">
                            <div class="flex">
                                <i class="fas fa-shield-alt text-yellow-500 mt-0.5 mr-3"></i>
                                <div>
                                    <p class="text-sm font-semibold text-yellow-900 mb-1">Password Security</p>
                                    <p class="text-sm text-yellow-700">Choose a strong password with at least 8 characters, including uppercase, lowercase, numbers, and symbols.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-lock text-primary-500 mr-2"></i>Current Password
                            </label>
                            <div class="relative">
                                <input type="password" id="currentPassword" name="currentPassword" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
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
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
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
                                    <span id="strengthText" class="text-sm font-medium"></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-check-circle text-primary-500 mr-2"></i>Confirm New Password
                            </label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" name="confirmPassword" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                                       placeholder="Confirm your new password">
                                <button type="button" onclick="togglePasswordVisibility('confirmPassword')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="passwordMatch" class="mt-2 text-sm hidden"></p>
                        </div>

                        <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                            <div class="flex">
                                <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                                <p class="text-sm text-red-700" id="passwordErrorText"></p>
                            </div>
                        </div>

                        <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">
                            <div class="flex">
                                <i class="fas fa-check-circle text-green-500 mt-0.5 mr-3"></i>
                                <p class="text-sm text-green-700">Password changed successfully!</p>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors shadow-sm">
                                <i class="fas fa-save mr-2"></i>Update Password
                            </button>
                            <button type="button" onclick="resetPasswordForm()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
            <div class="gradient-bg rounded-2xl p-6 sm:p-8 mb-8 text-white shadow-lg animate-fade-in-up">
                <h1 class="text-2xl sm:text-3xl font-bold mb-2">Welcome back, <?= htmlspecialchars($student['firstname']) ?>! 👋</h1>
                <p class="text-blue-100 text-sm sm:text-base">Continue your nursing journey and track your progress</p>
            </div>

            <!-- Progress Tracker -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-8 animate-fade-in-up">
                <!-- Header with Progress -->
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-chart-line text-primary-500 mr-2 text-sm"></i>
                        Learning Progress
                    </h2>
                    <span class="text-2xl font-bold text-primary-600"><?= $stats['progress'] ?>%</span>
                </div>

                <!-- Progress Bar -->
                <div class="relative w-full bg-gray-200 h-2 rounded-full overflow-hidden mb-3">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-600 to-primary-500 transition-all duration-1000" 
                         style="width: <?= $stats['progress'] ?>%"></div>
                </div>

                <!-- Stats Row - Inline -->
                <div class="flex items-center justify-between text-sm mb-4 pb-3 border-b border-gray-200">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                            <span class="text-gray-600"><?= $stats['total'] ?> Total</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <span class="text-gray-600"><?= $stats['completed'] ?> Done</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                            <span class="text-gray-600"><?= $stats['current'] ?> Active</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                            <span class="text-gray-600"><?= $stats['pending'] ?> Pending</span>
                        </div>
                    </div>
                </div>

                <!-- Journey Steps - Horizontal Compact -->
                <?php if (!empty($steps)): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Current Journey</p>
                    <div class="flex items-center gap-2 overflow-x-auto pb-2">
                        <?php foreach ($steps as $index => $step): ?>
                            <?php
                                $st = strtolower($step['status']);
                                $isCompleted = ($st === 'completed');
                                $isCurrent = ($st === 'current');
                            ?>
                            <div class="flex-shrink-0 flex items-center gap-1.5">
                                <!-- Status Icon -->
                                <div class="relative flex items-center justify-center w-8 h-8 rounded-lg
                                    <?= $isCompleted ? 'bg-green-100 text-green-600' : 
                                        ($isCurrent ? 'bg-blue-100 text-blue-600 ring-2 ring-blue-300' : 
                                        'bg-gray-100 text-gray-400') ?>">
                                    <?php if ($isCompleted): ?>
                                        <i class="fas fa-check text-xs"></i>
                                    <?php elseif ($isCurrent): ?>
                                        <i class="fas fa-play text-xs"></i>
                                    <?php else: ?>
                                        <i class="fas fa-lock text-xs"></i>
                                    <?php endif; ?>
                                    
                                    <!-- Pulsing indicator for current -->
                                    <?php if ($isCurrent): ?>
                                        <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-500"></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Title -->
                                <div class="px-2.5 py-1.5 rounded-lg border
                                    <?= $isCompleted ? 'bg-green-50 border-green-200' : 
                                        ($isCurrent ? 'bg-blue-50 border-blue-200' : 
                                        'bg-gray-50 border-gray-200') ?>">
                                    <p class="text-xs font-medium text-gray-900 whitespace-nowrap">
                                        <?= $step['type'] === 'module' ? '📘' : '📝' ?>
                                        <?= htmlspecialchars($step['title']) ?>
                                    </p>
                                </div>

                                <!-- Arrow connector (except last item) -->
                                <?php if ($index < count($steps) - 1): ?>
                                    <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quizzes Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up" style="animation-delay: 0.1s;">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-clipboard-list text-primary-500 mr-2"></i>
                        Available Quizzes
                    </h2>
                </div>

                <?php if (empty($quizzes)): ?>
                <div class="text-center py-16 px-4">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-clipboard-list text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No quizzes yet</h3>
                    <p class="text-gray-600">Check back later for new quizzes</p>
                </div>
                <?php else: ?>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($quizzes as $quiz): ?>
                            <?php 
                                $st = strtolower($quiz['status']); 
                                $statusConfig = match($st) {
                                    'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle', 'btnBg' => 'bg-green-600 hover:bg-green-700', 'btnText' => 'Retake Quiz'],
                                    'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => 'fa-times-circle', 'btnBg' => 'bg-red-600 hover:bg-red-700', 'btnText' => 'Retry Quiz'],
                                    'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'icon' => 'fa-clock', 'btnBg' => 'bg-primary-600 hover:bg-primary-700', 'btnText' => 'Start Quiz'],
                                    default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'icon' => 'fa-info-circle', 'btnBg' => 'bg-primary-600 hover:bg-primary-700', 'btnText' => 'Start Quiz']
                                };
                                
                                // Get the latest attempt for this quiz
                                $attemptStmt = $conn->prepare("
                                    SELECT id FROM quiz_attempts 
                                    WHERE student_id = ? AND quiz_id = ? 
                                    ORDER BY attempted_at DESC LIMIT 1
                                ");
                                $attemptStmt->execute([$studentId, $quiz['id']]);
                                $latestAttempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
                                $hasAttempt = !empty($latestAttempt);
                            ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-6 card-hover">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 mb-2 text-lg">
                                            <?= htmlspecialchars($quiz['title'] ?? 'Untitled Quiz') ?>
                                        </h3>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>">
                                        <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                        <?= ucfirst($st) ?>
                                    </span>
                                </div>
                                
                                <div class="flex gap-2">
                                    <a href="../member/take_quiz.php?id=<?= $quiz['id'] ?>"
                                       class="flex-1 text-center <?= $statusConfig['btnBg'] ?> text-white px-4 py-3 rounded-lg font-semibold transition-colors shadow-sm">
                                        <i class="fas fa-play mr-2"></i>
                                        <?= $statusConfig['btnText'] ?>
                                    </a>
                                    
                                    <?php if ($hasAttempt && ($st === 'completed' || $st === 'failed')): ?>
                                        <a href="quiz_result.php?attempt_id=<?= $latestAttempt['id'] ?>"
                                           class="flex-shrink-0 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg font-semibold transition-colors shadow-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Daily Tip -->
            <?php if ($dailyTip): ?>
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl p-6 sm:p-8 text-center border-2 border-purple-200 shadow-sm mt-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full mb-4 shadow-lg">
                    <i class="fas fa-lightbulb text-2xl text-white"></i>
                </div>
                <h3 class="text-lg font-semibold mb-3 text-purple-900">💡 Daily Nursing Tip</h3>
                <p class="text-gray-700 text-lg italic leading-relaxed max-w-2xl mx-auto">
                    "<?= htmlspecialchars($dailyTip) ?>"
                </p>
            </div>
            <?php endif; ?>
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
        
        sidebarExpanded = !sidebarExpanded;
        
        if (window.innerWidth < 1024) {
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            overlay.classList.toggle('hidden');
            if (sidebarExpanded) {
                mainContent.style.marginLeft = '0';
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
        event.target.classList.add('active');
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
        strengthText.className = 'text-sm font-medium ' + level.textColor;
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
            matchIndicator.className = 'mt-2 text-sm text-green-600 font-medium';
        } else {
            matchIndicator.textContent = '✗ Passwords do not match';
            matchIndicator.className = 'mt-2 text-sm text-red-600 font-medium';
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
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        const today = new Date();
        dateElement.textContent = today.toLocaleDateString('en-US', options);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        displayCurrentDate();

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

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUploadModal();
                closeProfileSettingsModal();
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
    });
</script>

</body>
</html>