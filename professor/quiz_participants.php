<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];
$quizId = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if ($quizId === 0) {
    header("Location: manage_quizzes.php");
    exit();
}

// Verify quiz belongs to professor
$stmt = $conn->prepare("SELECT q.*, m.title as module_title FROM quizzes q LEFT JOIN modules m ON q.module_id = m.id WHERE q.id = ? AND q.professor_id = ?");
$stmt->execute([$quizId, $professorId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    $_SESSION['error'] = "Quiz not found or you don't have permission to view it.";
    header("Location: manage_quizzes.php");
    exit();
}

// Fetch professor info
$stmt = $conn->prepare("SELECT firstname, lastname, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";
$profilePic = getProfilePicture($prof, "../");

// Fetch all sections
$sections = [];
try {
    $stmt = $conn->query("SELECT DISTINCT section FROM users WHERE role = 'student' AND section IS NOT NULL AND section != '' ORDER BY section");
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // If section column doesn't exist, continue without sections
    $sections = ['All Students'];
}

// Fetch students who HAVE taken the quiz
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.section, u.profile_pic, u.gender,
           qa.score, qa.total_score, qa.attempted_at as attempt_date,
           CASE WHEN qa.score IS NOT NULL AND qa.total_score > 0 
                THEN ROUND((qa.score / qa.total_score) * 100, 2)
                ELSE 0 
           END as percentage
    FROM users u
    INNER JOIN quiz_attempts qa ON u.id = qa.student_id
    WHERE u.role = 'student' AND qa.quiz_id = ?
    ORDER BY u.section, u.lastname, u.firstname
");
$stmt->execute([$quizId]);
$takenStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students who HAVE NOT taken the quiz
$stmt = $conn->prepare("
    SELECT u.id, u.firstname, u.lastname, u.email, u.section, u.profile_pic, u.gender
    FROM users u
    WHERE u.role = 'student'
    AND u.id NOT IN (
        SELECT DISTINCT student_id FROM quiz_attempts WHERE quiz_id = ?
    )
    ORDER BY u.section, u.lastname, u.firstname
");
$stmt->execute([$quizId]);
$notTakenStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize students by section
$takenBySection = [];
$notTakenBySection = [];

foreach ($takenStudents as $student) {
    $section = empty($student['section']) ? 'No Section' : $student['section'];
    if (!isset($takenBySection[$section])) {
        $takenBySection[$section] = [];
    }
    $takenBySection[$section][] = $student;
}

foreach ($notTakenStudents as $student) {
    $section = empty($student['section']) ? 'No Section' : $student['section'];
    if (!isset($notTakenBySection[$section])) {
        $notTakenBySection[$section] = [];
    }
    $notTakenBySection[$section][] = $student;
}

// Get all unique sections
$allSections = array_unique(array_merge(array_keys($takenBySection), array_keys($notTakenBySection)));
sort($allSections);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Participants - <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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

        .section-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .section-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 1rem 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .section-header:hover {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .section-content.active {
            max-height: 3000px;
        }

        .student-card {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s;
        }

        .student-card:hover {
            background: #f9fafb;
        }

        .student-card:last-child {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .section-header {
                padding: 0.875rem 1rem;
                font-size: 0.9375rem;
            }

            .student-card {
                padding: 0.625rem 0.875rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<div class="min-h-screen">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="manage_quizzes.php" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Quiz Participants</h1>
                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($quiz['title']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 sm:mb-8">
            <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Students</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900"><?= count($takenStudents) + count($notTakenStudents) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Completed</p>
                        <p class="text-2xl sm:text-3xl font-bold text-green-600"><?= count($takenStudents) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Pending</p>
                        <p class="text-2xl sm:text-3xl font-bold text-orange-600"><?= count($notTakenStudents) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-t-xl border border-gray-200">
            <div class="flex border-b border-gray-200">
                <button onclick="switchTab('taken')" id="takenTab" class="flex-1 px-4 sm:px-6 py-3 sm:py-4 text-sm sm:text-base font-semibold text-primary-600 border-b-2 border-primary-600 transition-colors">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Taken (<?= count($takenStudents) ?>)</span>
                </button>
                <button onclick="switchTab('notTaken')" id="notTakenTab" class="flex-1 px-4 sm:px-6 py-3 sm:py-4 text-sm sm:text-base font-semibold text-gray-600 hover:text-gray-900 transition-colors">
                    <i class="fas fa-clock mr-2"></i>
                    <span>Not Taken (<?= count($notTakenStudents) ?>)</span>
                </button>
            </div>
        </div>

        <!-- Taken Students Tab -->
        <div id="takenContent" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6">
            <?php if (count($takenStudents) > 0): ?>
                <?php foreach ($allSections as $section): ?>
                    <?php if (isset($takenBySection[$section])): ?>
                        <div class="section-card">
                            <div class="section-header" onclick="toggleSection('taken-<?= htmlspecialchars($section) ?>')">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-users-class text-lg"></i>
                                    <span class="font-bold"><?= htmlspecialchars($section) ?></span>
                                    <span class="badge bg-white bg-opacity-20 text-white">
                                        <?= count($takenBySection[$section]) ?> student<?= count($takenBySection[$section]) != 1 ? 's' : '' ?>
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down transition-transform" id="icon-taken-<?= htmlspecialchars($section) ?>"></i>
                            </div>
                            <div class="section-content" id="taken-<?= htmlspecialchars($section) ?>">
                                <?php foreach ($takenBySection[$section] as $student): ?>
                                    <div class="student-card">
                                        <div class="flex items-center justify-between flex-wrap gap-3">
                                            <div class="flex items-center space-x-3 flex-1 min-w-0">
                                                <img src="<?= htmlspecialchars(getProfilePicture($student, '../')) ?>" 
                                                     alt="<?= htmlspecialchars($student['firstname']) ?>" 
                                                     class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-900 truncate">
                                                        <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?= date('M d, Y g:i A', strtotime($student['attempt_date'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-3">
                                                <div class="text-right">
                                                    <p class="text-lg font-bold text-gray-900"><?= $student['percentage'] ?>%</p>
                                                    <p class="text-xs text-gray-500"><?= $student['score'] ?>/<?= $student['total_score'] ?> pts</p>
                                                </div>
                                                <?php
                                                    $percentage = $student['percentage'];
                                                    if ($percentage >= 90) {
                                                        $badgeClass = 'bg-green-100 text-green-700';
                                                        $icon = 'fa-star';
                                                    } elseif ($percentage >= 75) {
                                                        $badgeClass = 'bg-blue-100 text-blue-700';
                                                        $icon = 'fa-thumbs-up';
                                                    } elseif ($percentage >= 60) {
                                                        $badgeClass = 'bg-yellow-100 text-yellow-700';
                                                        $icon = 'fa-check';
                                                    } else {
                                                        $badgeClass = 'bg-red-100 text-red-700';
                                                        $icon = 'fa-exclamation-triangle';
                                                    }
                                                ?>
                                                <i class="fas <?= $icon ?> text-lg <?= strpos($badgeClass, 'green') !== false ? 'text-green-600' : (strpos($badgeClass, 'blue') !== false ? 'text-blue-600' : (strpos($badgeClass, 'yellow') !== false ? 'text-yellow-600' : 'text-red-600')) ?>"></i>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-clipboard-check text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">No students have taken this quiz yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Not Taken Students Tab -->
        <div id="notTakenContent" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-4 sm:p-6 hidden">
            <?php if (count($notTakenStudents) > 0): ?>
                <?php foreach ($allSections as $section): ?>
                    <?php if (isset($notTakenBySection[$section])): ?>
                        <div class="section-card">
                            <div class="section-header" onclick="toggleSection('nottaken-<?= htmlspecialchars($section) ?>')">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-users-class text-lg"></i>
                                    <span class="font-bold"><?= htmlspecialchars($section) ?></span>
                                    <span class="badge bg-white bg-opacity-20 text-white">
                                        <?= count($notTakenBySection[$section]) ?> student<?= count($notTakenBySection[$section]) != 1 ? 's' : '' ?>
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down transition-transform" id="icon-nottaken-<?= htmlspecialchars($section) ?>"></i>
                            </div>
                            <div class="section-content" id="nottaken-<?= htmlspecialchars($section) ?>">
                                <?php foreach ($notTakenBySection[$section] as $student): ?>
                                    <div class="student-card">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3 flex-1">
                                                <img src="<?= htmlspecialchars(getProfilePicture($student, '../')) ?>" 
                                                     alt="<?= htmlspecialchars($student['firstname']) ?>" 
                                                     class="w-10 h-10 rounded-full object-cover">
                                                <div>
                                                    <p class="font-semibold text-gray-900">
                                                        <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($student['email']) ?></p>
                                                </div>
                                            </div>
                                            <span class="badge bg-orange-100 text-orange-700">
                                                <i class="fas fa-clock mr-1"></i>
                                                Pending
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-check-double text-6xl text-green-300 mb-4"></i>
                    <p class="text-gray-600">All students have completed this quiz!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        const takenTab = document.getElementById('takenTab');
        const notTakenTab = document.getElementById('notTakenTab');
        const takenContent = document.getElementById('takenContent');
        const notTakenContent = document.getElementById('notTakenContent');

        if (tab === 'taken') {
            takenTab.classList.add('text-primary-600', 'border-b-2', 'border-primary-600');
            takenTab.classList.remove('text-gray-600');
            notTakenTab.classList.remove('text-primary-600', 'border-b-2', 'border-primary-600');
            notTakenTab.classList.add('text-gray-600');
            takenContent.classList.remove('hidden');
            notTakenContent.classList.add('hidden');
        } else {
            notTakenTab.classList.add('text-primary-600', 'border-b-2', 'border-primary-600');
            notTakenTab.classList.remove('text-gray-600');
            takenTab.classList.remove('text-primary-600', 'border-b-2', 'border-primary-600');
            takenTab.classList.add('text-gray-600');
            notTakenContent.classList.remove('hidden');
            takenContent.classList.add('hidden');
        }
    }

    function toggleSection(sectionId) {
        const content = document.getElementById(sectionId);
        const icon = document.getElementById('icon-' + sectionId);
        
        content.classList.toggle('active');
        icon.classList.toggle('rotate-180');
    }

    // Auto-expand first section in each tab
    document.addEventListener('DOMContentLoaded', function() {
        const firstTakenSection = document.querySelector('[id^="taken-"]');
        const firstNotTakenSection = document.querySelector('[id^="nottaken-"]');
        
        if (firstTakenSection) {
            firstTakenSection.classList.add('active');
            const icon = document.getElementById('icon-' + firstTakenSection.id);
            if (icon) icon.classList.add('rotate-180');
        }
        
        if (firstNotTakenSection) {
            firstNotTakenSection.classList.add('active');
            const icon = document.getElementById('icon-' + firstNotTakenSection.id);
            if (icon) icon.classList.add('rotate-180');
        }
    });
</script>

</body>
</html>
