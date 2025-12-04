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
$completedModules = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'completed'));
$passedSteps = count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'passed'));
$failedSteps = count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'failed'));
$pendingSteps = count(array_filter($modules, fn($s) => strtolower($s['status']) === 'pending')) +
                count(array_filter($quizzes, fn($s) => strtolower($s['status']) === 'pending'));

// For progress calculation
$totalCompleted = $passedSteps + $completedModules;
$totalSteps = max(count($modules) + count($quizzes), 1);
$progressPercent = round(($totalCompleted / $totalSteps) * 100);

// ============================================
// GROUP BY SUBJECT
// ============================================
$subjectData = [];

// Group modules by subject
foreach ($modules as $module) {
    $subjectName = $module['subject_name'] ?? 'Uncategorized';
    $subjectId = $module['subject_id'] ?? 0;
    
    if (!isset($subjectData[$subjectId])) {
        $subjectData[$subjectId] = [
            'id' => $subjectId,
            'name' => $subjectName,
            'modules' => [],
            'quizzes' => [],
            'stats' => [
                'total_modules' => 0,
                'completed_modules' => 0,
                'total_quizzes' => 0,
                'passed_quizzes' => 0,
                'failed_quizzes' => 0,
                'pending' => 0
            ]
        ];
    }
    
    $subjectData[$subjectId]['modules'][] = $module;
    $subjectData[$subjectId]['stats']['total_modules']++;
    
    if (strtolower($module['status']) === 'completed') {
        $subjectData[$subjectId]['stats']['completed_modules']++;
    } else {
        $subjectData[$subjectId]['stats']['pending']++;
    }
}

// Group quizzes by subject
foreach ($quizzes as $quiz) {
    $subjectName = $quiz['subject_name'] ?? 'Uncategorized';
    $subjectId = $quiz['subject_id'] ?? 0;
    
    if (!isset($subjectData[$subjectId])) {
        $subjectData[$subjectId] = [
            'id' => $subjectId,
            'name' => $subjectName,
            'modules' => [],
            'quizzes' => [],
            'stats' => [
                'total_modules' => 0,
                'completed_modules' => 0,
                'total_quizzes' => 0,
                'passed_quizzes' => 0,
                'failed_quizzes' => 0,
                'pending' => 0
            ]
        ];
    }
    
    $subjectData[$subjectId]['quizzes'][] = $quiz;
    $subjectData[$subjectId]['stats']['total_quizzes']++;
    
    $status = strtolower($quiz['status']);
    if ($status === 'passed') {
        $subjectData[$subjectId]['stats']['passed_quizzes']++;
    } elseif ($status === 'failed') {
        $subjectData[$subjectId]['stats']['failed_quizzes']++;
    } else {
        $subjectData[$subjectId]['stats']['pending']++;
    }
}

// Calculate progress percentage for each subject
foreach ($subjectData as &$subject) {
    $totalItems = $subject['stats']['total_modules'] + $subject['stats']['total_quizzes'];
    $completedItems = $subject['stats']['completed_modules'] + $subject['stats']['passed_quizzes'];
    $subject['stats']['progress'] = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
}
unset($subject);

// Sort by name
usort($subjectData, fn($a, $b) => strcmp($a['name'], $b['name']));

