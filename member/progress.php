<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/journey_fetch.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$journeyData = getStudentJourney($conn, $studentId);

// Avoid duplicate quizzes by only keeping the latest attempt
$modules = $journeyData['modules'] ?? [];
$quizzes = [];
if (!empty($journeyData['quizzes'])) {
    $temp = [];
    foreach ($journeyData['quizzes'] as $quiz) {
        $temp[$quiz['id']] = $quiz; // overwrite duplicates, keep latest
    }
    $quizzes = array_values($temp);
}

$stats = $journeyData['stats'] ?? ['completed' => 0, 'total' => 0, 'progress' => 0];

// Student info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// Default avatar logic
if (!empty($student['gender'])) {
    $defaultAvatar = strtolower($student['gender']) === "male"
        ? "../assets/img/avatar_male.png"
        : (strtolower($student['gender']) === "female"
            ? "../assets/img/avatar_female.png"
            : "../assets/img/avatar_neutral.png");
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// Daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

// Stats for progress chart
$completedSteps = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'completed')) +
                  count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'completed'));
$currentSteps = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'current')) +
                count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'current'));
$pendingSteps = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'pending')) +
                count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'pending'));
$totalSteps = max(count($modules) + count($quizzes), 1);
$progressPercent = round(($completedSteps / $totalSteps) * 100);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Progress - MedAce</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
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

        [x-cloak] { display: none !important; }

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
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ filter: 'all' }">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition sidebar-collapsed">
        <div class="flex flex-col h-full">
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

            <div class="px-4 py-3 border-b border-gray-200">
                <button onclick="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-primary-600 w-5 text-center flex-shrink-0"></i>
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
                    <h1 class="text-xl font-bold text-gray-900">Progress Overview</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Progress Chart Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row items-center gap-8">
                    <!-- Chart -->
                    <div class="relative w-64 h-64">
                        <canvas id="progressChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-4xl font-bold text-primary-600"><?= $progressPercent ?>%</p>
                            <p class="text-sm text-gray-500">Complete</p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="flex-1 w-full">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Your Learning Journey</h2>
                        <p class="text-gray-600 mb-6">You've completed <?= $completedSteps ?> of <?= $totalSteps ?> tasks. Keep up the great work!</p>
                        
                        <!-- Progress Bar -->
                        <div class="relative w-full bg-gray-200 h-3 rounded-full overflow-hidden mb-6">
                            <div class="absolute inset-0 bg-gradient-to-r from-primary-600 to-primary-500 transition-all duration-1000" 
                                 style="width: <?= $progressPercent ?>%"></div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                                <p class="text-2xl font-bold text-green-700"><?= $completedSteps ?></p>
                                <p class="text-xs text-green-600 font-medium">Completed</p>
                            </div>
                            <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <p class="text-2xl font-bold text-blue-700"><?= $currentSteps ?></p>
                                <p class="text-xs text-blue-600 font-medium">In Progress</p>
                            </div>
                            <div class="text-center p-4 bg-amber-50 rounded-lg border border-amber-200">
                                <p class="text-2xl font-bold text-amber-700"><?= $pendingSteps ?></p>
                                <p class="text-xs text-amber-600 font-medium">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="flex flex-wrap gap-2 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                        class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm">
                    All
                </button>
                <button @click="filter = 'completed'" :class="filter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                        class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm">
                    Completed
                </button>
                <button @click="filter = 'current'" :class="filter === 'current' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                        class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm">
                    In Progress
                </button>
                <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                        class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm">
                    Pending
                </button>
            </div>

            <!-- Activity Grid -->
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Modules -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-book text-blue-500 mr-2"></i>
                            Modules
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (!empty($modules)): ?>
                            <div class="space-y-3">
                                <?php foreach ($modules as $module): ?>
                                    <div x-show="filter === 'all' || filter === '<?= strtolower($module['status']) ?>'" x-cloak
                                         class="p-4 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($module['title']) ?></h4>
                                                <?php if (!empty($module['description'])): ?>
                                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($module['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="ml-3 flex-shrink-0 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                <?= strtolower($module['status']) === 'completed' ? 'bg-green-100 text-green-700' : 
                                                    (strtolower($module['status']) === 'current' ? 'bg-blue-100 text-blue-700' : 
                                                    'bg-amber-100 text-amber-700') ?>">
                                                <?= htmlspecialchars(ucfirst($module['status'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-book text-4xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500">No modules found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quizzes -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-clipboard-list text-purple-500 mr-2"></i>
                            Quizzes
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (!empty($quizzes)): ?>
                            <div class="space-y-3">
                                <?php foreach ($quizzes as $quiz): ?>
                                    <div x-show="filter === 'all' || filter === '<?= strtolower($quiz['status']) ?>'" x-cloak
                                         class="p-4 rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition-all">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($quiz['title']) ?></h4>
                                                <?php if (!empty($quiz['description'])): ?>
                                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($quiz['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="ml-3 flex-shrink-0 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                <?= strtolower($quiz['status']) === 'completed' ? 'bg-green-100 text-green-700' : 
                                                    (strtolower($quiz['status']) === 'current' ? 'bg-blue-100 text-blue-700' : 
                                                    'bg-amber-100 text-amber-700') ?>">
                                                <?= htmlspecialchars(ucfirst($quiz['status'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500">No quizzes found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Daily Tip -->
            <?php if ($dailyTip): ?>
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl p-6 sm:p-8 text-center border-2 border-purple-200 shadow-sm mt-8 animate-fade-in-up" style="animation-delay: 0.4s;">
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

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    let sidebarExpanded = false;

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

    // Chart
    const ctx = document.getElementById('progressChart');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'In Progress', 'Pending'],
            datasets: [{
                data: [<?= $completedSteps ?>, <?= $currentSteps ?>, <?= $pendingSteps ?>],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    }
                }
            }
        }
    });

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
</script>

</body>
</html>   