<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// ✅ Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    http_response_code(403);
    echo '<div class="text-center py-12"><p class="text-red-600">Access denied</p></div>';
    exit();
}

$professorId = $_SESSION['user_id'];
$quizId = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if ($quizId <= 0) {
    echo '<div class="text-center py-12"><p class="text-red-600">Invalid quiz ID</p></div>';
    exit();
}

// Verify quiz belongs to professor
$stmt = $conn->prepare("SELECT title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quizId, $professorId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo '<div class="text-center py-12"><p class="text-red-600">Quiz not found or access denied</p></div>';
    exit();
}

// ✅ FIX: Get total possible points for the quiz
$stmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) as total_points FROM questions WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$totalPoints = floatval($stmt->fetchColumn());

// Fetch participants grouped by section
$stmt = $conn->prepare("
    SELECT DISTINCT
        u.id, u.firstname, u.lastname, u.email, u.profile_pic, u.gender, u.section,
        MAX(qa.score) as best_score_raw,
        COUNT(qa.id) as attempts,
        MAX(qa.attempted_at) as last_attempt
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    WHERE qa.quiz_id = ?
    GROUP BY u.id
    ORDER BY u.section ASC, best_score_raw DESC, u.lastname ASC
");
$stmt->execute([$quizId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ FIX: Calculate percentage for each participant
foreach ($participants as &$participant) {
    $participant['best_score'] = ($totalPoints > 0) 
        ? ($participant['best_score_raw'] / $totalPoints) * 100 
        : 0;
}
unset($participant);

// Group participants by section
$participantsBySection = [];
foreach ($participants as $participant) {
    $section = $participant['section'] ?? 'No Section';
    if (!isset($participantsBySection[$section])) {
        $participantsBySection[$section] = [];
    }
    $participantsBySection[$section][] = $participant;
}
?>

<?php if (count($participants) > 0): ?>
    <!-- Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Total Students</p>
                    <p class="text-2xl font-bold text-blue-900"><?= count($participants) ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-green-600 uppercase tracking-wide">Average Score</p>
                    <p class="text-2xl font-bold text-green-900"><?= number_format(array_sum(array_column($participants, 'best_score')) / count($participants), 1) ?>%</p>
                </div>
                <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-line text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-purple-600 uppercase tracking-wide">Sections</p>
                    <p class="text-2xl font-bold text-purple-900"><?= count($participantsBySection) ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-200 rounded-full flex items-center justify-center">
                    <i class="fas fa-layer-group text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Participants by Section -->
    <?php foreach ($participantsBySection as $section => $sectionParticipants): ?>
    <div class="mb-6">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-3 rounded-t-lg">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-lg">
                    <i class="fas fa-graduation-cap mr-2"></i>
                    <?= htmlspecialchars($section) ?>
                </h3>
                <span class="bg-white/20 px-3 py-1 rounded-full text-sm font-semibold">
                    <?= count($sectionParticipants) ?> student<?= count($sectionParticipants) != 1 ? 's' : '' ?>
                </span>
            </div>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-b-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Best Score</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Attempts</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Last Attempt</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    <?php foreach ($sectionParticipants as $participant): 
                        $score = $participant['best_score'];
                        if ($score >= 90) {
                            $scoreClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                        } elseif ($score >= 75) {
                            $scoreClass = 'bg-green-100 text-green-800 border-green-300';
                        } elseif ($score >= 60) {
                            $scoreClass = 'bg-yellow-100 text-yellow-800 border-yellow-300';
                        } else {
                            $scoreClass = 'bg-red-100 text-red-800 border-red-300';
                        }
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars(getProfilePicture($participant, "../")) ?>" 
                                     alt="Profile" 
                                     class="w-10 h-10 rounded-full object-cover border-2 border-gray-200">
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">
                                        <?= htmlspecialchars($participant['firstname'] . ' ' . $participant['lastname']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($participant['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold border <?= $scoreClass ?>">
                                <?= number_format($score, 1) ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm font-semibold text-gray-700"><?= $participant['attempts'] ?> time<?= $participant['attempts'] != 1 ? 's' : '' ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm text-gray-600">
                                <?= $participant['last_attempt'] ? date('M j, Y g:i A', strtotime($participant['last_attempt'])) : '—' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="openAttemptsModal(<?= $quizId ?>, <?= $participant['id'] ?>, '<?= htmlspecialchars(addslashes($participant['firstname'] . ' ' . $participant['lastname'])) ?>')" 
                                    class="inline-flex items-center px-3 py-1.5 bg-primary-50 text-primary-700 rounded-lg hover:bg-primary-100 font-medium text-sm transition-colors">
                                <i class="fas fa-eye mr-1"></i>
                                View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

<?php else: ?>
<div class="text-center py-12">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
        <i class="fas fa-users text-3xl text-gray-300"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">No participants yet</h3>
    <p class="text-gray-500 text-sm">No students have taken this quiz yet</p>
</div>
<?php endif; ?>