// Convert to JSON for JavaScript
$subjectDataJson = json_encode(array_values($subjectData));
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.6s ease-out; }
        .animate-scale-in { animation: scaleIn 0.4s ease-out; }

        .sidebar-transition { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-collapsed { width: 5rem; }
        .sidebar-collapsed .nav-text, .sidebar-collapsed .profile-info { opacity: 0; width: 0; overflow: hidden; transition: opacity 0.2s ease; }
        .sidebar-expanded { width: 18rem; }
        .sidebar-expanded .nav-text, .sidebar-expanded .profile-info { opacity: 1; width: auto; transition: opacity 0.3s ease 0.1s; }

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

        .main-container { width: 100%; max-width: 100%; overflow-x: hidden; }
        .chart-container { position: relative; width: 100%; max-width: 16rem; height: 16rem; margin: 0 auto; }
        @media (max-width: 640px) { .chart-container { max-width: 14rem; height: 14rem; } }

        /* Subject Card Hover */
        .subject-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .subject-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.15);
        }

        /* Progress Ring */
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring__circle {
            transition: stroke-dashoffset 0.5s ease;
        }

        /* Modal Styles */
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* Tab Styles */
        .tab-btn.active {
            color: #0284c7;
            border-bottom-color: #0284c7;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

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

            <div class="px-4 py-3 border-b border-gray-200 hidden lg:block">
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
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 transition-all duration-300 main-container" style="margin-left: 5rem;">
        <!-- Top Bar -->
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

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-full">
            <!-- Overall Progress Chart Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row items-center gap-6 sm:gap-8">
                    <!-- Chart -->
                    <div class="chart-container">
                        <canvas id="progressChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <p class="text-3xl sm:text-4xl font-bold text-primary-600"><?= $progressPercent ?>%</p>
                            <p class="text-xs sm:text-sm text-gray-500">Complete</p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="flex-1 w-full">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3 sm:mb-4">Your Learning Journey</h2>
                        <p class="text-sm sm:text-base text-gray-600 mb-4 sm:mb-6">You've completed <?= $totalCompleted ?> of <?= $totalSteps ?> tasks across <?= count($subjectData) ?> subjects.</p>
                        
                        <!-- Progress Bar -->
                        <div class="relative w-full bg-gray-200 h-2.5 sm:h-3 rounded-full overflow-hidden mb-4 sm:mb-6">
                            <div class="absolute inset-0 bg-gradient-to-r from-primary-600 to-primary-500 transition-all duration-1000" 
                                 style="width: <?= $progressPercent ?>%"></div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                            <div class="text-center p-3 sm:p-4 bg-emerald-50 rounded-lg border border-emerald-200">
                                <p class="text-xl sm:text-2xl font-bold text-emerald-700"><?= $completedModules ?></p>
                                <p class="text-xs text-emerald-600 font-medium">Completed</p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-green-50 rounded-lg border border-green-200">
                                <p class="text-xl sm:text-2xl font-bold text-green-700"><?= $passedSteps ?></p>
                                <p class="text-xs text-green-600 font-medium">Passed</p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-red-50 rounded-lg border border-red-200">
                                <p class="text-xl sm:text-2xl font-bold text-red-700"><?= $failedSteps ?></p>
                                <p class="text-xs text-red-600 font-medium">Failed</p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-amber-50 rounded-lg border border-amber-200">
                                <p class="text-xl sm:text-2xl font-bold text-amber-700"><?= $pendingSteps ?></p>
                                <p class="text-xs text-amber-600 font-medium">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subject Progress Section -->
            <div class="mb-6 sm:mb-8">
                <div class="flex items-center justify-between mb-4 sm:mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900">
                        <i class="fas fa-folder-open text-primary-500 mr-2"></i>
                        Progress by Subject
                    </h2>
                    <span class="text-sm text-gray-500"><?= count($subjectData) ?> subjects</span>
                </div>

                <?php if (!empty($subjectData)): ?>
                <div class="grid gap-4 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($subjectData as $index => $subject): ?>
                    <?php 
                        $progress = $subject['stats']['progress'];
                        $circumference = 2 * 3.14159 * 36;
                        $offset = $circumference - ($progress / 100) * $circumference;
                        
                        // Determine color based on progress
                        if ($progress >= 75) {
                            $ringColor = '#10b981'; // Green
                            $bgColor = 'bg-emerald-50';
                            $borderColor = 'border-emerald-200';
                        } elseif ($progress >= 50) {
                            $ringColor = '#0ea5e9'; // Blue
                            $bgColor = 'bg-blue-50';
                            $borderColor = 'border-blue-200';
                        } elseif ($progress >= 25) {
                            $ringColor = '#f59e0b'; // Amber
                            $bgColor = 'bg-amber-50';
                            $borderColor = 'border-amber-200';
                        } else {
                            $ringColor = '#ef4444'; // Red
                            $bgColor = 'bg-red-50';
                            $borderColor = 'border-red-200';
                        }
                    ?>
                    <div class="subject-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 animate-fade-in-up"
                         style="animation-delay: <?= $index * 0.1 ?>s"
                         onclick="openSubjectModal(<?= $subject['id'] ?>)">
                        <div class="flex items-start gap-4">
                            <!-- Progress Ring -->
                            <div class="relative flex-shrink-0">
                                <svg class="progress-ring w-20 h-20" viewBox="0 0 80 80">
                                    <circle class="text-gray-200" stroke="currentColor" stroke-width="6" fill="transparent" r="36" cx="40" cy="40"/>
                                    <circle class="progress-ring__circle" stroke="<?= $ringColor ?>" stroke-width="6" stroke-linecap="round" fill="transparent" r="36" cx="40" cy="40"
                                            style="stroke-dasharray: <?= $circumference ?>; stroke-dashoffset: <?= $offset ?>"/>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-lg font-bold text-gray-700"><?= $progress ?>%</span>
                                </div>
                            </div>

                            <!-- Subject Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 mb-2 truncate"><?= htmlspecialchars($subject['name']) ?></h3>
                                <div class="space-y-1">
                                    <div class="flex items-center text-xs text-gray-600">
                                        <i class="fas fa-book w-4 text-blue-400"></i>
                                        <span class="ml-2"><?= $subject['stats']['completed_modules'] ?>/<?= $subject['stats']['total_modules'] ?> Modules</span>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-600">
                                        <i class="fas fa-clipboard-check w-4 text-purple-400"></i>
                                        <span class="ml-2"><?= $subject['stats']['passed_quizzes'] ?>/<?= $subject['stats']['total_quizzes'] ?> Quizzes Passed</span>
                                    </div>
                                    <?php if ($subject['stats']['failed_quizzes'] > 0): ?>
                                    <div class="flex items-center text-xs text-red-600">
                                        <i class="fas fa-times-circle w-4"></i>
                                        <span class="ml-2"><?= $subject['stats']['failed_quizzes'] ?> Failed</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Arrow -->
                            <div class="flex-shrink-0 text-gray-400">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i class="fas fa-folder-open text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No subjects found. Start learning to see your progress!</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Daily Tip -->
            <?php if ($dailyTip): ?>
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl p-4 sm:p-6 lg:p-8 text-center border-2 border-purple-200 shadow-sm animate-fade-in-up">
                <div class="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full mb-3 sm:mb-4 shadow-lg">
                    <i class="fas fa-lightbulb text-xl sm:text-2xl text-white"></i>
                </div>
                <h3 class="text-base sm:text-lg font-semibold mb-2 sm:mb-3 text-purple-900">💡 Daily Nursing Tip</h3>
                <p class="text-sm sm:text-base lg:text-lg text-gray-700 italic leading-relaxed max-w-2xl mx-auto">
                    "<?= htmlspecialchars($dailyTip) ?>"
                </p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Subject Detail Modal -->
<div id="subjectModal" class="fixed inset-0 z-[100] hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeSubjectModal()"></div>
    <div class="absolute inset-4 sm:inset-8 lg:inset-16 flex items-center justify-center pointer-events-none">
        <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-full overflow-hidden pointer-events-auto flex flex-col">
            <!-- Modal Header -->
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-primary-500 to-primary-600">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-folder text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 id="modalSubjectName" class="text-lg sm:text-xl font-bold text-white">Subject Name</h3>
                        <p id="modalSubjectProgress" class="text-sm text-white/80">0% Complete</p>
                    </div>
                </div>
                <button onclick="closeSubjectModal()" class="w-10 h-10 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-white text-lg"></i>
                </button>
            </div>

            <!-- Modal Stats Bar -->
            <div id="modalStatsBar" class="px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-200 grid grid-cols-4 gap-2 sm:gap-4">
                <div class="text-center">
                    <p id="statModules" class="text-lg sm:text-xl font-bold text-emerald-600">0</p>
                    <p class="text-xs text-gray-500">Modules</p>
                </div>
                <div class="text-center">
                    <p id="statQuizzes" class="text-lg sm:text-xl font-bold text-purple-600">0</p>
                    <p class="text-xs text-gray-500">Quizzes</p>
                </div>
                <div class="text-center">
                    <p id="statPassed" class="text-lg sm:text-xl font-bold text-green-600">0</p>
                    <p class="text-xs text-gray-500">Passed</p>
                </div>
                <div class="text-center">
                    <p id="statPending" class="text-lg sm:text-xl font-bold text-amber-600">0</p>
                    <p class="text-xs text-gray-500">Pending</p>
                </div>
            </div>

            <!-- Modal Tabs -->
            <div class="px-4 sm:px-6 border-b border-gray-200">
                <div class="flex gap-4 sm:gap-6">
                    <button id="tabModules" onclick="switchTab('modules')" class="tab-btn py-3 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors active">
                        <i class="fas fa-book mr-2"></i>Modules
                    </button>
                    <button id="tabQuizzes" onclick="switchTab('quizzes')" class="tab-btn py-3 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-clipboard-list mr-2"></i>Quizzes
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <!-- Modules Tab -->
                <div id="modulesContent" class="space-y-3">
                    <!-- Populated by JS -->
                </div>

                <!-- Quizzes Tab -->
                <div id="quizzesContent" class="space-y-3 hidden">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include the chatbot component if exists
if (file_exists(__DIR__ . '/../includes/chatbot.php')) {
    include __DIR__ . '/../includes/chatbot.php'; 
}
?>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    // ============================================
    // GLOBAL DATA
    // ============================================
    const subjectData = <?= $subjectDataJson ?>;
    let currentSubject = null;
    let sidebarExpanded = false;

    // ============================================
    // SIDEBAR FUNCTIONS
    // ============================================
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebarExpanded = !sidebarExpanded;
        
        if (window.innerWidth < 1024) {
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
        if (window.innerWidth < 1024 && sidebarExpanded) {
            toggleSidebar();
        }
    }

    // ============================================
    // MODAL FUNCTIONS
    // ============================================
    function openSubjectModal(subjectId) {
        const subject = subjectData.find(s => s.id === subjectId);
        if (!subject) return;

        currentSubject = subject;
        
        // Update header
        document.getElementById('modalSubjectName').textContent = subject.name;
        document.getElementById('modalSubjectProgress').textContent = subject.stats.progress + '% Complete';
        
        // Update stats
        document.getElementById('statModules').textContent = subject.stats.completed_modules + '/' + subject.stats.total_modules;
        document.getElementById('statQuizzes').textContent = subject.stats.total_quizzes;
        document.getElementById('statPassed').textContent = subject.stats.passed_quizzes;
        document.getElementById('statPending').textContent = subject.stats.pending;
        
        // Render modules
        renderModules(subject.modules);
        
        // Render quizzes
        renderQuizzes(subject.quizzes);
        
        // Show modal
        document.getElementById('subjectModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Reset to modules tab
        switchTab('modules');
    }

    function closeSubjectModal() {
        document.getElementById('subjectModal').classList.add('hidden');
        document.body.style.overflow = '';
        currentSubject = null;
    }

    function switchTab(tab) {
        const tabModules = document.getElementById('tabModules');
        const tabQuizzes = document.getElementById('tabQuizzes');
        const modulesContent = document.getElementById('modulesContent');
        const quizzesContent = document.getElementById('quizzesContent');
        
        if (tab === 'modules') {
            tabModules.classList.add('active');
            tabQuizzes.classList.remove('active');
            modulesContent.classList.remove('hidden');
            quizzesContent.classList.add('hidden');
        } else {
            tabQuizzes.classList.add('active');
            tabModules.classList.remove('active');
            quizzesContent.classList.remove('hidden');
            modulesContent.classList.add('hidden');
        }
    }

    function renderModules(modules) {
        const container = document.getElementById('modulesContent');
        
        if (!modules || modules.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-book text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No modules in this subject yet</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = modules.map(module => {
            const status = (module.status || 'pending').toLowerCase();
            const statusConfig = {
                completed: { bg: 'bg-emerald-100', text: 'text-emerald-700', icon: 'fa-check-circle' },
                failed: { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-times-circle' },
                pending: { bg: 'bg-amber-100', text: 'text-amber-700', icon: 'fa-clock' }
            };
            const config = statusConfig[status] || statusConfig.pending;
            
            return `
                <div class="p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50/50 transition-all">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-book text-blue-600"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="font-semibold text-gray-900">${escapeHtml(module.title)}</h4>
                                <span class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${config.bg} ${config.text}">
                                    <i class="fas ${config.icon} mr-1"></i>
                                    ${capitalizeFirst(status)}
                                </span>
                            </div>
                            ${module.description ? `<p class="text-sm text-gray-600 mt-1">${escapeHtml(module.description)}</p>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderQuizzes(quizzes) {
        const container = document.getElementById('quizzesContent');
        
        if (!quizzes || quizzes.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No quizzes in this subject yet</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = quizzes.map(quiz => {
            const status = (quiz.status || 'pending').toLowerCase();
            const statusConfig = {
                passed: { bg: 'bg-green-100', text: 'text-green-700', icon: 'fa-check-circle' },
                failed: { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-times-circle' },
                pending: { bg: 'bg-amber-100', text: 'text-amber-700', icon: 'fa-clock' }
            };
            const config = statusConfig[status] || statusConfig.pending;
            
            // Score display
            let scoreHtml = '';
            if (quiz.score !== undefined && quiz.score !== null && status !== 'pending') {
                const scorePercent = quiz.total_points ? Math.round((quiz.score / quiz.total_points) * 100) : 0;
                scoreHtml = `<span class="text-sm ${status === 'passed' ? 'text-green-600' : 'text-red-600'} font-medium">${quiz.score}/${quiz.total_points || '?'} (${scorePercent}%)</span>`;
            }
            
            return `
                <div class="p-4 rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50/50 transition-all">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clipboard-list text-purple-600"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h4 class="font-semibold text-gray-900">${escapeHtml(quiz.title)}</h4>
                                    ${scoreHtml}
                                </div>
                                <span class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${config.bg} ${config.text}">
                                    <i class="fas ${config.icon} mr-1"></i>
                                    ${capitalizeFirst(status)}
                                </span>
                            </div>
                            ${quiz.description ? `<p class="text-sm text-gray-600 mt-1">${escapeHtml(quiz.description)}</p>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // ============================================
    // CHART INITIALIZATION
    // ============================================
    const ctx = document.getElementById('progressChart');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Passed', 'Failed', 'Pending'],
            datasets: [{
                data: [<?= $completedModules ?>, <?= $passedSteps ?>, <?= $failedSteps ?>, <?= $pendingSteps ?>],
                backgroundColor: ['#059669', '#10b981', '#ef4444', '#f59e0b'],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            cutout: '75%',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    cornerRadius: 8
                }
            }
        }
    });

    // ============================================
    // KEYBOARD & RESIZE HANDLERS
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('subjectModal').classList.contains('hidden')) {
            closeSubjectModal();
        }
    });

    window.addEventListener('resize', function() {
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (window.innerWidth >= 1024) {
            overlay.classList.add('hidden');
            document.body.classList.remove('sidebar-open');
            mainContent.style.marginLeft = sidebarExpanded ? '18rem' : '5rem';
        } else {
            mainContent.style.marginLeft = '0';
        }
    });

    // ============================================
    // CHATBOT TOGGLE (if chatbot exists)
    // ============================================
    function toggleChatbot() {
        const chatWindow = document.getElementById('chatbotWindow');
        const icon = document.getElementById('chatbotIcon');
        
        if (!chatWindow || !icon) return;
        
        const isOpen = !chatWindow.classList.contains('hidden');
        
        if (isOpen) {
            chatWindow.classList.add('hidden');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-robot');
        } else {
            chatWindow.classList.remove('hidden');
            icon.classList.remove('fa-robot');
            icon.classList.add('fa-times');
        }
    }

    console.log('📊 Progress page initialized with', subjectData.length, 'subjects');
</script>

</body>
</html>