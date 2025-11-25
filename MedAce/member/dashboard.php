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
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
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
        .sidebar-collapsed .profile-info {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-expanded {
            width: 18rem;
        }

        .sidebar-expanded .nav-text,
        .sidebar-expanded .profile-info {
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
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                        <p class="text-xs text-gray-500">Student</p>
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
    });
</script>

</body>
</html>