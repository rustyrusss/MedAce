<?php
session_start();
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

// Fetch all attempts
$stmt = $conn->prepare("
    SELECT id, score, status, attempted_at, attempt_number
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
?>

<div class="space-y-4">
    <!-- Student Info Header -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
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

    <!-- Attempts List -->
    <?php foreach ($attempts as $index => $attempt): ?>
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-semibold text-sm">
                        #<?= $attempt['attempt_number'] ?>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">
                            Attempt <?= $attempt['attempt_number'] ?>
                        </p>
                        <p class="text-xs text-gray-500">
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
                            class="inline-flex items-center px-3 py-1.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium text-sm transition-colors">
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

<script>
let loadedAttempts = new Set();

function toggleAttemptDetails(attemptId) {
    const detailsDiv = document.getElementById(`attempt-details-${attemptId}`);
    
    if (detailsDiv.classList.contains('hidden')) {
        detailsDiv.classList.remove('hidden');
        
        // Load details if not already loaded
        if (!loadedAttempts.has(attemptId)) {
            loadAttemptDetails(attemptId);
            loadedAttempts.add(attemptId);
        }
    } else {
        detailsDiv.classList.add('hidden');
    }
}

function loadAttemptDetails(attemptId) {
    const detailsDiv = document.getElementById(`attempt-details-${attemptId}`);
    
    fetch(`attempt_details_ajax.php?attempt_id=${attemptId}`)
        .then(response => response.text())
        .then(html => {
            detailsDiv.innerHTML = html;
        })
        .catch(error => {
            detailsDiv.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-3xl text-red-500"></i>
                    <p class="text-gray-600 mt-2">Error loading details</p>
                </div>
            `;
        });
}
</script>