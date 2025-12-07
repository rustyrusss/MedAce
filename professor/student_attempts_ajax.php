<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_conn.php';

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    http_response_code(403);
    echo '<div class="text-center py-12"><p class="text-red-600">Access denied</p></div>';
    exit();
}

$professorId = $_SESSION['user_id'];
$quizId = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($quizId <= 0 || $studentId <= 0) {
    echo '<div class="text-center py-12"><p class="text-red-600">Invalid parameters</p></div>';
    exit();
}

// Verify quiz belongs to professor
$stmt = $conn->prepare("SELECT title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quizId, $professorId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo '<div class="text-center py-12"><p class="text-red-600">Quiz not found</p></div>';
    exit();
}

// Get student info
$stmt = $conn->prepare("SELECT firstname, lastname, email FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '<div class="text-center py-12"><p class="text-red-600">Student not found</p></div>';
    exit();
}

// ✅ FIX: Get total possible points for the quiz
$stmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) as total_points FROM questions WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$totalPoints = floatval($stmt->fetchColumn());

// Fetch all attempts
$stmt = $conn->prepare("
    SELECT id, score as score_raw, status, attempted_at, attempt_number
    FROM quiz_attempts
    WHERE quiz_id = ? AND student_id = ?
    ORDER BY attempt_number DESC
");
$stmt->execute([$quizId, $studentId]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($attempts) === 0) {
    echo '<div class="text-center py-12">
        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">No attempts found for this student</p>
    </div>';
    exit();
}

// ✅ FIX: Calculate percentage for each attempt
foreach ($attempts as &$attempt) {
    $attempt['score'] = ($totalPoints > 0) 
        ? ($attempt['score_raw'] / $totalPoints) * 100 
        : 0;
}
unset($attempt);

// Split attempts into latest and older
$latestAttempts = array_slice($attempts, 0, 3);
$olderAttempts = array_slice($attempts, 3);

// Prepare data for chart (reverse order for chronological display)
$chartData = array_reverse($attempts);
$chartLabels = array_map(function($a) { return 'Attempt ' . $a['attempt_number']; }, $chartData);
$chartScores = array_map(function($a) { return round($a['score'], 1); }, $chartData);
?>

<div class="space-y-4">
    <!-- Student Info Header -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-user-graduate text-primary-600 mr-2"></i>
                    <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>
                </h3>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($student['email']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-600">Total Attempts</p>
                <p class="text-2xl font-bold text-primary-600"><?= count($attempts) ?></p>
            </div>
        </div>
    </div>

    <!-- Latest Attempts Section -->
    <div>
        <h4 class="font-bold text-gray-900 text-lg mb-3 flex items-center">
            <i class="fas fa-clock text-blue-600 mr-2"></i>
            Recent Attempts
        </h4>
        
        <div class="space-y-3" id="latest-attempts">
            <?php foreach ($latestAttempts as $index => $attempt): ?>
            <div class="bg-white border-2 border-gray-200 rounded-lg overflow-hidden hover:border-primary-300 transition-colors">
                <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-blue-500 text-white font-bold text-sm shadow-md">
                                #<?= $attempt['attempt_number'] ?>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-gray-900">
                                    Attempt <?= $attempt['attempt_number'] ?>
                                    <?php if ($index === 0): ?>
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <i class="fas fa-star text-xs mr-1"></i>Latest
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500 flex items-center gap-1">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= date('M j, Y g:i A', strtotime($attempt['attempted_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php
                                $score = $attempt['score'];
                                if ($score >= 90) {
                                    $scoreClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                                    $icon = 'fa-trophy';
                                } elseif ($score >= 75) {
                                    $scoreClass = 'bg-green-100 text-green-800 border-green-300';
                                    $icon = 'fa-check-circle';
                                } elseif ($score >= 60) {
                                    $scoreClass = 'bg-yellow-100 text-yellow-800 border-yellow-300';
                                    $icon = 'fa-exclamation-circle';
                                } else {
                                    $scoreClass = 'bg-red-100 text-red-800 border-red-300';
                                    $icon = 'fa-times-circle';
                                }
                            ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold border-2 <?= $scoreClass ?>">
                                <i class="fas <?= $icon ?> mr-1"></i>
                                <?= number_format($score, 1) ?>%
                            </span>
                            <button onclick="toggleAttemptDetails(<?= $attempt['id'] ?>)" 
                                    class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-semibold text-sm transition-all shadow-sm hover:shadow-md">
                                <i class="fas fa-eye mr-2"></i>
                                View Answers
                            </button>
                        </div>
                    </div>
                </div>

                <div id="attempt-details-<?= $attempt['id'] ?>" class="hidden p-4 bg-gray-50" data-attempt-id="<?= $attempt['id'] ?>">
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary-500"></i>
                        <p class="text-gray-600 mt-2">Loading answers...</p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Older Attempts (Collapsible) -->
    <?php if (count($olderAttempts) > 0): ?>
    <div id="older-attempts-section">
        <div id="older-attempts" class="hidden space-y-3">
            <h4 class="font-bold text-gray-700 text-lg mb-3 flex items-center">
                <i class="fas fa-history text-gray-600 mr-2"></i>
                Previous Attempts
            </h4>
            
            <?php foreach ($olderAttempts as $index => $attempt): ?>
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-gray-300 transition-colors opacity-90">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-200 text-gray-700 font-semibold text-sm">
                                #<?= $attempt['attempt_number'] ?>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    Attempt <?= $attempt['attempt_number'] ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?= date('M j, Y g:i A', strtotime($attempt['attempted_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php
                                $score = $attempt['score'];
                                if ($score >= 90) {
                                    $scoreClass = 'bg-emerald-100 text-emerald-800';
                                } elseif ($score >= 75) {
                                    $scoreClass = 'bg-green-100 text-green-800';
                                } elseif ($score >= 60) {
                                    $scoreClass = 'bg-yellow-100 text-yellow-800';
                                } else {
                                    $scoreClass = 'bg-red-100 text-red-800';
                                }
                            ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?= $scoreClass ?>">
                                <?= number_format($score, 1) ?>%
                            </span>
                            <button onclick="toggleAttemptDetails(<?= $attempt['id'] ?>)" 
                                    class="inline-flex items-center px-3 py-1.5 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium text-sm transition-colors">
                                <i class="fas fa-eye mr-1"></i>
                                View Answers
                            </button>
                        </div>
                    </div>
                </div>

                <div id="attempt-details-<?= $attempt['id'] ?>" class="hidden p-4" data-attempt-id="<?= $attempt['id'] ?>">
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary-500"></i>
                        <p class="text-gray-600 mt-2">Loading answers...</p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button onclick="toggleOlderAttempts()" 
                id="toggle-older-btn"
                class="w-full py-3 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 rounded-lg font-semibold text-gray-700 transition-all mt-4 border border-gray-300 shadow-sm">
            <i class="fas fa-chevron-down mr-2" id="toggleIcon"></i>
            <span id="toggleText">Show <?= count($olderAttempts) ?> Older Attempt<?= count($olderAttempts) != 1 ? 's' : '' ?></span>
        </button>
    </div>

    <script>
        function toggleOlderAttempts() {
            const older = document.getElementById('older-attempts');
            const icon = document.getElementById('toggleIcon');
            const text = document.getElementById('toggleText');
            
            if (older.classList.contains('hidden')) {
                older.classList.remove('hidden');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                text.textContent = 'Hide Older Attempts';
            } else {
                older.classList.add('hidden');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                text.textContent = 'Show <?= count($olderAttempts) ?> Older Attempt<?= count($olderAttempts) != 1 ? 's' : '' ?>';
            }
        }
    </script>
    <?php endif; ?>
</div>