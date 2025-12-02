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
$totalModules = $conn->query("SELECT COUNT(*) FROM modules WHERE status IN ('active', 'published')")->fetchColumn();
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
            WHERE m.status IN ('active', 'published')
            ORDER BY m.created_at DESC
        ";
        $stmt = $conn->prepare($moduleQuery);
        $stmt->execute([$studentId]);
        $studentModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get quiz attempts with calculated total score from questions
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
                qa.total_score,
                qa.status,
                (SELECT COALESCE(SUM(points), 0) FROM questions WHERE quiz_id = q.id) as quiz_total_points,
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

        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                        width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Sidebar Toggle Button */
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

        /* Main container responsiveness */
        .main-container {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Modal Styles */
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

        .modal-content {
            background-color: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
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
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

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
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                        <p class="text-xs text-gray-500">Professor</p>
                    </div>
                </div>
            </div>

            <!-- Toggle Button -->
            <div class="px-4 py-3 border-b border-gray-200">
                <button onclick="toggleSidebar()" class="sidebar-toggle-btn w-full" id="sidebarToggleBtn" aria-label="Toggle sidebar">
                    <div class="toggle-icon"></div>
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
    <main id="main-content" class="flex-1 transition-all duration-300 main-container">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex-1"></div>
                <div class="flex items-center space-x-4">
                    <?php if ($selectedStudent): ?>
                        <a href="student_progress.php" class="text-xs sm:text-sm text-primary-600 hover:text-primary-700 font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>
                            <span class="hidden sm:inline">Back to All Students</span>
                            <span class="sm:hidden">Back</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <?php if (!$selectedStudent): ?>
                <!-- All Students View -->
                <div class="gradient-bg rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 mb-6 sm:mb-8 text-white shadow-lg animate-fade-in-up">
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-2">Student Progress Tracking 📊</h1>
                    <p class="text-blue-100 text-sm sm:text-base">Monitor your students' learning journey and performance</p>
                </div>

                <!-- Search Bar and Section Filter -->
                <div class="mb-6 animate-fade-in-up">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative flex-1">
                            <input type="text" id="searchInput" placeholder="Search students by name or email..." 
                                   class="w-full pl-10 sm:pl-12 pr-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                                   onkeyup="filterStudents()">
                            <i class="fas fa-search absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm sm:text-base"></i>
                        </div>
                        <div class="relative">
                            <select id="sectionFilter" onchange="filterStudents()" 
                                    class="w-full sm:w-64 pl-10 sm:pl-12 pr-10 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all appearance-none bg-white text-sm sm:text-base">
                                <option value="">All Sections</option>
                                <?php foreach (array_keys($studentsBySection) as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-layer-group absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm sm:text-base"></i>
                            <i class="fas fa-chevron-down absolute right-3 sm:right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Students by Section -->
                <?php if (empty($studentsBySection)): ?>
                <div class="text-center py-12 sm:py-20">
                    <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-user-graduate text-3xl sm:text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">No Students Yet</h3>
                    <p class="text-sm sm:text-base text-gray-600">Students will appear here once they enroll</p>
                </div>
                <?php else: ?>
                    <?php foreach ($studentsBySection as $section => $students): ?>
                        <div class="section-group mb-6 sm:mb-8" data-section="<?= htmlspecialchars($section) ?>">
                            <!-- Section Header -->
                            <div class="flex items-center justify-between mb-4 pb-3 border-b-2 border-primary-500">
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-users text-primary-600 text-sm sm:text-base"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg sm:text-xl font-bold text-gray-900"><?= htmlspecialchars($section) ?></h2>
                                        <p class="text-xs sm:text-sm text-gray-600"><?= count($students) ?> <?= count($students) === 1 ? 'Student' : 'Students' ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Students Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                                <?php foreach ($students as $student): ?>
                                    <?php
                                        $studentAvatar = getProfilePicture($student, "../");
                                        $moduleProgress = $totalModules > 0 ? round(($student['modules_done'] / $totalModules) * 100) : 0;
                                        $quizProgress = $totalQuizzes > 0 ? round(($student['quizzes_passed'] / $totalQuizzes) * 100) : 0;
                                        $avgScore = $student['avg_score'] ? round($student['avg_score'], 1) : 0;
                                    ?>
                                    <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 card-hover student-card animate-fade-in-up"
                                         data-name="<?= htmlspecialchars(strtolower($student['firstname'] . ' ' . $student['lastname'])) ?>"
                                         data-email="<?= htmlspecialchars(strtolower($student['email'])) ?>"
                                         data-section="<?= htmlspecialchars($section) ?>">
                                        <!-- Student Header -->
                                        <div class="flex items-center space-x-3 sm:space-x-4 mb-4">
                                            <img src="<?= htmlspecialchars($studentAvatar) ?>" alt="Student" 
                                                 class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover ring-2 ring-gray-200 flex-shrink-0">
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-semibold text-gray-900 truncate text-sm sm:text-base">
                                                    <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>
                                                </h3>
                                                <p class="text-xs sm:text-sm text-gray-500 truncate"><?= htmlspecialchars($student['email']) ?></p>
                                            </div>
                                        </div>

                                        <!-- Progress Stats -->
                                        <div class="space-y-3 sm:space-y-4 mb-4 sm:mb-5">
                                            <!-- Modules -->
                                            <div>
                                                <div class="flex items-center justify-between text-xs sm:text-sm mb-2">
                                                    <span class="text-gray-600 flex items-center">
                                                        <i class="fas fa-book text-blue-500 mr-1.5 sm:mr-2 text-xs"></i>
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
                                                <div class="flex items-center justify-between text-xs sm:text-sm mb-2">
                                                    <span class="text-gray-600 flex items-center">
                                                        <i class="fas fa-clipboard-check text-green-500 mr-1.5 sm:mr-2 text-xs"></i>
                                                        Quizzes Passed
                                                    </span>
                                                    <span class="font-semibold text-gray-900"><?= $student['quizzes_passed'] ?>/<?= $totalQuizzes ?></span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-500 h-2 rounded-full transition-all duration-500" 
                                                         style="width: <?= $quizProgress ?>%"></div>
                                                </div>
                                            </div>

                                            <!-- Average Score -->
                                            <?php if ($student['quizzes_taken'] > 0): ?>
                                            <div class="flex items-center justify-between text-xs sm:text-sm pt-2 border-t border-gray-200">
                                                <span class="text-gray-600 flex items-center">
                                                    <i class="fas fa-star text-amber-500 mr-1.5 sm:mr-2 text-xs"></i>
                                                    Average Score
                                                </span>
                                                <span class="font-bold text-amber-600"><?= $avgScore ?>%</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- View Details Button -->
                                        <a href="?student_id=<?= $student['id'] ?>" 
                                           class="block w-full text-center bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 sm:py-2.5 rounded-lg font-semibold transition-colors text-xs sm:text-sm">
                                            <i class="fas fa-chart-line mr-1 sm:mr-2"></i>
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
                    <div class="flex items-center gap-3 sm:gap-4">
                        <img src="<?= htmlspecialchars($studentAvatar) ?>" alt="Student" 
                             class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover ring-2 ring-primary-500 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <h1 class="text-lg sm:text-xl font-bold text-gray-900 truncate">
                                <?= htmlspecialchars($selectedStudent['firstname'] . ' ' . $selectedStudent['lastname']) ?>
                            </h1>
                            <p class="text-xs sm:text-sm text-gray-600 truncate">
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
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                    <!-- Left Column: Modules -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-book text-blue-500 mr-2 text-sm"></i>
                                Module Progress
                            </h2>
                        </div>
                        <div class="p-4 overflow-y-auto flex-1" style="max-height: 600px;">
                            <?php if (empty($studentModules)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-book-open text-2xl sm:text-3xl text-gray-300 mb-2"></i>
                                    <p class="text-gray-500 text-xs sm:text-sm">No modules available</p>
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
                                                    <h3 class="font-semibold text-gray-900 text-xs sm:text-sm truncate"><?= htmlspecialchars($module['title']) ?></h3>
                                                    <?php if ($module['started_at'] || $module['completed_at']): ?>
                                                        <div class="flex items-center gap-2 sm:gap-3 text-xs text-gray-500 mt-1">
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
                                                    <span class="hidden sm:inline"><?= strtolower($status) === 'in progress' ? 'Active' : htmlspecialchars($status) ?></span>
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
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-clipboard-list text-purple-500 mr-2 text-sm"></i>
                                Quiz Performance
                            </h2>
                        </div>

                        <?php if (empty($groupedQuizzes)): ?>
                            <div class="p-4 text-center py-8">
                                <i class="fas fa-clipboard-question text-2xl sm:text-3xl text-gray-300 mb-2"></i>
                                <p class="text-gray-500 text-xs sm:text-sm">No quizzes taken yet</p>
                            </div>
                        <?php else: ?>
                            <!-- Quick Navigation - Compact -->
                            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 overflow-x-auto">
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($groupedQuizzes as $quizId => $data): ?>
                                        <?php 
                                            $quiz = $data['quiz'];
                                            $attempts = $data['attempts'];
                                            $latestAttempt = !empty($attempts) ? $attempts[0] : null;
                                            
                                            if (!$latestAttempt) {
                                                $navBtnClass = 'bg-gray-200 text-gray-700 hover:bg-gray-300';
                                                $displayScore = '';
                                            } else {
                                                $scoreEarned = floatval($latestAttempt['score']);
                                                $totalScore = floatval($latestAttempt['quiz_total_points']); // Use calculated total
                                                $percentage = $totalScore > 0 ? round(($scoreEarned / $totalScore) * 100, 1) : 0;
                                                $displayScore = $percentage;
                                                
                                                if (strtolower($latestAttempt['status']) === 'completed') {
                                                    $navBtnClass = 'bg-green-100 text-green-700 hover:bg-green-200';
                                                } else {
                                                    $navBtnClass = 'bg-red-100 text-red-700 hover:bg-red-200';
                                                }
                                            }
                                        ?>
                                        <button onclick="scrollToQuiz(<?= $quizId ?>)" 
                                                class="<?= $navBtnClass ?> px-2 py-1 rounded text-xs font-medium transition-all flex items-center gap-1.5 whitespace-nowrap"
                                                title="<?= htmlspecialchars($quiz['title']) ?>">
                                            <span class="truncate max-w-[60px] sm:max-w-[80px]"><?= htmlspecialchars(substr($quiz['title'], 0, 15)) ?><?= strlen($quiz['title']) > 15 ? '...' : '' ?></span>
                                            <?php if ($latestAttempt): ?>
                                                <span class="font-bold"><?= $displayScore ?>%</span>
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
                                        <div id="quiz<?= $quizId ?>" class="border border-gray-200 rounded-lg overflow-hidden scroll-mt-2"
                                             data-quiz-id="<?= $quizId ?>"
                                             data-attempts='<?= json_encode(array_map(function($a) {
                                                 return [
                                                     'attempt_number' => $a['attempt_number'],
                                                     'score' => $a['score'],
                                                     'total_score' => $a['quiz_total_points'], // Use calculated total from questions
                                                     'attempted_at' => $a['attempted_at'],
                                                     'status' => $a['status']
                                                 ];
                                             }, $attempts)) ?>'>
                                            <!-- Quiz Header - Compact -->
                                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="flex-1 min-w-0">
                                                        <h3 class="font-semibold text-gray-900 text-xs sm:text-sm truncate"><?= htmlspecialchars($quiz['title']) ?></h3>
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
                                                            <span class="hidden sm:inline"><?= htmlspecialchars($latestAttempt['status']) ?></span>
                                                            <span class="sm:hidden"><?= strtolower($latestAttempt['status']) === 'completed' ? '✓' : '✗' ?></span>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                                            <span class="hidden sm:inline">Not Taken</span>
                                                            <span class="sm:hidden">-</span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Attempts - Compact -->
                                            <?php if (!empty($attempts)): ?>
                                                <div class="p-3">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-xs font-semibold text-gray-700">Attempts</span>
                                                        <button onclick="showPerformanceModal(<?= $quizId ?>, '<?= htmlspecialchars(addslashes($quiz['title'])) ?>')" 
                                                                class="text-xs text-primary-600 hover:text-primary-700 font-semibold flex items-center gap-1">
                                                            <i class="fas fa-chart-line"></i>
                                                            <span class="hidden sm:inline">Performance</span>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Visible Attempts -->
                                                    <div class="space-y-1.5">
                                                        <?php foreach ($visibleAttempts as $attempt): ?>
                                                            <?php 
                                                                $scoreEarned = floatval($attempt['score']);
                                                                $totalScore = floatval($attempt['quiz_total_points']); // Use calculated total
                                                                $percentage = $totalScore > 0 ? round(($scoreEarned / $totalScore) * 100, 1) : 0;
                                                            ?>
                                                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="flex items-center justify-center w-6 h-6 bg-white rounded-full text-xs font-semibold text-gray-700 border border-gray-200">
                                                                        <?= $attempt['attempt_number'] ?>
                                                                    </span>
                                                                    <div>
                                                                        <p class="text-xs font-medium text-gray-900"><?= $percentage ?>% <span class="text-gray-500 font-normal">(<?= $scoreEarned ?>/<?= $totalScore ?>)</span></p>
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
                                                                <?php 
                                                                    $scoreEarned = floatval($attempt['score']);
                                                                    $totalScore = floatval($attempt['quiz_total_points']); // Use calculated total
                                                                    $percentage = $totalScore > 0 ? round(($scoreEarned / $totalScore) * 100, 1) : 0;
                                                                ?>
                                                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="flex items-center justify-center w-6 h-6 bg-white rounded-full text-xs font-semibold text-gray-700 border border-gray-200">
                                                                            <?= $attempt['attempt_number'] ?>
                                                                        </span>
                                                                        <div>
                                                                            <p class="text-xs font-medium text-gray-900"><?= $percentage ?>% <span class="text-gray-500 font-normal">(<?= $scoreEarned ?>/<?= $totalScore ?>)</span></p>
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

<!-- Performance Modal -->
<div id="performanceModal" class="modal">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="gradient-bg px-4 sm:px-6 py-4 sm:py-5 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-chart-line mr-2 sm:mr-3"></i>
                    <span id="modalQuizTitle">Quiz Performance</span>
                </h2>
                <button onclick="closePerformanceModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="p-4 sm:p-6">
            <!-- Performance Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
                    <div class="flex items-center justify-between mb-2">
                        <i class="fas fa-clipboard-list text-blue-500 text-lg sm:text-xl"></i>
                    </div>
                    <p class="text-xs sm:text-sm text-blue-600 mb-1">Total Attempts</p>
                    <p class="text-xl sm:text-2xl font-bold text-blue-700" id="totalAttempts">0</p>
                </div>

                <div class="bg-green-50 rounded-lg p-3 sm:p-4">
                    <div class="flex items-center justify-between mb-2">
                        <i class="fas fa-trophy text-green-500 text-lg sm:text-xl"></i>
                    </div>
                    <p class="text-xs sm:text-sm text-green-600 mb-1">Best Score</p>
                    <p class="text-xl sm:text-2xl font-bold text-green-700" id="bestScore">0%</p>
                </div>

                <div class="bg-purple-50 rounded-lg p-3 sm:p-4">
                    <div class="flex items-center justify-between mb-2">
                        <i class="fas fa-chart-bar text-purple-500 text-lg sm:text-xl"></i>
                    </div>
                    <p class="text-xs sm:text-sm text-purple-600 mb-1">Average</p>
                    <p class="text-xl sm:text-2xl font-bold text-purple-700" id="avgScore">0%</p>
                </div>

                <div class="bg-amber-50 rounded-lg p-3 sm:p-4">
                    <div class="flex items-center justify-between mb-2">
                        <i class="fas fa-clock text-amber-500 text-lg sm:text-xl"></i>
                    </div>
                    <p class="text-xs sm:text-sm text-amber-600 mb-1">Last Attempt</p>
                    <p class="text-xl sm:text-2xl font-bold text-amber-700" id="lastScore">0%</p>
                </div>
            </div>

            <!-- Performance Chart -->
            <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 mb-4">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-line-chart text-primary-500 mr-2"></i>
                    Performance Trend
                </h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>

            <!-- Attempts Details Table -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list text-primary-500 mr-2"></i>
                        All Attempts
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Attempt</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Score</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody id="attemptsTableBody" class="divide-y divide-gray-200">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let sidebarExpanded = false;

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        
        sidebarExpanded = !sidebarExpanded;
        
        // Toggle button active state
        toggleBtn.classList.toggle('active');
        
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
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            
            if (window.innerWidth >= 1025) {
                // Desktop mode - reset mobile states
                overlay.classList.add('hidden');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                
                // Apply correct desktop state
                if (!sidebarExpanded) {
                    sidebar.classList.remove('sidebar-expanded');
                    mainContent.classList.remove('content-expanded');
                    toggleBtn.classList.remove('active');
                } else {
                    sidebar.classList.add('sidebar-expanded');
                    mainContent.classList.add('content-expanded');
                    toggleBtn.classList.add('active');
                }
            } else {
                // Mobile mode - reset desktop states
                mainContent.classList.remove('content-expanded');
                
                if (!sidebarExpanded) {
                    sidebar.classList.remove('sidebar-expanded');
                    overlay.classList.add('hidden');
                    overlay.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                    toggleBtn.classList.remove('active');
                }
            }
        }, 250);
    });

    // Initialize correct state on page load
    window.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        
        if (window.innerWidth >= 1025) {
            // Desktop: start collapsed
            sidebar.classList.remove('sidebar-expanded');
            mainContent.classList.remove('content-expanded');
            toggleBtn.classList.remove('active');
            sidebarExpanded = false;
        } else {
            // Mobile: ensure sidebar is hidden
            sidebar.classList.remove('sidebar-expanded');
            toggleBtn.classList.remove('active');
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
        // Close performance modal on escape
        if (e.key === 'Escape') {
            closePerformanceModal();
        }
    });

    // Performance Modal Functions
    let performanceChart = null;

    function showPerformanceModal(quizId, quizTitle) {
        const modal = document.getElementById('performanceModal');
        const quizElement = document.getElementById('quiz' + quizId);
        const attemptsData = JSON.parse(quizElement.getAttribute('data-attempts'));
        
        // Set modal title
        document.getElementById('modalQuizTitle').textContent = quizTitle;
        
        // Calculate percentages for each attempt
        const percentages = attemptsData.map(a => {
            const score = parseFloat(a.score);
            const totalScore = parseFloat(a.total_score);
            return totalScore > 0 ? Math.round((score / totalScore) * 100 * 10) / 10 : 0;
        });
        
        // Calculate statistics
        const totalAttempts = percentages.length;
        const bestScore = Math.max(...percentages);
        const avgScore = (percentages.reduce((a, b) => a + b, 0) / totalAttempts).toFixed(1);
        const lastScore = percentages[0]; // First element is the latest
        
        // Update summary cards
        document.getElementById('totalAttempts').textContent = totalAttempts;
        document.getElementById('bestScore').textContent = bestScore + '%';
        document.getElementById('avgScore').textContent = avgScore + '%';
        document.getElementById('lastScore').textContent = lastScore + '%';
        
        // Populate attempts table
        const tableBody = document.getElementById('attemptsTableBody');
        tableBody.innerHTML = '';
        
        attemptsData.forEach((attempt, index) => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors';
            
            const scoreEarned = parseFloat(attempt.score);
            const totalScore = parseFloat(attempt.total_score);
            const percentage = totalScore > 0 ? Math.round((scoreEarned / totalScore) * 100 * 10) / 10 : 0;
            
            const statusClass = attempt.status.toLowerCase() === 'completed' 
                ? 'bg-green-100 text-green-700' 
                : 'bg-red-100 text-red-700';
            
            const scoreClass = percentage >= 70 
                ? 'text-green-600 font-bold' 
                : 'text-red-600 font-bold';
            
            row.innerHTML = `
                <td class="px-4 py-3 text-sm">
                    <span class="inline-flex items-center justify-center w-7 h-7 bg-primary-100 text-primary-700 rounded-full font-semibold text-xs">
                        ${attempt.attempt_number}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm ${scoreClass}">
                    ${percentage}% <span class="text-gray-500 font-normal text-xs">(${scoreEarned}/${totalScore})</span>
                </td>
                <td class="px-4 py-3 text-sm">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${statusClass}">
                        ${attempt.status}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">${formatDateTime(attempt.attempted_at)}</td>
            `;
            
            tableBody.appendChild(row);
        });
        
        // Create performance chart with percentages
        createPerformanceChart(attemptsData);
        
        // Show modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closePerformanceModal() {
        const modal = document.getElementById('performanceModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        
        // Destroy chart to prevent memory leaks
        if (performanceChart) {
            performanceChart.destroy();
            performanceChart = null;
        }
    }

    function createPerformanceChart(attemptsData) {
        // Destroy existing chart if it exists
        if (performanceChart) {
            performanceChart.destroy();
        }
        
        const ctx = document.getElementById('performanceChart').getContext('2d');
        
        // Reverse data to show chronological order (oldest to newest)
        const reversedData = [...attemptsData].reverse();
        
        const labels = reversedData.map(a => 'Attempt ' + a.attempt_number);
        
        // Calculate percentages from score and total_score
        const scores = reversedData.map(a => {
            const scoreEarned = parseFloat(a.score);
            const totalScore = parseFloat(a.total_score);
            return totalScore > 0 ? Math.round((scoreEarned / totalScore) * 100 * 10) / 10 : 0;
        });
        
        // Calculate trend line
        const avgScore = scores.reduce((a, b) => a + b, 0) / scores.length;
        
        performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Score',
                        data: scores,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#0ea5e9',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#0284c7',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Average',
                        data: Array(scores.length).fill(avgScore),
                        borderColor: '#8b5cf6',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0
                    },
                    {
                        label: 'Passing Line (70%)',
                        data: Array(scores.length).fill(70),
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderDash: [10, 5],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
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
                        },
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Score: ' + context.parsed.y + '%';
                                } else if (context.datasetIndex === 1) {
                                    return 'Average: ' + context.parsed.y.toFixed(1) + '%';
                                } else {
                                    return 'Passing: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            },
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        return date.toLocaleDateString('en-US', options);
    }

    // Close modal when clicking outside
    document.getElementById('performanceModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closePerformanceModal();
        }
    });
</script>

</body>
</html>