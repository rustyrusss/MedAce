<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/journey_fetch.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

$journeyData = getStudentJourney($conn, $studentId);

$modules = $journeyData['modules'] ?? [];
$quizzes = $journeyData['quizzes'] ?? [];
$stats   = $journeyData['stats'] ?? ['completed' => 0, 'total' => 0, 'progress' => 0];

// ✅ Build SUBJECT LIST for filter (from modules + quizzes)
$subjectSet = [];
foreach ($modules as $m) {
    if (!empty($m['subject'])) {
        $subjectSet[$m['subject']] = true;
    }
}
foreach ($quizzes as $q) {
    if (!empty($q['subject'])) {
        $subjectSet[$q['subject']] = true;
    }
}
$subjects = array_keys($subjectSet);
sort($subjects);

// Student info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

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

$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

// ✅ Stats based on journey data
$completedModules = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'completed'));
$passedSteps      = count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'passed'));
$failedSteps      = count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'failed'));
$pendingSteps     = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'pending')) +
                    count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'pending'));

$totalCompleted   = $passedSteps + $completedModules;
$totalSteps       = max(count($modules) + count($quizzes), 1);
$progressPercent  = round(($totalCompleted / $totalSteps) * 100);
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
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        .animate-fade-in-up { animation: fadeInUp 0.6s ease-out; }
        .animate-scale-in { animation: scaleIn 0.4s ease-out; }
        .message-slide-in { animation: slideInRight 0.3s ease-out; }

        .sidebar-transition { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-collapsed { width: 5rem; }
        .sidebar-collapsed .nav-text, .sidebar-collapsed .profile-info { opacity: 0; width: 0; overflow: hidden; transition: opacity 0.2s ease; }
        .sidebar-expanded { width: 18rem; }
        .sidebar-expanded .nav-text, .sidebar-expanded .profile-info { opacity: 1; width: auto; transition: opacity 0.3s ease 0.1s; }
        .gradient-bg { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
        [x-cloak] { display: none !important; }

        @media (max-width: 1024px) {
            .sidebar-collapsed { width: 18rem; transform: translateX(-100%); }
            .sidebar-expanded { width: 18rem; transform: translateX(0); }
            .sidebar-collapsed .nav-text, .sidebar-collapsed .profile-info { opacity: 1; width: auto; }
        }
        @media (max-width: 768px) {
            .sidebar-collapsed, .sidebar-expanded { width: 16rem; }
        }
        body.sidebar-open { overflow: hidden; }
        @media (min-width: 1024px) { body.sidebar-open { overflow: auto; } }

        .main-container {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .chart-container {
            position: relative;
            width: 100%;
            max-width: 16rem;
            height: 16rem;
            margin: 0 auto;
        }

        @media (max-width: 640px) {
            .chart-container {
                max-width: 14rem;
                height: 14rem;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }
        }

        /* Chatbot styles kept but no chatbot markup on this page */
        #chatbotContainer { position: fixed; bottom: 2rem; right: 2rem; z-index: 45; }
        #chatbotWindow { position: fixed; bottom: 6rem; right: 2rem; width: 420px; max-width: calc(100vw - 2rem); z-index: 44; }
        #quickActions { position: fixed; bottom: 6rem; right: 2rem; z-index: 43; }

        @media (min-width: 1025px) {
            #chatbotContainer { right: 2rem; }
            #chatbotWindow { right: 2rem; width: 420px; }
            #quickActions { right: 2rem; }
        }

        @media (max-width: 640px) {
            #chatbotContainer { right: 1rem; bottom: 1rem; }
            #chatbotWindow { left: 0; bottom: 0; right: 0; width: 100%; max-width: 100%; height: calc(100vh - 60px); border-radius: 1rem 1rem 0 0; }
            #quickActions { right: 1rem; bottom: 5rem; }
            #chatbotContainer.chat-open { display: none; }
        }

        .chat-tab {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .chat-tab.active { color: #0ea5e9; }
        .chat-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #0ea5e9;
        }
        .chat-tab:hover:not(.active) { color: #475569; background: #f8fafc; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ filter: 'all', selectedSubject: 'All' }">

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

            <div class="px-3 lg:px-4 py-2 lg:py-3 border-b border-gray-200 hidden lg:block">
                <button onclick="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 flex-shrink-0 text-center"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-primary-600 w-5 flex-shrink-0 text-center"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">My Progress</span>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 flex-shrink-0 text-center"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="resources.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-book text-gray-400 w-5 flex-shrink-0 text-center"></i>
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

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 transition-all duration-300 main-container overflow-y-auto" style="margin-left: 5rem;">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars text-gray-600 text-xl"></i>
                </button>
                <div class="flex items-center space-x-4">
                    <h1 class="text-lg sm:text-xl font-bold text-gray-900">Progress Overview</h1>
                </div>
            </div>
        </header>

        <div class="px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-full">
            <!-- Progress Chart Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row items-center gap-6 sm:gap-8">
                    <div class="chart-container">
                        <canvas id="progressChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <p class="text-3xl sm:text-4xl font-bold text-primary-600"><?= $progressPercent ?>%</p>
                            <p class="text-xs sm:text-sm text-gray-500">Complete</p>
                        </div>
                    </div>

                    <div class="flex-1 w-full">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3 sm:mb-4">Your Learning Journey</h2>
                        <p class="text-sm sm:text-base text-gray-600 mb-4 sm:mb-6">
                            You've completed <?= $totalCompleted ?> of <?= $totalSteps ?> tasks. Keep up the great work!
                        </p>
                        
                        <div class="relative w-full bg-gray-200 h-2.5 sm:h-3 rounded-full overflow-hidden mb-4 sm:mb-6">
                            <div class="absolute inset-0 bg-gradient-to-r from-primary-600 to-primary-500 transition-all duration-1000" style="width: <?= $progressPercent ?>%"></div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 stats-grid">
                            <div class="text-center p-3 sm:p-4 bg-emerald-50 rounded-lg border border-emerald-200">
                                <p class="text-xl sm:text-2xl font-bold text-emerald-700"><?= $completedModules ?></p>
                                <p class="text-xs text-emerald-600 font-medium">Modules Completed</p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-green-50 rounded-lg border border-green-200">
                                <p class="text-xl sm:text-2xl font-bold text-green-700"><?= $passedSteps ?></p>
                                <p class="text-xs text-green-600 font-medium">Quizzes Passed</p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-red-50 rounded-lg border border-red-200">
                                <p class="text-xl sm:text-2xl font-bold text-red-700"><?= $failedSteps ?></p>
                                <p class="text-xs text-red-600 font-medium">Quizzes Failed</p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-amber-50 rounded-lg border border-amber-200">
                                <p class="text-xl sm:text-2xl font-bold text-amber-700"><?= $pendingSteps ?></p>
                                <p class="text-xs text-amber-600 font-medium">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters: Status + Subject -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 sm:mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
                <!-- Status buttons -->
                <div class="flex flex-wrap gap-2">
                    <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm">All</button>
                    <button @click="filter = 'completed'" :class="filter === 'completed' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm">Completed</button>
                    <button @click="filter = 'passed'" :class="filter === 'passed' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm">Passed</button>
                    <button @click="filter = 'failed'" :class="filter === 'failed' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm">Failed</button>
                    <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm">Pending</button>
                </div>

                <!-- Subject dropdown (fixed: single custom chevron, native arrow hidden) -->
                <div class="relative w-full sm:w-64">
                    <select
                        x-model="selectedSubject"
                        class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent appearance-none cursor-pointer"
                    >
                        <option value="All">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= htmlspecialchars($subject) ?>">
                                <?= htmlspecialchars($subject) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            <!-- Activity Grid -->
            <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                <!-- Modules -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-book text-blue-500 mr-2"></i>Modules
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6 max-h-96 overflow-y-auto">
                        <?php if (!empty($modules)): ?>
                            <div class="space-y-3">
                                <?php foreach ($modules as $module): 
                                    $moduleStatus   = strtolower($module['status']);
                                    $moduleSubject  = !empty($module['subject']) ? $module['subject'] : 'General';
                                ?>
                                    <div 
                                        x-show="(filter === 'all' || filter === '<?= $moduleStatus ?>') 
                                                 && (selectedSubject === 'All' || selectedSubject === '<?= htmlspecialchars($moduleSubject) ?>')"
                                        x-cloak
                                        class="p-3 sm:p-4 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-sm sm:text-base text-gray-900 mb-1 break-words">
                                                    <?= htmlspecialchars($module['title']) ?>
                                                </h4>
                                                <?php if (!empty($module['description'])): ?>
                                                    <p class="text-xs sm:text-sm text-gray-600 break-words">
                                                        <?= htmlspecialchars($module['description']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="text-xs text-purple-700 mt-1 font-medium">
                                                    <i class="fas fa-book-open mr-1"></i><?= htmlspecialchars($moduleSubject) ?>
                                                </p>
                                            </div>
                                            <span class="flex-shrink-0 inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap 
                                                <?= $moduleStatus === 'completed' ? 'bg-emerald-100 text-emerald-700' 
                                                : ($moduleStatus === 'failed' ? 'bg-red-100 text-red-700' 
                                                : 'bg-amber-100 text-amber-700') ?>">
                                                <?= htmlspecialchars(ucfirst($module['status'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-book text-4xl text-gray-300 mb-3"></i>
                                <p class="text-sm sm:text-base text-gray-500">No modules found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quizzes -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-clipboard-list text-purple-500 mr-2"></i>Quizzes <span class="text-xs text-gray-400 ml-2">(Best Scores)</span>
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6 max-h-96 overflow-y-auto">
                        <?php if (!empty($quizzes)): ?>
                            <div class="space-y-3">
                                <?php foreach ($quizzes as $quiz): 
                                    $quizStatus   = strtolower($quiz['status']);  // passed | failed | pending
                                    $quizSubject  = !empty($quiz['subject']) ? $quiz['subject'] : 'General';
                                    $percentage   = $quiz['percentage'];
                                ?>
                                    <div 
                                        x-show="(filter === 'all' || filter === '<?= $quizStatus ?>') 
                                                 && (selectedSubject === 'All' || selectedSubject === '<?= htmlspecialchars($quizSubject) ?>')"
                                        x-cloak
                                        class="p-3 sm:p-4 rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition-all">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-sm sm:text-base text-gray-900 mb-1 break-words">
                                                    <?= htmlspecialchars($quiz['title']) ?>
                                                </h4>
                                                <?php if (!empty($quiz['description'])): ?>
                                                    <p class="text-xs sm:text-sm text-gray-600 break-words">
                                                        <?= htmlspecialchars($quiz['description']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="text-xs text-purple-700 mt-1 font-medium">
                                                    <i class="fas fa-book-medical mr-1"></i><?= htmlspecialchars($quizSubject) ?>
                                                </p>
                                                <?php if (!is_null($percentage)): ?>
                                                    <p class="text-xs text-purple-600 mt-1 font-medium">
                                                        <i class="fas fa-trophy mr-1"></i>
                                                        Best Score: <?= htmlspecialchars($percentage) ?>%
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="flex-shrink-0 inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap 
                                                <?= $quizStatus === 'passed' ? 'bg-green-100 text-green-700' 
                                                : ($quizStatus === 'failed' ? 'bg-red-100 text-red-700' 
                                                : 'bg-amber-100 text-amber-700') ?>">
                                                <?= htmlspecialchars(ucfirst($quizStatus)) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3"></i>
                                <p class="text-sm sm:text-base text-gray-500">No quizzes found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Daily Tip -->
            <?php if ($dailyTip): ?>
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl p-4 sm:p-6 lg:p-8 text-center border-2 border-purple-200 shadow-sm mt-6 sm:mt-8 animate-fade-in-up" style="animation-delay: 0.4s;">
                <div class="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full mb-3 sm:mb-4 shadow-lg">
                    <i class="fas fa-lightbulb text-xl sm:text-2xl text-white"></i>
                </div>
                <h3 class="text-base sm:text-lg font-semibold mb-2 sm:mb-3 text-purple-900">💡 Daily Nursing Tip</h3>
                <p class="text-sm sm:text-base lg:text-lg text-gray-700 italic leading-relaxed max-w-2xl mx-auto break-words">"<?= htmlspecialchars($dailyTip) ?>"</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
let chatbotOpen = false;
let sidebarExpanded = false;
let messageHistory = [];

const API_CONFIG = {
    url: '../config/chatbot_endpoint.php',
    maxTokens: 1024
};

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const overlay = document.getElementById('sidebar-overlay');
    sidebarExpanded = !sidebarExpanded;
    
    if (window.innerWidth < 1025) {
        if (sidebarExpanded) {
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('sidebar-expanded');
            overlay.classList.remove('hidden');
            document.body.classList.add('sidebar-open');
        } else {
            sidebar.classList.remove('sidebar-expanded');
            sidebar.classList.add('sidebar-collapsed');
            overlay.classList.add('hidden');
            document.body.classList.remove('sidebar-open');
        }
    } else {
        sidebar.classList.toggle('sidebar-expanded');
        sidebar.classList.toggle('sidebar-collapsed');
        mainContent.style.marginLeft = sidebarExpanded ? '18rem' : '5rem';
    }
}

function closeSidebar() {
    if (window.innerWidth < 1025 && sidebarExpanded) toggleSidebar();
}

function toggleChatbot() {
    const windowEl = document.getElementById('chatbotWindow');
    const icon = document.getElementById('chatbotIcon');
    const quickActions = document.getElementById('quickActions');
    const chatbotContainer = document.getElementById('chatbotContainer');
    
    if (!windowEl || !icon) return;
    
    chatbotOpen = !chatbotOpen;
    
    if (chatbotOpen) {
        windowEl.classList.remove('hidden');
        icon.classList.remove('fa-robot');
        icon.classList.add('fa-times');
        if (quickActions) quickActions.classList.add('hidden');
        if (chatbotContainer && window.innerWidth <= 640) {
            chatbotContainer.classList.add('chat-open');
        }
    } else {
        windowEl.classList.add('hidden');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-robot');
        if (chatbotContainer) chatbotContainer.classList.remove('chat-open');
    }
}

function handleInputKeydown(event) {
    const textarea = event.target;
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage(event);
    }
}

async function sendMessage(event) {
    if (event && event.preventDefault) event.preventDefault();
    
    const input = document.getElementById('chatInput');
    if (!input) return;
    
    const message = input.value.trim();
    if (!message) return;
    
    addMessage(message, 'user');
    input.value = '';
    input.style.height = 'auto';
    showTypingIndicator(true);
    
    try {
        const response = await callChatAPI(message);
        showTypingIndicator(false);
        addMessage(response, 'bot');
    } catch (error) {
        showTypingIndicator(false);
        addMessage('Sorry, I encountered an error: ' + error.message, 'bot', true);
    }
}

async function callChatAPI(userMessage) {
    const response = await fetch(API_CONFIG.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'chat', message: userMessage })
    });
    if (!response.ok) throw new Error(`API request failed: ${response.status}`);
    const data = await response.json();
    if (data.error) throw new Error(data.error);
    if (data.reply) return data.reply;
    throw new Error('Invalid API response format');
}

function addMessage(text, sender, isError = false) {
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;
    
    const messageDiv = document.createElement('div');
    const timestamp = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    
    if (sender === 'user') {
        messageDiv.className = 'flex items-start space-x-2 justify-end message-slide-in mb-4';
        messageDiv.innerHTML = `
            <div class="flex-1 flex flex-col items-end">
                <div class="bg-primary-600 text-white rounded-2xl rounded-tr-none px-4 py-3 shadow-sm max-w-[85%]">
                    <p class="text-sm break-words">${escapeHtml(text)}</p>
                </div>
                <span class="text-xs text-gray-500 mt-1">${timestamp}</span>
            </div>
            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
        `;
    } else {
        messageDiv.className = 'flex items-start space-x-2 message-slide-in mb-4';
        messageDiv.innerHTML = `
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-primary-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <div class="bg-gray-100 ${isError ? 'border-2 border-red-300' : ''} rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                    <p class="text-gray-800 text-sm break-words whitespace-pre-wrap">${isError ? escapeHtml(text) : formatBotMessage(text)}</p>
                </div>
                <span class="text-xs text-gray-500 mt-1 block">${timestamp}</span>
            </div>
        `;
    }
    
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    messageHistory.push({ role: sender === 'user' ? 'user' : 'assistant', content: text });
}

function showTypingIndicator(show) {
    const indicator = document.getElementById('typingIndicator');
    const messagesContainer = document.getElementById('chatMessages');
    if (!indicator) return;
    
    if (show) {
        indicator.classList.remove('hidden');
        if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;
    } else {
        indicator.classList.add('hidden');
    }
}

function quickQuestion(question) {
    const input = document.getElementById('chatInput');
    if (input) input.value = question;
    if (!chatbotOpen) toggleChatbot();
    setTimeout(() => {
        const form = document.getElementById('chatForm');
        if (form) form.dispatchEvent(new Event('submit'));
    }, 300);
}

async function generateProgressReport() {
    const msg = `Analyze my progress: Total=<?= $totalSteps ?>, Completed=<?= $completedModules ?>, Passed=<?= $passedSteps ?>, Failed=<?= $failedSteps ?>. Give insights and recommendations.`;
    if (!chatbotOpen) toggleChatbot();
    setTimeout(() => {
        document.getElementById('chatInput').value = msg;
        sendMessage(new Event('submit'));
    }, 300);
}

async function generateFlashcards() {
    if (!chatbotOpen) toggleChatbot();
    setTimeout(() => {
        document.getElementById('chatInput').value = 'Create 5 flashcards for my current nursing topics.';
        sendMessage(new Event('submit'));
    }, 300);
}

async function generateStudyTips() {
    if (!chatbotOpen) toggleChatbot();
    setTimeout(() => {
        document.getElementById('chatInput').value = 'Give me 5 personalized study tips based on my nursing journey.';
        sendMessage(new Event('submit'));
    }, 300);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatBotMessage(text) {
    return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
               .replace(/^\d+\.\s+(.+)$/gm, '<div class="ml-2 mb-1">• $1</div>');
}

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('progressChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed Modules', 'Passed Quizzes', 'Failed Quizzes', 'Pending'],
                datasets: [{
                    data: [<?= $completedModules ?>, <?= $passedSteps ?>, <?= $failedSteps ?>, <?= $pendingSteps ?>],
                    backgroundColor: ['#059669', '#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '75%',
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } }
            }
        });
    }

    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    if (window.innerWidth >= 1025) {
        sidebar?.classList.add('sidebar-collapsed');
        if (mainContent) mainContent.style.marginLeft = '5rem';
    } else {
        sidebar?.classList.add('sidebar-collapsed');
        if (mainContent) mainContent.style.marginLeft = '0';
    }
    sidebarExpanded = false;

    setTimeout(() => {
        if (!chatbotOpen) {
            document.getElementById('quickActions')?.classList.remove('hidden');
        }
    }, 3000);
});

let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');

        if (window.innerWidth >= 1024) {
            if (overlay) overlay.classList.add('hidden');
            if (sidebarExpanded && mainContent) {
                mainContent.style.marginLeft = '18rem';
            } else if (mainContent) {
                mainContent.style.marginLeft = '5rem';
            }
        } else {
            if (mainContent) mainContent.style.marginLeft = '0';
            if (!sidebarExpanded && sidebar) {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.classList.remove('sidebar-expanded');
            }
        }
    }, 250);
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && chatbotOpen) {
        toggleChatbot();
    }
});
</script>

</body>
</html>
