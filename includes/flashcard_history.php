<?php
// ✅ Start session only if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_conn.php';
require_once __DIR__ . '/../includes/journey_fetch.php';
require_once __DIR__ . '/../config/env.php';

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
            if (!empty($student['profile_pic']) && file_exists('../' . $student['profile_pic'])) {
                unlink('../' . $student['profile_pic']);
            }
            $relativePath = 'uploads/profiles/' . $filename;
            $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $updateStmt->execute([$relativePath, $studentId]);
            header("Location: dashboard.php?upload=success");
            exit();
        }
    }
}

// ✅ Fetch journey data
$journeyData = getStudentJourney($conn, $studentId);
$steps = $journeyData['steps'] ?? [];
$stats = $journeyData['stats'] ?? ['completed' => 0, 'total' => 1, 'current' => 0, 'pending' => 0, 'progress' => 0];

// ✅ Fetch quizzes with prerequisite check
$quizzesStmt = $conn->prepare("
    SELECT q.id, q.title, q.prerequisite_module_id, qa.status,
           pm.title AS prerequisite_module_title,
           LOWER(TRIM(COALESCE(sp.status, ''))) AS prerequisite_status
    FROM quizzes q
    LEFT JOIN modules pm ON q.prerequisite_module_id = pm.id
    LEFT JOIN student_progress sp ON sp.module_id = q.prerequisite_module_id AND sp.student_id = ?
    LEFT JOIN (
        SELECT qa1.* FROM quiz_attempts qa1
        INNER JOIN (
            SELECT quiz_id, MAX(attempted_at) AS latest_attempt
            FROM quiz_attempts WHERE student_id = ? GROUP BY quiz_id
        ) latest ON qa1.quiz_id = latest.quiz_id AND qa1.attempted_at = latest.latest_attempt
    ) qa ON q.id = qa.quiz_id
    WHERE q.status = 'active'
    ORDER BY q.publish_time DESC
");
$quizzesStmt->execute([$studentId, $studentId]);
$allQuizzes = $quizzesStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Filter out locked quizzes
$quizzes = [];
foreach ($allQuizzes as $quiz) {
    $prerequisiteMet = !$quiz['prerequisite_module_id'] || ($quiz['prerequisite_status'] === 'completed');
    if ($prerequisiteMet) {
        $quiz['status'] = $quiz['status'] ?: 'Pending';
        $quizzes[] = $quiz;
    }
}

// ✅ Fetch daily tip
$dailyTipStmt = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1");
$dailyTip = $dailyTipStmt ? $dailyTipStmt->fetchColumn() : null;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Student Dashboard - MedAce</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">
    
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/profile_modals.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 w-full lg:ml-20 transition-all duration-300">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-3 sm:px-4 lg:px-8 py-3 lg:py-4 safe-top">
            <div class="flex items-center justify-between">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars text-gray-600 text-lg"></i>
                </button>
                <div class="flex items-center space-x-3 lg:space-x-4">
                    <div class="hidden sm:block">
                        <p class="text-xs lg:text-sm text-gray-500">Today</p>
                        <p class="text-xs lg:text-sm font-semibold text-gray-900" id="currentDate"></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-3 sm:px-4 lg:px-8 py-4 lg:py-8 safe-bottom">
            
            <?php if(isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
            <div class="mb-4 lg:mb-6 bg-green-50 border border-green-200 text-green-800 px-3 lg:px-4 py-2 lg:py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center text-sm lg:text-base">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Profile picture updated successfully!</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="gradient-bg rounded-xl lg:rounded-2xl p-4 sm:p-6 lg:p-8 mb-4 lg:mb-8 text-white shadow-lg animate-fade-in-up">
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1 lg:mb-2">Welcome back, <?= htmlspecialchars($student['firstname']) ?>! 👋</h1>
                <p class="text-blue-100 text-xs sm:text-sm lg:text-base">Continue your nursing journey and track your progress</p>
            </div>

            <!-- Progress Tracker -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 sm:p-4 mb-4 lg:mb-8 animate-fade-in-up">
                <div class="flex items-center justify-between mb-2 lg:mb-3">
                    <h2 class="text-sm lg:text-base font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-chart-line text-primary-500 mr-2 text-xs lg:text-sm"></i>
                        Learning Progress
                    </h2>
                    <span class="text-xl lg:text-2xl font-bold text-primary-600"><?= $stats['progress'] ?>%</span>
                </div>

                <div class="relative w-full bg-gray-200 h-2 rounded-full overflow-hidden mb-2 lg:mb-3">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-600 to-primary-500 transition-all duration-1000" style="width: <?= $stats['progress'] ?>%"></div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:gap-3 lg:gap-4 text-xs lg:text-sm mb-3 lg:mb-4 pb-2 lg:pb-3 border-b border-gray-200">
                    <div class="flex items-center gap-1 lg:gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                        <span class="text-gray-600"><?= $stats['total'] ?> Total</span>
                    </div>
                    <div class="flex items-center gap-1 lg:gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        <span class="text-gray-600"><?= $stats['completed'] ?> Done</span>
                    </div>
                    <div class="flex items-center gap-1 lg:gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                        <span class="text-gray-600"><?= $stats['current'] ?> Active</span>
                    </div>
                    <div class="flex items-center gap-1 lg:gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                        <span class="text-gray-600"><?= $stats['pending'] ?> Pending</span>
                    </div>
                </div>

                <?php if (!empty($steps)): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Current Journey</p>
                    <div class="journey-steps-container flex items-center gap-1.5 lg:gap-2 overflow-x-auto pb-2">
                        <?php foreach ($steps as $index => $step): ?>
                            <?php
                                $st = strtolower($step['status']);
                                $isCompleted = ($st === 'completed');
                                $isCurrent = ($st === 'current');
                            ?>
                            <div class="flex-shrink-0 flex items-center gap-1 lg:gap-1.5">
                                <div class="relative flex items-center justify-center w-7 h-7 lg:w-8 lg:h-8 rounded-lg
                                    <?= $isCompleted ? 'bg-green-100 text-green-600' : ($isCurrent ? 'bg-blue-100 text-blue-600 ring-2 ring-blue-300' : 'bg-gray-100 text-gray-400') ?>">
                                    <?php if ($isCompleted): ?>
                                        <i class="fas fa-check text-xs"></i>
                                    <?php elseif ($isCurrent): ?>
                                        <i class="fas fa-play text-xs"></i>
                                    <?php else: ?>
                                        <i class="fas fa-lock text-xs"></i>
                                    <?php endif; ?>
                                    <?php if ($isCurrent): ?>
                                        <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2 lg:h-2.5 lg:w-2.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 lg:h-2.5 lg:w-2.5 bg-blue-500"></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="px-2 lg:px-2.5 py-1 lg:py-1.5 rounded-lg border
                                    <?= $isCompleted ? 'bg-green-50 border-green-200' : ($isCurrent ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200') ?>">
                                    <p class="text-xs font-medium text-gray-900 whitespace-nowrap">
                                        <?= $step['type'] === 'module' ? '📘' : '📝' ?>
                                        <span class="hidden sm:inline"><?= htmlspecialchars($step['title']) ?></span>
                                        <span class="sm:hidden"><?= htmlspecialchars(strlen($step['title']) > 15 ? substr($step['title'], 0, 15) . '...' : $step['title']) ?></span>
                                    </p>
                                </div>
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
            <div class="bg-white rounded-xl lg:rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up" style="animation-delay: 0.1s;">
                <div class="px-4 sm:px-6 py-4 lg:py-5 border-b border-gray-200">
                    <h2 class="text-lg lg:text-xl font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-clipboard-list text-primary-500 mr-2"></i>
                        Available Quizzes
                    </h2>
                </div>

                <?php if (empty($quizzes)): ?>
                <div class="text-center py-12 lg:py-16 px-4">
                    <div class="inline-flex items-center justify-center w-16 h-16 lg:w-20 lg:h-20 bg-gray-100 rounded-full mb-3 lg:mb-4">
                        <i class="fas fa-clipboard-list text-3xl lg:text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-2">No quizzes available</h3>
                    <p class="text-sm lg:text-base text-gray-600">Complete required modules to unlock quizzes</p>
                </div>
                <?php else: ?>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                        <?php foreach ($quizzes as $quiz): ?>
                            <?php 
                                $st = strtolower($quiz['status']); 
                                $statusConfig = match($st) {
                                    'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle', 'btnBg' => 'bg-green-600 hover:bg-green-700', 'btnText' => 'Retake Quiz'],
                                    'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => 'fa-times-circle', 'btnBg' => 'bg-red-600 hover:bg-red-700', 'btnText' => 'Retry Quiz'],
                                    'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'icon' => 'fa-clock', 'btnBg' => 'bg-primary-600 hover:bg-primary-700', 'btnText' => 'Start Quiz'],
                                    default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'icon' => 'fa-info-circle', 'btnBg' => 'bg-primary-600 hover:bg-primary-700', 'btnText' => 'Start Quiz']
                                };
                                
                                $attemptStmt = $conn->prepare("SELECT id FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? ORDER BY attempted_at DESC LIMIT 1");
                                $attemptStmt->execute([$studentId, $quiz['id']]);
                                $latestAttempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
                                $hasAttempt = !empty($latestAttempt);
                            ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-4 lg:p-6 card-hover">
                                <div class="flex items-start justify-between mb-3 lg:mb-4">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <h3 class="font-semibold text-gray-900 mb-2 text-base lg:text-lg break-words">
                                            <?= htmlspecialchars($quiz['title'] ?? 'Untitled Quiz') ?>
                                        </h3>
                                    </div>
                                    <span class="inline-flex items-center px-2 lg:px-3 py-1 rounded-full text-xs font-semibold flex-shrink-0 <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>">
                                        <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                        <span class="hidden sm:inline"><?= ucfirst($st) ?></span>
                                        <span class="sm:hidden"><?= substr(ucfirst($st), 0, 4) ?></span>
                                    </span>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <a href="../member/take_quiz.php?id=<?= $quiz['id'] ?>" class="flex-1 text-center <?= $statusConfig['btnBg'] ?> text-white px-3 lg:px-4 py-2.5 lg:py-3 rounded-lg font-semibold transition-colors shadow-sm text-sm lg:text-base">
                                        <i class="fas fa-play mr-2"></i>
                                        <span class="hidden sm:inline"><?= $statusConfig['btnText'] ?></span>
                                        <span class="sm:hidden"><?= $st === 'completed' ? 'Retake' : ($st === 'failed' ? 'Retry' : 'Start') ?></span>
                                    </a>
                                    <?php if ($hasAttempt && ($st === 'completed' || $st === 'failed')): ?>
                                        <a href="quiz_result.php?attempt_id=<?= $latestAttempt['id'] ?>" class="flex-shrink-0 bg-gray-600 hover:bg-gray-700 text-white px-3 lg:px-4 py-2.5 lg:py-3 rounded-lg font-semibold transition-colors shadow-sm text-sm lg:text-base flex items-center justify-center">
                                            <i class="fas fa-eye"></i>
                                            <span class="ml-2 hidden sm:inline">View</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Flashcard History -->
            <?php include __DIR__ . '/../includes/flashcard_history.php'; ?>

            <!-- Daily Tip -->
            <?php if ($dailyTip): ?>
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl lg:rounded-2xl p-4 sm:p-6 lg:p-8 text-center border-2 border-purple-200 shadow-sm mt-4 lg:mt-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="inline-flex items-center justify-center w-12 h-12 lg:w-16 lg:h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full mb-3 lg:mb-4 shadow-lg">
                    <i class="fas fa-lightbulb text-xl lg:text-2xl text-white"></i>
                </div>
                <h3 class="text-base lg:text-lg font-semibold mb-2 lg:mb-3 text-purple-900">💡 Daily Nursing Tip</h3>
                <p class="text-gray-700 text-sm sm:text-base lg:text-lg italic leading-relaxed max-w-2xl mx-auto">
                    "<?= htmlspecialchars($dailyTip) ?>"
                </p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/chatbot.php'; ?>
<script src="../assets/js/dashboard.js"></script>

</body>
</html>