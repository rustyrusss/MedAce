<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// Get student info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// Default avatar
if (!empty($student['gender'])) {
    if (strtolower($student['gender']) === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif (strtolower($student['gender']) === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}

// Profile picture
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// FIXED: Fetch all available modules (both 'active' and 'published' status to match view_module.php)
$stmt = $conn->prepare("
    SELECT 
        m.id, 
        m.title, 
        m.description, 
        m.content, 
        COALESCE(sp.status, 'Pending') AS status
    FROM modules m
    LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
    WHERE m.status IN ('active', 'published')
    ORDER BY m.display_order ASC, m.created_at DESC
");
$stmt->execute([$studentId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

// Get success/error messages from session
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - MedAce</title>
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
                    },
                    screens: {
                        'xs': '475px',
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

        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
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

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
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

        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                        width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Desktop Sidebar Styles */
        @media (min-width: 1025px) {
            #sidebar {
                width: 5rem;
            }

            #sidebar.sidebar-expanded {
                width: 18rem;
            }

            #sidebar .nav-text,
            #sidebar .profile-info {
                opacity: 0;
                width: 0;
                overflow: hidden;
                transition: opacity 0.2s ease;
            }

            #sidebar.sidebar-expanded .nav-text,
            #sidebar.sidebar-expanded .profile-info {
                opacity: 1;
                width: auto;
                transition: opacity 0.3s ease 0.1s;
            }

            #main-content {
                margin-left: 5rem;
            }

            #main-content.content-expanded {
                margin-left: 18rem;
            }

            /* Hide toggle button on desktop */
            .mobile-toggle-btn {
                display: none;
            }
        }

        /* Mobile Sidebar Styles */
        @media (max-width: 1024px) {
            #sidebar {
                width: 18rem;
                transform: translateX(-100%);
            }

            #sidebar.sidebar-expanded {
                transform: translateX(0);
            }

            #sidebar .nav-text,
            #sidebar .profile-info {
                opacity: 1;
                width: auto;
            }

            #main-content {
                margin-left: 0 !important;
            }

            /* Show toggle button on mobile */
            .mobile-toggle-btn {
                display: flex;
            }

            /* Hide desktop toggle button on mobile */
            .desktop-toggle-section {
                display: none;
            }
        }

        @media (max-width: 768px) {
            #sidebar {
                width: 16rem;
            }
        }

        @media (max-width: 640px) {
            #sidebar {
                width: 85vw;
                max-width: 20rem;
            }
        }

        /* Prevent body scroll when sidebar is open on mobile */
        body.sidebar-open {
            overflow: hidden;
        }

        @media (min-width: 1025px) {
            body.sidebar-open {
                overflow: auto;
            }
        }

        /* Overlay transition */
        #sidebar-overlay {
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        #sidebar-overlay.show {
            opacity: 1;
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        [x-cloak] { display: none !important; }

        /* Main container responsiveness */
        .main-container {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* ✅ Success message styling for HTML content */
        .success-message-content {
            line-height: 1.6;
        }

        .success-message-content strong {
            font-weight: 600;
            color: #166534;
        }

        .success-message-content a {
            display: inline-block;
            margin-top: 0.75rem;
            padding: 0.5rem 1rem;
            background: #0ea5e9;
            color: white;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .success-message-content a:hover {
            background: #0284c7;
        }

        .success-message-content ul {
            list-style: none;
            margin: 0.5rem 0;
            padding-left: 0;
        }

        .success-message-content ul li {
            margin: 0.25rem 0;
            padding-left: 1.25rem;
            position: relative;
        }

        .success-message-content ul li::before {
            content: "•";
            position: absolute;
            left: 0.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ activeFilter: 'All', searchQuery: '', showAlert: <?= ($successMessage || $errorMessage) ? 'true' : 'false' ?> }">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition">
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between px-4 py-5 border-b border-gray-200">
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3 h-3 sm:w-3.5 sm:h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                        <p class="text-xs text-gray-500">Student</p>
                    </div>
                </div>
            </div>

            <!-- Desktop Toggle Button (Hidden on Mobile) -->
            <div class="desktop-toggle-section px-4 py-3 border-b border-gray-200">
                <button onclick="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
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
                <a href="resources.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-book text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Resources</span>
                </a>
            </nav>

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
    <main id="main-content" class="flex-1 transition-all duration-300 main-container">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between gap-3">
                <!-- Mobile Toggle Button (Visible on Mobile) -->
                <button onclick="toggleSidebar()" class="mobile-toggle-btn lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0">
                    <i class="fas fa-bars text-gray-600 text-xl"></i>
                </button>
                
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900 truncate flex-1 text-center lg:text-left">Learning Resources</h1>
                
                <!-- Spacer for mobile to center title -->
                <div class="w-10 lg:hidden"></div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-full">
            <!-- Success/Error Messages -->
            <?php if ($successMessage): ?>
            <div x-show="showAlert" class="mb-6 animate-slide-down">
                <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 flex items-start justify-between shadow-sm">
                    <div class="flex items-start min-w-0 flex-1">
                        <i class="fas fa-check-circle text-green-500 text-lg sm:text-xl mr-3 mt-0.5 flex-shrink-0"></i>
                        <div class="text-green-800 font-medium text-sm sm:text-base break-words success-message-content">
                            <?= $successMessage ?>
                        </div>
                    </div>
                    <button @click="showAlert = false" class="text-green-500 hover:text-green-700 ml-4 flex-shrink-0">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div x-show="showAlert" class="mb-6 animate-slide-down">
                <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 flex items-start justify-between shadow-sm">
                    <div class="flex items-start min-w-0 flex-1">
                        <i class="fas fa-exclamation-circle text-red-500 text-lg sm:text-xl mr-3 mt-0.5 flex-shrink-0"></i>
                        <p class="text-red-800 font-medium text-sm sm:text-base break-words"><?= htmlspecialchars($errorMessage) ?></p>
                    </div>
                    <button @click="showAlert = false" class="text-red-500 hover:text-red-700 ml-4 flex-shrink-0">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="flex flex-col gap-4 mb-6 animate-fade-in-up">
                <div class="relative">
                    <input type="text" x-model="searchQuery" placeholder="Search modules..." 
                           class="w-full pl-10 sm:pl-12 pr-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    <i class="fas fa-search absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm sm:text-base"></i>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button @click="activeFilter = 'All'" :class="activeFilter === 'All' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap text-xs sm:text-sm">
                        All
                    </button>
                    <button @click="activeFilter = 'Pending'" :class="activeFilter === 'Pending' ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap text-xs sm:text-sm">
                        Pending
                    </button>
                    <button @click="activeFilter = 'In Progress'" :class="activeFilter === 'In Progress' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap text-xs sm:text-sm">
                        In Progress
                    </button>
                    <button @click="activeFilter = 'Completed'" :class="activeFilter === 'Completed' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap text-xs sm:text-sm">
                        Completed
                    </button>
                </div>
            </div>

            <!-- Modules Count -->
            <div class="mb-4 text-xs sm:text-sm text-gray-600 animate-fade-in-up">
                Showing <strong><?= count($modules) ?></strong> available module(s)
            </div>

            <!-- Modules Grid -->
            <?php if (empty($modules)): ?>
            <div class="text-center py-12 sm:py-20 animate-fade-in-up">
                <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-book text-3xl sm:text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">No modules available yet</h3>
                <p class="text-sm sm:text-base text-gray-600">Check back later for new learning materials</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                <?php foreach ($modules as $index => $module): ?>
                    <?php
                        $coverImage = "../assets/img/module/module_default.jpg";
                        $status = ucwords(strtolower($module['status']));
                        $statusConfig = match (strtolower($module['status'])) {
                            'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle'],
                            'in progress' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-spinner'],
                            'pending' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'icon' => 'fa-clock'],
                            default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'icon' => 'fa-info-circle']
                        };
                        $moduleTitle = htmlspecialchars($module['title']);
                        $moduleTitleLower = strtolower($module['title']);
                    ?>
                    <div x-show="(activeFilter === 'All' || activeFilter === '<?= htmlspecialchars($status) ?>') && 
                                  (searchQuery === '' || '<?= htmlspecialchars($moduleTitleLower) ?>'.includes(searchQuery.toLowerCase()))"
                         x-cloak
                         class="bg-white border border-gray-200 rounded-xl overflow-hidden card-hover animate-fade-in-up"
                         style="animation-delay: <?= $index * 0.05 ?>s;">
                        <!-- Cover Image -->
                        <div class="h-32 xs:h-36 sm:h-40 overflow-hidden relative group">
                            <img src="<?= $coverImage ?>" alt="<?= $moduleTitle ?>"
                                 class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                            <div class="absolute bottom-2 sm:bottom-3 left-2 sm:left-3 right-2 sm:right-3">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 rounded-full text-xs font-semibold <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?> backdrop-blur-sm">
                                        <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-4 sm:p-5">
                            <h3 class="font-semibold text-gray-900 text-base sm:text-lg mb-2 line-clamp-2">
                                <?= $moduleTitle ?>
                            </h3>
                            <p class="text-xs sm:text-sm text-gray-600 mb-3 sm:mb-4 line-clamp-2">
                                <?= htmlspecialchars($module['description'] ?: "No description available.") ?>
                            </p>

                            <!-- Action Button -->
                            <a href="view_module.php?id=<?= $module['id'] ?>"
                               class="block w-full text-center bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 sm:py-2.5 rounded-lg font-semibold transition-colors text-xs sm:text-sm">
                                <i class="fas fa-book-open mr-1 sm:mr-2"></i>
                                Start Learning
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Daily Tip -->
            <?php if ($dailyTip): ?>
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl p-4 sm:p-6 lg:p-8 text-center border-2 border-purple-200 shadow-sm mt-6 sm:mt-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full mb-3 sm:mb-4 shadow-lg">
                    <i class="fas fa-lightbulb text-xl sm:text-2xl text-white"></i>
                </div>
                <h3 class="text-base sm:text-lg font-semibold mb-2 sm:mb-3 text-purple-900">💡 Daily Nursing Tip</h3>
                <p class="text-gray-700 text-sm sm:text-base lg:text-lg italic leading-relaxed max-w-2xl mx-auto break-words">
                    "<?= htmlspecialchars($dailyTip) ?>"
                </p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    let sidebarExpanded = false;

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebarExpanded = !sidebarExpanded;
        
        if (window.innerWidth < 1025) {
            // Mobile behavior
            if (sidebarExpanded) {
                sidebar.classList.add('sidebar-expanded');
                overlay.classList.remove('hidden');
                overlay.classList.add('show');
                document.body.classList.add('sidebar-open');
            } else {
                sidebar.classList.remove('sidebar-expanded');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 300);
            }
        } else {
            // Desktop behavior
            if (sidebarExpanded) {
                sidebar.classList.add('sidebar-expanded');
                mainContent.classList.add('content-expanded');
            } else {
                sidebar.classList.remove('sidebar-expanded');
                mainContent.classList.remove('content-expanded');
            }
        }
    }

    function closeSidebar() {
        if (sidebarExpanded) {
            toggleSidebar();
        }
    }

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (window.innerWidth >= 1025) {
                // Desktop mode - reset mobile states
                overlay.classList.add('hidden');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                
                // Apply correct desktop state
                if (!sidebarExpanded) {
                    sidebar.classList.remove('sidebar-expanded');
                    mainContent.classList.remove('content-expanded');
                } else {
                    sidebar.classList.add('sidebar-expanded');
                    mainContent.classList.add('content-expanded');
                }
            } else {
                // Mobile mode - reset desktop states
                mainContent.classList.remove('content-expanded');
                
                if (!sidebarExpanded) {
                    sidebar.classList.remove('sidebar-expanded');
                    overlay.classList.add('hidden');
                    overlay.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                }
            }
        }, 250);
    });

    // Initialize correct state on page load
    window.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        if (window.innerWidth >= 1025) {
            // Desktop: start collapsed
            sidebar.classList.remove('sidebar-expanded');
            mainContent.classList.remove('content-expanded');
            sidebarExpanded = false;
        } else {
            // Mobile: ensure sidebar is hidden
            sidebar.classList.remove('sidebar-expanded');
            sidebarExpanded = false;
        }
    });

    // Touch swipe support for mobile sidebar
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
        if (window.innerWidth < 1025) {
            // Swipe right to open
            if (touchEndX - touchStartX > 50 && !sidebarExpanded) {
                toggleSidebar();
            }
            // Swipe left to close
            if (touchStartX - touchEndX > 50 && sidebarExpanded) {
                toggleSidebar();
            }
        }
    }

    // Close sidebar on escape key (mobile only)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebarExpanded && window.innerWidth < 1025) {
            closeSidebar();
        }
    });

    // Auto-hide alert after 10 seconds
    <?php if ($successMessage || $errorMessage): ?>
    setTimeout(() => {
        const alpineData = document.querySelector('[x-data]')?.__x?.$data;
        if (alpineData) {
            alpineData.showAlert = false;
        }
    }, 10000);
    <?php endif; ?>
</script>

</body>
</html>