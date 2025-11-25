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

// Fetch all sections
$sectionsQuery = "SELECT DISTINCT section FROM users WHERE role = 'student' AND section IS NOT NULL ORDER BY section";
$sections = $conn->query($sectionsQuery)->fetchAll(PDO::FETCH_COLUMN);

// Fetch all students with their progress grouped by section
$studentsQuery = "
    SELECT 
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.profile_pic,
        u.gender,
        COALESCE(u.section, 'Unassigned') as section,
        COUNT(DISTINCT sp.module_id) as modules_completed,
        COUNT(DISTINCT CASE WHEN sp.status = 'Completed' THEN sp.module_id END) as modules_done,
        COUNT(DISTINCT qa.quiz_id) as quizzes_taken,
        COUNT(DISTINCT CASE WHEN qa.status = 'Completed' THEN qa.quiz_id END) as quizzes_passed,
        AVG(CASE WHEN qa.status = 'Completed' THEN CAST(qa.score AS DECIMAL(10,2)) END) as avg_score
    FROM users u
    LEFT JOIN student_progress sp ON u.id = sp.student_id
    LEFT JOIN quiz_attempts qa ON u.id = qa.student_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.firstname, u.lastname, u.email, u.profile_pic, u.gender, u.section
    ORDER BY u.section, u.lastname, u.firstname
";

