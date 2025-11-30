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
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// ✅ Get quizzes with latest attempt and highest score
$stmt = $conn->prepare("
  SELECT q.id, q.title, q.publish_time, q.deadline_time, q.subject,
         qa.id AS attempt_id, 
         COALESCE(qa.status, 'Pending') AS status,
         qa.score AS latest_score_raw,
         (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) AS highest_score_raw,
         (SELECT COALESCE(SUM(points), 0) FROM questions WHERE quiz_id = q.id) AS total_points,
         (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) AS attempt_count
  FROM quizzes q
  LEFT JOIN (
      SELECT qa1.*
      FROM quiz_attempts qa1
      INNER JOIN (
          SELECT quiz_id, MAX(attempted_at) AS latest_attempt
          FROM quiz_attempts
          WHERE student_id = ?
          GROUP BY quiz_id
      ) latest ON qa1.quiz_id = latest.quiz_id AND qa1.attempted_at = latest.latest_attempt
  ) qa ON q.id = qa.quiz_id
  ORDER BY q.publish_time DESC
");
$stmt->execute([$studentId, $studentId, $studentId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to calculate percentage
function calculatePercentage($score, $total) {
    if ($total == 0) return 0;
    return round(($score / $total) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - MedAce</title>
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

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        /* Improved sidebar transitions */
        aside {
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), 
                        width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Mobile sidebar */
        @media (max-width: 1024px) {
            .sidebar-collapsed {
                width: 18rem;
                transform: translateX(-100%);
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }
            
            /* Ensure main content takes full width on mobile */
            #main-content {
                margin-left: 0 !important;
            }
        }

        /* Desktop sidebar behavior */
        @media (min-width: 1025px) {
            .sidebar-collapsed {
                width: 5rem;
                transform: translateX(0);
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }
        }

        /* Responsive quiz grid */
        @media (max-width: 640px) {
            .quiz-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            .quiz-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1025px) and (max-width: 1280px) {
            .quiz-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (min-width: 1281px) {
            .quiz-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ filter: 'all', search: '', openModal: null }">

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
                <a href="progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">My Progress</span>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-primary-600 w-5 text-center flex-shrink-0"></i>
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
                    <h1 class="text-xl font-bold text-gray-900">Available Quizzes</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Search and Filters -->
            <div class="flex flex-col sm:flex-row gap-4 mb-6 animate-fade-in-up">
                <div class="relative flex-1">
                    <input type="text" x-model="search" placeholder="Search quizzes..." 
                           class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                
                <!-- Status Filters -->
                <div class="flex gap-2 flex-wrap">
                    <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-3 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap">
                        All
                    </button>
                    <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-3 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap">
                        Pending
                    </button>
                    <button @click="filter = 'failed'" :class="filter === 'failed' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-3 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap">
                        Failed
                    </button>
                    <button @click="filter = 'completed'" :class="filter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-3 rounded-lg font-medium transition-all shadow-sm whitespace-nowrap">
                        Completed
                    </button>
                </div>
            </div>

            <!-- Subject Filter Section (Future Feature - Uncomment when subject column is added) -->
            <!-- 
            <div class="mb-6 animate-fade-in-up" x-data="{ subjectFilter: 'all' }">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Filter by Subject:</h3>
                <div class="flex gap-2 flex-wrap">
                    <button @click="subjectFilter = 'all'" :class="subjectFilter === 'all' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm text-sm">
                        <i class="fas fa-th mr-1"></i>
                        All Subjects
                    </button>
                    <button @click="subjectFilter = 'anatomy'" :class="subjectFilter === 'anatomy' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm text-sm">
                        <i class="fas fa-user-md mr-1"></i>
                        Anatomy
                    </button>
                    <button @click="subjectFilter = 'physiology'" :class="subjectFilter === 'physiology' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm text-sm">
                        <i class="fas fa-heartbeat mr-1"></i>
                        Physiology
                    </button>
                    <button @click="subjectFilter = 'pharmacology'" :class="subjectFilter === 'pharmacology' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm text-sm">
                        <i class="fas fa-pills mr-1"></i>
                        Pharmacology
                    </button>
                    <button @click="subjectFilter = 'nursing'" :class="subjectFilter === 'nursing' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'" 
                            class="px-4 py-2 rounded-lg font-medium transition-all shadow-sm text-sm">
                        <i class="fas fa-stethoscope mr-1"></i>
                        Nursing Care
                    </button>
                </div>
            </div>
            -->

            <!-- Quiz Cards -->
            <?php if (empty($quizzes)): ?>
            <div class="text-center py-20 animate-fade-in-up">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-clipboard-list text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No quizzes yet</h3>
                <p class="text-gray-600">Check back later for new quizzes</p>
            </div>
            <?php else: ?>
            <div class="quiz-grid grid gap-6">
                <?php foreach ($quizzes as $quiz): 
                    $status = strtolower($quiz['status']);
                    $statusConfig = match($status) {
                        'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle'],
                        'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => 'fa-times-circle'],
                        'pending' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'icon' => 'fa-clock'],
                        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'icon' => 'fa-info-circle']
                    };
                    
                    // Calculate percentages based on total points
                    $latestPercentage = calculatePercentage($quiz['latest_score_raw'], $quiz['total_points']);
                    $highestPercentage = calculatePercentage($quiz['highest_score_raw'], $quiz['total_points']);
                    
                    // Determine if quiz can be retaken (failed or already completed but want to improve)
                    $canRetake = ($status === 'failed' || ($status === 'completed' && $quiz['attempt_count'] > 0));
                ?>
                <div x-show="(filter === 'all' || filter === '<?= $status ?>') && ('<?= strtolower(htmlspecialchars($quiz['title'])) ?>'.includes(search.toLowerCase()))"
                     class="bg-white border border-gray-200 rounded-xl overflow-hidden card-hover animate-fade-in-up">
                    <!-- Header -->
                    <div class="h-32 bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center relative">
                        <i class="fas fa-clipboard-question text-5xl text-white opacity-90"></i>
                    </div>

                    <!-- Content -->
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="font-semibold text-gray-900 text-lg flex-1 pr-2 line-clamp-2">
                                <?= htmlspecialchars($quiz['title']) ?>
                            </h3>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?> flex-shrink-0">
                                <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                <?= ucfirst($status) ?>
                            </span>
                        </div>
                        
                        <!-- Subject Badge -->
                        <?php 
                        // Use actual subject from database
                        $subjectDisplay = !empty($quiz['subject']) ? $quiz['subject'] : 'General';
                        
                        $subjectColors = [
                            'Anatomy' => 'bg-pink-100 text-pink-700',
                            'Physiology' => 'bg-green-100 text-green-700',
                            'Pharmacology' => 'bg-purple-100 text-purple-700',
                            'Nursing Care' => 'bg-blue-100 text-blue-700',
                            'Community Health Nursing II' => 'bg-teal-100 text-teal-700',
                            'General' => 'bg-gray-100 text-gray-700'
                        ];
                        $subjectColor = $subjectColors[$subjectDisplay] ?? 'bg-indigo-100 text-indigo-700';
                        ?>
                        <div class="mb-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium <?= $subjectColor ?>">
                                <i class="fas fa-book-medical mr-1"></i>
                                <?= htmlspecialchars($subjectDisplay) ?>
                            </span>
                        </div>

                        <?php if ($quiz['publish_time']): ?>
                        <p class="text-xs text-gray-500 mb-1">
                            <i class="fas fa-calendar mr-1"></i>
                            <?= date('M d, Y', strtotime($quiz['publish_time'])) ?>
                        </p>
                        <?php endif; ?>

                        <?php if ($quiz['deadline_time']): ?>
                        <p class="text-xs text-red-600 mb-3">
                            <i class="fas fa-clock mr-1"></i>
                            Due: <?= date('M d, Y g:i A', strtotime($quiz['deadline_time'])) ?>
                        </p>
                        <?php endif; ?>

                        <!-- Score Display for Completed/Failed -->
                        <?php if ($status !== 'pending' && $quiz['total_points'] > 0): ?>
                        <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-3 mb-4 border border-blue-100">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <p class="text-xs text-gray-600 mb-1">Latest Score</p>
                                    <p class="text-lg font-bold text-gray-900"><?= $latestPercentage ?>%</p>
                                    <p class="text-xs text-gray-500"><?= $quiz['latest_score_raw'] ?> / <?= $quiz['total_points'] ?> pts</p>
                                </div>
                                <div class="h-12 w-px bg-gray-300 mx-2"></div>
                                <div class="flex-1 text-right">
                                    <p class="text-xs text-gray-600 mb-1">Highest Score</p>
                                    <p class="text-lg font-bold text-green-600">
                                        <i class="fas fa-trophy text-amber-500 mr-1"></i>
                                        <?= $highestPercentage ?>%
                                    </p>
                                    <p class="text-xs text-gray-500"><?= $quiz['highest_score_raw'] ?> / <?= $quiz['total_points'] ?> pts</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="flex gap-2 mt-4">
                            <?php if ($status === 'pending'): ?>
                                <!-- Pending quiz - Show Start button -->
                                <a href="take_quiz.php?id=<?= $quiz['id'] ?>"
                                   class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors text-center text-sm">
                                    <i class="fas fa-play mr-1"></i>
                                    Start Quiz
                                </a>
                            <?php elseif ($status === 'failed'): ?>
                                <!-- Failed quiz - Show View Result + Retake -->
                                <a href="quiz_result.php?attempt_id=<?= $quiz['attempt_id'] ?>"
                                   class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors text-center text-sm">
                                    <i class="fas fa-eye mr-1"></i>
                                    View Result
                                </a>
                                <a href="take_quiz.php?id=<?= $quiz['id'] ?>"
                                   class="px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold transition-colors text-sm flex-shrink-0">
                                    <i class="fas fa-redo mr-1"></i>
                                    Retake
                                </a>
                            <?php elseif ($status === 'completed'): ?>
                                <!-- Completed quiz - Show View Result + Retake -->
                                <a href="quiz_result.php?attempt_id=<?= $quiz['attempt_id'] ?>"
                                   class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors text-center text-sm">
                                    <i class="fas fa-eye mr-1"></i>
                                    View Result
                                </a>
                                <a href="take_quiz.php?id=<?= $quiz['id'] ?>"
                                   class="px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold transition-colors text-sm flex-shrink-0">
                                    <i class="fas fa-redo mr-1"></i>
                                    Retake
                                </a>
                            <?php else: ?>
                                <!-- Fallback - Unknown status -->
                                <a href="take_quiz.php?id=<?= $quiz['id'] ?>"
                                   class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors text-center text-sm">
                                    <i class="fas fa-play mr-1"></i>
                                    Start Quiz
                                </a>
                            <?php endif; ?>

                            <button @click="openModal = 'quiz<?= $quiz['id'] ?>'; setTimeout(() => createChart<?= $quiz['id'] ?>(), 100)"
                                    class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition-colors text-sm flex-shrink-0">
                                <i class="fas fa-history"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- History Modal with Chart -->
                <div x-show="openModal === 'quiz<?= $quiz['id'] ?>'"
                     x-transition
                     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                     style="display: none;">
                    <div @click.away="openModal = null" class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                            <h2 class="text-lg font-semibold text-gray-900">Attempt History & Progress</h2>
                            <button @click="openModal = null" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="p-6">
                            <?php
                            $historyStmt = $conn->prepare("
                                SELECT qa.id, qa.attempt_number, qa.attempted_at, qa.score, qa.status,
                                       (SELECT COALESCE(SUM(points), 0) FROM questions WHERE quiz_id = ?) AS total_points
                                FROM quiz_attempts qa
                                WHERE qa.student_id = ? AND qa.quiz_id = ?
                                ORDER BY qa.attempted_at ASC
                            ");
                            $historyStmt->execute([$quiz['id'], $studentId, $quiz['id']]);
                            $attempts = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php if (empty($attempts)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500">No attempts yet</p>
                                </div>
                            <?php else: ?>
                                <!-- Progress Chart -->
                                <div class="mb-6 bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-6 border-2 border-blue-100">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                                        Score Progress
                                    </h3>
                                    <canvas id="chart<?= $quiz['id'] ?>" class="w-full" style="max-height: 250px;"></canvas>
                                </div>

                                <!-- Attempt List -->
                                <h3 class="text-lg font-semibold text-gray-900 mb-3">All Attempts</h3>
                                <div class="space-y-3">
                                    <?php foreach (array_reverse($attempts) as $attempt): 
                                        $attemptPercentage = calculatePercentage($attempt['score'], $attempt['total_points']);
                                    ?>
                                        <div class="p-4 border-2 border-gray-200 rounded-lg hover:border-primary-300 transition-colors bg-white">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-semibold text-gray-900">Attempt #<?= $attempt['attempt_number'] ?></span>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                    <?= strtolower($attempt['status']) === 'completed' ? 'bg-green-100 text-green-700' : 
                                                       (strtolower($attempt['status']) === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                                                    <?= ucfirst($attempt['status']) ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mb-2">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?= date('M d, Y g:i A', strtotime($attempt['attempted_at'])) ?>
                                            </p>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-700">
                                                        <i class="fas fa-star mr-1 text-amber-500"></i>
                                                        Score: <strong class="text-lg"><?= $attemptPercentage ?>%</strong>
                                                    </p>
                                                    <p class="text-xs text-gray-500 mt-1"><?= $attempt['score'] ?> out of <?= $attempt['total_points'] ?> points</p>
                                                </div>
                                                <?php if ($attemptPercentage == $highestPercentage): ?>
                                                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full font-semibold">
                                                        <i class="fas fa-trophy"></i> Best
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Chart Script -->
                <script>
                function createChart<?= $quiz['id'] ?>() {
                    const ctx = document.getElementById('chart<?= $quiz['id'] ?>');
                    if (!ctx) return;
                    
                    // Destroy existing chart if it exists
                    if (window.chartInstance<?= $quiz['id'] ?>) {
                        window.chartInstance<?= $quiz['id'] ?>.destroy();
                    }
                    
                    const data = {
                        labels: [<?php foreach ($attempts as $a): ?>'Attempt #<?= $a['attempt_number'] ?>',<?php endforeach; ?>],
                        datasets: [{
                            label: 'Score (%)',
                            data: [<?php foreach ($attempts as $a): ?><?= calculatePercentage($a['score'], $a['total_points']) ?>,<?php endforeach; ?>],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgb(59, 130, 246)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    };
                    
                    window.chartInstance<?= $quiz['id'] ?> = new Chart(ctx, {
                        type: 'line',
                        data: data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: {
                                        size: 14
                                    },
                                    bodyFont: {
                                        size: 13
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return 'Score: ' + context.parsed.y + '%';
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
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                </script>
                <?php endforeach; ?>
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
                sidebar.classList.remove('sidebar-collapsed');
                sidebar.classList.add('sidebar-expanded');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.remove('sidebar-expanded');
                sidebar.classList.add('sidebar-collapsed');
                overlay.classList.add('hidden');
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
        if (window.innerWidth < 1025 && sidebarExpanded) {
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
                // Desktop mode
                overlay.classList.add('hidden');
                if (sidebarExpanded) {
                    sidebar.classList.remove('sidebar-collapsed');
                    sidebar.classList.add('sidebar-expanded');
                    mainContent.style.marginLeft = '18rem';
                } else {
                    sidebar.classList.remove('sidebar-expanded');
                    sidebar.classList.add('sidebar-collapsed');
                    mainContent.style.marginLeft = '5rem';
                }
            } else {
                // Mobile mode
                mainContent.style.marginLeft = '0';
                if (!sidebarExpanded) {
                    sidebar.classList.add('sidebar-collapsed');
                    sidebar.classList.remove('sidebar-expanded');
                    overlay.classList.add('hidden');
                }
            }
        }, 250);
    });
    
    // Initialize on page load
    window.addEventListener('load', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        if (window.innerWidth >= 1025) {
            // Desktop: start with collapsed sidebar
            sidebar.classList.add('sidebar-collapsed');
            mainContent.style.marginLeft = '5rem';
        } else {
            // Mobile: start with hidden sidebar
            sidebar.classList.add('sidebar-collapsed');
            mainContent.style.marginLeft = '0';
        }
    });
</script>

</body>
</html>