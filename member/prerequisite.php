<?php
/**
 * QUIZ PREREQUISITE VALIDATION
 * 
 * This code should be added to your quiz-taking page (e.g., take_quiz.php)
 * to prevent students from accessing quizzes if they haven't completed the prerequisite module.
 * 
 * Place this code BEFORE displaying the quiz interface.
 */

session_start();
require_once '../config/db_conn.php';

// Assume you have the quiz_id from URL parameter and student_id from session
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$student_id = $_SESSION['user_id'] ?? 0;

if (!$quiz_id || !$student_id) {
    header("Location: ../public/index.php");
    exit();
}

try {
    // ✅ Step 1: Fetch quiz details including prerequisite module
    $stmt = $conn->prepare("
        SELECT q.id, q.title, q.prerequisite_module_id, 
               pm.title AS prerequisite_module_title
        FROM quizzes q
        LEFT JOIN modules pm ON q.prerequisite_module_id = pm.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        $_SESSION['error'] = "Quiz not found.";
        header("Location: student_dashboard.php");
        exit();
    }
    
    // ✅ Step 2: Check if quiz has a prerequisite module
    if ($quiz['prerequisite_module_id']) {
        // Check if student has completed the prerequisite module
        $stmt = $conn->prepare("
            SELECT status, completed_at
            FROM student_progress
            WHERE student_id = ? AND module_id = ?
        ");
        $stmt->execute([$student_id, $quiz['prerequisite_module_id']]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If module not completed, block access
        if (!$progress || $progress['status'] !== 'completed') {
            $_SESSION['error'] = "You must complete the module '" . 
                                htmlspecialchars($quiz['prerequisite_module_title']) . 
                                "' before taking this quiz.";
            header("Location: student_dashboard.php");
            exit();
        }
    }
    
    // ✅ Step 3: If we reach here, student can access the quiz
    // Continue with your normal quiz display logic...
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: student_dashboard.php");
    exit();
}

// ============================================================================
// ALTERNATIVE: Display a warning message instead of blocking
// ============================================================================

/**
 * If you prefer to show a warning instead of blocking access completely,
 * you can use this approach instead:
 */

$prerequisite_warning = null;

if ($quiz['prerequisite_module_id']) {
    $stmt = $conn->prepare("
        SELECT status, completed_at
        FROM student_progress
        WHERE student_id = ? AND module_id = ?
    ");
    $stmt->execute([$student_id, $quiz['prerequisite_module_id']]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$progress || $progress['status'] !== 'completed') {
        $prerequisite_warning = [
            'module_title' => $quiz['prerequisite_module_title'],
            'module_id' => $quiz['prerequisite_module_id']
        ];
    }
}

// Then in your HTML:
?>

<?php if ($prerequisite_warning): ?>
<div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-lg">
    <div class="flex items-start">
        <i class="fas fa-exclamation-triangle text-amber-500 text-xl mr-3 mt-1"></i>
        <div class="flex-1">
            <h3 class="font-semibold text-amber-900 mb-1">Prerequisite Module Required</h3>
            <p class="text-amber-800 text-sm mb-2">
                It is recommended that you complete the module 
                <strong>"<?= htmlspecialchars($prerequisite_warning['module_title']) ?>"</strong> 
                before taking this quiz.
            </p>
            <a href="view_module.php?module_id=<?= $prerequisite_warning['module_id'] ?>" 
               class="inline-flex items-center text-sm font-semibold text-amber-700 hover:text-amber-900">
                <i class="fas fa-book-open mr-1"></i>
                Go to Prerequisite Module
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================================ -->
<!-- STUDENT DASHBOARD: Show prerequisite status in quiz list -->
<!-- ============================================================================ -->

<?php
/**
 * When displaying available quizzes on the student dashboard,
 * you can show which quizzes have prerequisites and whether they're met:
 */

// In your student dashboard quiz listing query:
$stmt = $conn->prepare("
    SELECT q.id, q.title, q.subject, q.status, q.time_limit, 
           q.prerequisite_module_id,
           pm.title AS prerequisite_module_title,
           sp.status AS prerequisite_status,
           sp.completed_at AS prerequisite_completed_at,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) AS attempt_count
    FROM quizzes q
    LEFT JOIN modules pm ON q.prerequisite_module_id = pm.id
    LEFT JOIN student_progress sp ON sp.module_id = q.prerequisite_module_id AND sp.student_id = ?
    WHERE q.status = 'active'
    ORDER BY q.created_at DESC
");
$stmt->execute([$student_id, $student_id]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Then in your HTML display:
foreach ($quizzes as $quiz) {
    $can_access = true;
    $prerequisite_message = '';
    
    if ($quiz['prerequisite_module_id']) {
        if ($quiz['prerequisite_status'] !== 'completed') {
            $can_access = false;
            $prerequisite_message = 'Complete "' . $quiz['prerequisite_module_title'] . '" first';
        } else {
            $prerequisite_message = '✓ Prerequisite completed';
        }
    }
    
    // Display logic:
    ?>
    <div class="quiz-card <?= !$can_access ? 'opacity-60' : '' ?>">
        <h3><?= htmlspecialchars($quiz['title']) ?></h3>
        
        <?php if ($quiz['prerequisite_module_id']): ?>
            <div class="<?= $can_access ? 'text-green-600' : 'text-amber-600' ?> text-sm">
                <i class="fas fa-<?= $can_access ? 'check-circle' : 'lock' ?>"></i>
                <?= htmlspecialchars($prerequisite_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($can_access): ?>
            <a href="take_quiz.php?quiz_id=<?= $quiz['id'] ?>" 
               class="btn btn-primary">Take Quiz</a>
        <?php else: ?>
            <button class="btn btn-disabled" disabled>
                <i class="fas fa-lock mr-2"></i>
                Locked
            </button>
        <?php endif; ?>
    </div>
    <?php
}
?>