$allStudents = $conn->query($studentsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Group students by section
$studentsBySection = [];
foreach ($allStudents as $student) {
    $section = $student['section'] ?: 'Unassigned';
    if (!isset($studentsBySection[$section])) {
        $studentsBySection[$section] = [];
    }
    $studentsBySection[$section][] = $student;
}

// Get total modules and quizzes for progress calculation
$totalModules = $conn->query("SELECT COUNT(*) FROM modules WHERE status = 'active'")->fetchColumn();
$totalQuizzes = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();

// If viewing individual student
$selectedStudent = null;
$studentModules = [];
$studentQuizzes = [];

if (isset($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
    
    // Get student info
    $stmt = $conn->prepare("SELECT id, firstname, lastname, email, profile_pic, gender, section FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$studentId]);
    $selectedStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedStudent) {
        // Get module progress
        $moduleQuery = "
            SELECT 
                m.id,
                m.title,
                m.description,
                m.created_at,
                COALESCE(sp.status, 'Not Started') as status,
                sp.started_at,
                sp.completed_at
            FROM modules m
            LEFT JOIN student_progress sp ON m.id = sp.module_id AND sp.student_id = ?
            WHERE m.status = 'active'
            ORDER BY m.created_at DESC
        ";
        $stmt = $conn->prepare($moduleQuery);
        $stmt->execute([$studentId]);
        $studentModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get quiz attempts
        $quizQuery = "
            SELECT 
                q.id,
                q.title,
                q.publish_time,
                q.deadline_time,
                qa.id as attempt_id,
                qa.attempt_number,
                qa.attempted_at,
                qa.score,
                qa.status,
                COUNT(*) OVER (PARTITION BY q.id) as total_attempts
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
            ORDER BY q.publish_time DESC, qa.attempted_at DESC
        ";
        $stmt = $conn->prepare($quizQuery);
        $stmt->execute([$studentId]);
        $studentQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group quizzes by quiz_id to show latest attempt
        $groupedQuizzes = [];
        foreach ($studentQuizzes as $quiz) {
            if (!isset($groupedQuizzes[$quiz['id']])) {
                $groupedQuizzes[$quiz['id']] = [
                    'quiz' => $quiz,
                    'attempts' => []
                ];
            }
            if ($quiz['attempt_id']) {
                $groupedQuizzes[$quiz['id']]['attempts'][] = $quiz;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress - MedAce</title>
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

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .table-row-hover {
            transition: background-color 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: #f0f9ff;
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
                <a href="manage_modules.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-book text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Modules</span>
                </a>
                <a href="manage_quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="student_progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Student Progress</span>
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
                    <?php if ($selectedStudent): ?>
                        <a href="student_progress.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to All Students
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <?php if (!$selectedStudent): ?>
                <!-- All Students View -->
                <div class="gradient-bg rounded-2xl p-6 sm:p-8 mb-8 text-white shadow-lg animate-fade-in-up">
                    <h1 class="text-2xl sm:text-3xl font-bold mb-2">Student Progress Tracking 📊</h1>
                    <p class="text-blue-100 text-sm sm:text-base">Monitor your students' learning journey and performance</p>
                </div>

                <!-- Search Bar and Section Filter -->
                <div class="mb-6 animate-fade-in-up">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative flex-1">
                            <input type="text" id="searchInput" placeholder="Search students by name or email..." 
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                                   onkeyup="filterStudents()">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <select id="sectionFilter" onchange="filterStudents()" 
                                    class="w-full sm:w-64 pl-12 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all appearance-none bg-white">
                                <option value="">All Sections</option>
                                <?php foreach (array_keys($studentsBySection) as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-layer-group absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Students by Section -->
                <?php if (empty($studentsBySection)): ?>
                <div class="text-center py-20">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-user-graduate text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Students Yet</h3>
                    <p class="text-gray-600">Students will appear here once they enroll</p>
                </div>
                <?php else: ?>
                    <?php foreach ($studentsBySection as $section => $students): ?>
                        <div class="section-group mb-8" data-section="<?= htmlspecialchars($section) ?>">
                            <!-- Section Header -->
                            <div class="flex items-center justify-between mb-4 pb-3 border-b-2 border-primary-500">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-users text-primary-600"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($section) ?></h2>
                                        <p class="text-sm text-gray-600"><?= count($students) ?> <?= count($students) === 1 ? 'Student' : 'Students' ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Students Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($students as $student): ?>
                                    <?php
                                        $studentAvatar = getProfilePicture($student, "../");
                                        $moduleProgress = $totalModules > 0 ? round(($student['modules_done'] / $totalModules) * 100) : 0;
                                        $quizProgress = $student['quizzes_taken'] > 0 ? round(($student['quizzes_passed'] / $student['quizzes_taken']) * 100) : 0;
                                        $avgScore = $student['avg_score'] ? round($student['avg_score'], 1) : 0;
                                    ?>
                                    <div class="bg-white border border-gray-200 rounded-xl p-6 card-hover student-card animate-fade-in-up"
                                         data-name="<?= htmlspecialchars(strtolower($student['firstname'] . ' ' . $student['lastname'])) ?>"
                                         data-email="<?= htmlspecialchars(strtolower($student['email'])) ?>"
                                         data-section="<?= htmlspecialchars($section) ?>">
                                        <!-- Student Header -->
                                        <div class="flex items-center space-x-4 mb-4">
                                            <img src="<?= htmlspecialchars($studentAvatar) ?>" alt="Student" 
                                                 class="w-16 h-16 rounded-full object-cover ring-2 ring-gray-200">
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-semibold text-gray-900 truncate">
                                                    <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>
                                                </h3>
                                                <p class="text-sm text-gray-500 truncate"><?= htmlspecialchars($student['email']) ?></p>
                                            </div>
                                        </div>

                                        <!-- Progress Stats -->
                                        <div class="space-y-4 mb-5">
                                            <!-- Modules -->
                                            <div>
                                                <div class="flex items-center justify-between text-sm mb-2">
                                                    <span class="text-gray-600 flex items-center">
                                                        <i class="fas fa-book text-blue-500 mr-2"></i>
                                                        Modules
                                                    </span>
                                                    <span class="font-semibold text-gray-900"><?= $student['modules_done'] ?>/<?= $totalModules ?></span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" 
                                                         style="width: <?= $moduleProgress ?>%"></div>
                                                </div>
                                            </div>

                                            <!-- Quizzes -->
                                            <div>
                                                <div class="flex items-center justify-between text-sm mb-2">
                                                    <span class="text-gray-600 flex items-center">
                                                        <i class="fas fa-clipboard-check text-green-500 mr-2"></i>
                                                        Quizzes Passed
                                                    </span>
                                                    <span class="font-semibold text-gray-900"><?= $student['quizzes_passed'] ?>/<?= $student['quizzes_taken'] ?></span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-500 h-2 rounded-full transition-all duration-500" 
                                                         style="width: <?= $quizProgress ?>%"></div>
                                                </div>
                                            </div>

                                            <!-- Average Score -->
                                            <?php if ($student['quizzes_taken'] > 0): ?>
                                            <div class="flex items-center justify-between text-sm pt-2 border-t border-gray-200">
                                                <span class="text-gray-600 flex items-center">
                                                    <i class="fas fa-star text-amber-500 mr-2"></i>
                                                    Average Score
                                                </span>
                                                <span class="font-bold text-amber-600"><?= $avgScore ?>%</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- View Details Button -->
                                        <a href="?student_id=<?= $student['id'] ?>" 
                                           class="block w-full text-center bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors">
                                            <i class="fas fa-chart-line mr-2"></i>
                                            View Details
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- Individual Student View -->
                <?php
                    $studentAvatar = getProfilePicture($selectedStudent, "../");
                ?>
                
                <!-- Student Header - Compact -->
                <div class="bg-white rounded-xl p-4 mb-6 shadow-sm border border-gray-100 animate-fade-in-up">
                    <div class="flex items-center gap-4">
                        <img src="<?= htmlspecialchars($studentAvatar) ?>" alt="Student" 
                             class="w-16 h-16 rounded-full object-cover ring-2 ring-primary-500">
                        <div class="flex-1 min-w-0">
                            <h1 class="text-xl font-bold text-gray-900 truncate">
                                <?= htmlspecialchars($selectedStudent['firstname'] . ' ' . $selectedStudent['lastname']) ?>
                            </h1>
                            <p class="text-sm text-gray-600 truncate">
                                <i class="fas fa-envelope mr-1"></i>
                                <?= htmlspecialchars($selectedStudent['email']) ?>
                            </p>
                            <?php if (!empty($selectedStudent['section'])): ?>
                            <span class="inline-flex items-center px-2 py-0.5 bg-primary-100 text-primary-700 rounded text-xs font-semibold mt-1">
                                <i class="fas fa-layer-group mr-1"></i>
                                <?= htmlspecialchars($selectedStudent['section']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column: Modules -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-book text-blue-500 mr-2 text-sm"></i>
                                Module Progress
                            </h2>
                        </div>
                        <div class="p-4 overflow-y-auto flex-1" style="max-height: 600px;">
                            <?php if (empty($studentModules)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-book-open text-3xl text-gray-300 mb-2"></i>
                                    <p class="text-gray-500 text-sm">No modules available</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($studentModules as $module): ?>
                                        <?php
                                            $status = $module['status'];
                                            $statusConfig = match(strtolower($status)) {
                                                'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle'],
                                                'in progress' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-spinner'],
                                                default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'icon' => 'fa-clock']
                                            };
                                        ?>
                                        <div class="p-3 border border-gray-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-all">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($module['title']) ?></h3>
                                                    <?php if ($module['started_at'] || $module['completed_at']): ?>
                                                        <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
                                                            <?php if ($module['started_at']): ?>
                                                                <span><i class="fas fa-play mr-1"></i><?= date('M j', strtotime($module['started_at'])) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($module['completed_at']): ?>
                                                                <span><i class="fas fa-check mr-1"></i><?= date('M j', strtotime($module['completed_at'])) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="flex-shrink-0 inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>">
                                                    <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                                    <?= strtolower($status) === 'in progress' ? 'Active' : htmlspecialchars($status) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column: Quizzes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col animate-fade-in-up" style="animation-delay: 0.2s;">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-clipboard-list text-purple-500 mr-2 text-sm"></i>
                                Quiz Performance
                            </h2>
                        </div>

                        <?php if (empty($groupedQuizzes)): ?>
                            <div class="p-4 text-center py-8">
                                <i class="fas fa-clipboard-question text-3xl text-gray-300 mb-2"></i>
                                <p class="text-gray-500 text-sm">No quizzes taken yet</p>
                            </div>
                        <?php else: ?>
                            <!-- Quick Navigation - Compact -->
                            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($groupedQuizzes as $quizId => $data): ?>
                                        <?php 
                                            $quiz = $data['quiz'];
                                            $attempts = $data['attempts'];
                                            $latestAttempt = !empty($attempts) ? $attempts[0] : null;
                                            
                                            if (!$latestAttempt) {
                                                $navBtnClass = 'bg-gray-200 text-gray-700 hover:bg-gray-300';
                                            } elseif (strtolower($latestAttempt['status']) === 'completed') {
                                                $navBtnClass = 'bg-green-100 text-green-700 hover:bg-green-200';
                                            } else {
                                                $navBtnClass = 'bg-red-100 text-red-700 hover:bg-red-200';
                                            }
                                        ?>
                                        <button onclick="scrollToQuiz(<?= $quizId ?>)" 
                                                class="<?= $navBtnClass ?> px-2 py-1 rounded text-xs font-medium transition-all flex items-center gap-1.5"
                                                title="<?= htmlspecialchars($quiz['title']) ?>">
                                            <span class="truncate max-w-[80px]"><?= htmlspecialchars(substr($quiz['title'], 0, 15)) ?><?= strlen($quiz['title']) > 15 ? '...' : '' ?></span>
                                            <?php if ($latestAttempt): ?>
                                                <span class="font-bold"><?= htmlspecialchars($latestAttempt['score']) ?>%</span>
                                            <?php else: ?>
                                                <i class="fas fa-minus-circle text-xs"></i>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quiz Details - Scrollable -->
                            <div class="p-4 overflow-y-auto flex-1" style="max-height: 600px;">
                                <div class="space-y-3">
                                    <?php foreach ($groupedQuizzes as $quizId => $data): ?>
                                        <?php 
                                            $quiz = $data['quiz'];
                                            $attempts = $data['attempts'];
                                            $latestAttempt = !empty($attempts) ? $attempts[0] : null;
                                            $totalAttempts = count($attempts);
                                            $visibleAttempts = array_slice($attempts, 0, 2);
                                            $hiddenAttempts = array_slice($attempts, 2);
                                        ?>
                                        <div id="quiz<?= $quizId ?>" class="border border-gray-200 rounded-lg overflow-hidden scroll-mt-2">
                                            <!-- Quiz Header - Compact -->
                                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="flex-1 min-w-0">
                                                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($quiz['title']) ?></h3>
                                                        <div class="flex items-center gap-2 text-xs text-gray-600 mt-0.5">
                                                            <span><i class="fas fa-calendar mr-1"></i><?= date('M j', strtotime($quiz['publish_time'])) ?></span>
                                                            <?php if ($quiz['deadline_time']): ?>
                                                                <span><i class="fas fa-clock mr-1"></i><?= date('M j', strtotime($quiz['deadline_time'])) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($latestAttempt): ?>
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold
                                                            <?= strtolower($latestAttempt['status']) === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                                            <?= htmlspecialchars($latestAttempt['status']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                                            Not Taken
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Attempts - Compact -->
                                            <?php if (!empty($attempts)): ?>
                                                <div class="p-3">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-xs font-semibold text-gray-700">Attempts</span>
                                                        <span class="text-xs text-gray-500"><?= $totalAttempts ?> total</span>
                                                    </div>
                                                    
                                                    <!-- Visible Attempts -->
                                                    <div class="space-y-1.5">
                                                        <?php foreach ($visibleAttempts as $attempt): ?>
                                                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="flex items-center justify-center w-6 h-6 bg-white rounded-full text-xs font-semibold text-gray-700 border border-gray-200">
                                                                        <?= $attempt['attempt_number'] ?>
                                                                    </span>
                                                                    <div>
                                                                        <p class="text-xs font-medium text-gray-900"><?= htmlspecialchars($attempt['score']) ?>%</p>
                                                                        <p class="text-xs text-gray-500"><?= date('M j, g:i A', strtotime($attempt['attempted_at'])) ?></p>
                                                                    </div>
                                                                </div>
                                                                <span class="text-xs font-semibold <?= strtolower($attempt['status']) === 'completed' ? 'text-green-600' : 'text-red-600' ?>">
                                                                    <?= strtolower($attempt['status']) === 'completed' ? '✓' : '✗' ?>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <!-- Hidden Attempts -->
                                                    <?php if (!empty($hiddenAttempts)): ?>
                                                        <div id="hiddenAttempts<?= $quizId ?>" class="space-y-1.5 mt-1.5" style="display: none;">
                                                            <?php foreach ($hiddenAttempts as $attempt): ?>
                                                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="flex items-center justify-center w-6 h-6 bg-white rounded-full text-xs font-semibold text-gray-700 border border-gray-200">
                                                                            <?= $attempt['attempt_number'] ?>
                                                                        </span>
                                                                        <div>
                                                                            <p class="text-xs font-medium text-gray-900"><?= htmlspecialchars($attempt['score']) ?>%</p>
                                                                            <p class="text-xs text-gray-500"><?= date('M j, g:i A', strtotime($attempt['attempted_at'])) ?></p>
                                                                        </div>
                                                                    </div>
                                                                    <span class="text-xs font-semibold <?= strtolower($attempt['status']) === 'completed' ? 'text-green-600' : 'text-red-600' ?>">
                                                                        <?= strtolower($attempt['status']) === 'completed' ? '✓' : '✗' ?>
                                                                    </span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                        <!-- View More Button - Compact -->
                                                        <button onclick="toggleAttempts(<?= $quizId ?>)" 
                                                                id="viewMoreBtn<?= $quizId ?>"
                                                                class="w-full mt-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-medium transition-colors flex items-center justify-center gap-1.5">
                                                            <span id="viewMoreText<?= $quizId ?>">+<?= count($hiddenAttempts) ?> more</span>
                                                            <i id="viewMoreIcon<?= $quizId ?>" class="fas fa-chevron-down text-xs"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

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

    function filterStudents() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const sectionFilter = document.getElementById('sectionFilter').value.toLowerCase();
        const cards = document.querySelectorAll('.student-card');
        const sectionGroups = document.querySelectorAll('.section-group');
        
        // First, handle section groups visibility
        sectionGroups.forEach(group => {
            const groupSection = group.getAttribute('data-section').toLowerCase();
            if (sectionFilter === '' || groupSection === sectionFilter) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
                return; // Skip checking individual cards in hidden sections
            }
            
            // Check individual cards within visible sections
            let hasVisibleCards = false;
            const cardsInSection = group.querySelectorAll('.student-card');
            
            cardsInSection.forEach(card => {
                const name = card.getAttribute('data-name');
                const email = card.getAttribute('data-email');
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    card.style.display = 'block';
                    hasVisibleCards = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Hide section group if no cards are visible
            if (!hasVisibleCards && searchTerm !== '') {
                group.style.display = 'none';
            }
        });
    }

    function toggleAttempts(quizId) {
        const hiddenSection = document.getElementById('hiddenAttempts' + quizId);
        const viewMoreText = document.getElementById('viewMoreText' + quizId);
        const viewMoreIcon = document.getElementById('viewMoreIcon' + quizId);
        
        if (hiddenSection.style.display === 'none') {
            // Show hidden attempts
            hiddenSection.style.display = 'block';
            viewMoreText.textContent = 'Less';
            viewMoreIcon.classList.remove('fa-chevron-down');
            viewMoreIcon.classList.add('fa-chevron-up');
        } else {
            // Hide attempts
            hiddenSection.style.display = 'none';
            const hiddenCount = hiddenSection.children.length;
            viewMoreText.textContent = '+' + hiddenCount + ' more';
            viewMoreIcon.classList.remove('fa-chevron-up');
            viewMoreIcon.classList.add('fa-chevron-down');
        }
    }

    function scrollToQuiz(quizId) {
        const quizElement = document.getElementById('quiz' + quizId);
        if (quizElement) {
            quizElement.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start'
            });
            
            // Add a brief highlight effect
            quizElement.classList.add('ring-2', 'ring-primary-500', 'ring-offset-2');
            setTimeout(() => {
                quizElement.classList.remove('ring-2', 'ring-primary-500', 'ring-offset-2');
            }, 2000);
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