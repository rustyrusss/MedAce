<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// Access control
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
    echo '<div class="text-center py-12"><p class="text-red-600">Quiz not found</p></div>';
    exit();
}

// Fetch participants
$stmt = $conn->prepare("
    SELECT DISTINCT
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.profile_pic,
        u.gender,
        MAX(qa.score) as best_score,
        COUNT(qa.id) as attempts,
        MAX(qa.attempted_at) as last_attempt
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    WHERE qa.quiz_id = ?
    GROUP BY u.id
    ORDER BY best_score DESC, u.lastname ASC
");
$stmt->execute([$quizId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-4">
    <p class="text-sm text-gray-600">
        <i class="fas fa-users mr-1"></i>
        Total Participants: <span class="font-semibold"><?= count($participants) ?></span>
    </p>
</div>

<?php if (count($participants) > 0): ?>
<div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Student</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Best Score</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Attempts</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Last Attempt</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($participants as $participant): 
                $profilePic = getProfilePicture($participant, "../");
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center">
                        <img src="<?= htmlspecialchars($profilePic) ?>" 
                             alt="Profile" class="w-8 h-8 rounded-full mr-3 object-cover">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($participant['firstname'] . ' ' . $participant['lastname']) ?>
                            </p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($participant['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <?php
                        $score = $participant['best_score'];
                        if ($score >= 90) {
                            $badgeClass = 'bg-emerald-100 text-emerald-800';
                        } elseif ($score >= 75) {
                            $badgeClass = 'bg-green-100 text-green-800';
                        } elseif ($score >= 60) {
                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                        } else {
                            $badgeClass = 'bg-red-100 text-red-800';
                        }
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>">
                        <?= number_format($participant['best_score'], 1) ?>%
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">
                    <span class="inline-flex items-center">
                        <i class="fas fa-redo text-xs mr-1"></i>
                        <?= $participant['attempts'] ?> time<?= $participant['attempts'] != 1 ? 's' : '' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">
                    <?php if ($participant['last_attempt']): ?>
                        <span class="inline-flex items-center">
                            <i class="fas fa-calendar text-xs mr-1"></i>
                            <?= date('M j, Y', strtotime($participant['last_attempt'])) ?>
                        </span>
                        <br>
                        <span class="text-xs text-gray-400">
                            <?= date('g:i A', strtotime($participant['last_attempt'])) ?>
                        </span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
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

<!-- Summary Stats -->
<div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
    <?php
        $totalAttempts = array_sum(array_column($participants, 'attempts'));
        $avgScore = count($participants) > 0 ? array_sum(array_column($participants, 'best_score')) / count($participants) : 0;
        $passRate = count($participants) > 0 ? (count(array_filter($participants, fn($p) => $p['best_score'] >= 75)) / count($participants)) * 100 : 0;
    ?>
    <div class="bg-blue-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-blue-700"><?= $totalAttempts ?></div>
        <div class="text-xs text-blue-600 uppercase tracking-wide">Total Attempts</div>
    </div>
    <div class="bg-green-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-green-700"><?= number_format($avgScore, 1) ?>%</div>
        <div class="text-xs text-green-600 uppercase tracking-wide">Average Score</div>
    </div>
    <div class="bg-purple-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-purple-700"><?= number_format($passRate, 1) ?>%</div>
        <div class="text-xs text-purple-600 uppercase tracking-wide">Pass Rate (≥75%)</div>
    </div>
</div>

<?php else: ?>
<div class="text-center py-12">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
        <i class="fas fa-users text-3xl text-gray-300"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">No participants yet</h3>
    <p class="text-gray-500 text-sm">No students have taken this quiz yet</p>
</div>
<?php endif; ?><?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// Access control
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
    echo '<div class="text-center py-12"><p class="text-red-600">Quiz not found</p></div>';
    exit();
}

// Fetch participants
$stmt = $conn->prepare("
    SELECT DISTINCT
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.profile_pic,
        u.gender,
        MAX(qa.score) as best_score,
        COUNT(qa.id) as attempts,
        MAX(qa.attempted_at) as last_attempt
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    WHERE qa.quiz_id = ?
    GROUP BY u.id
    ORDER BY best_score DESC, u.lastname ASC
");
$stmt->execute([$quizId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-4">
    <p class="text-sm text-gray-600">
        <i class="fas fa-users mr-1"></i>
        Total Participants: <span class="font-semibold"><?= count($participants) ?></span>
    </p>
</div>

<?php if (count($participants) > 0): ?>
<div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Student</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Best Score</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Attempts</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Last Attempt</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($participants as $participant): 
                $profilePic = getProfilePicture($participant, "../");
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center">
                        <img src="<?= htmlspecialchars($profilePic) ?>" 
                             alt="Profile" class="w-8 h-8 rounded-full mr-3 object-cover">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($participant['firstname'] . ' ' . $participant['lastname']) ?>
                            </p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($participant['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <?php
                        $score = $participant['best_score'];
                        if ($score >= 90) {
                            $badgeClass = 'bg-emerald-100 text-emerald-800';
                        } elseif ($score >= 75) {
                            $badgeClass = 'bg-green-100 text-green-800';
                        } elseif ($score >= 60) {
                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                        } else {
                            $badgeClass = 'bg-red-100 text-red-800';
                        }
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>">
                        <?= number_format($participant['best_score'], 1) ?>%
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">
                    <span class="inline-flex items-center">
                        <i class="fas fa-redo text-xs mr-1"></i>
                        <?= $participant['attempts'] ?> time<?= $participant['attempts'] != 1 ? 's' : '' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">
                    <?php if ($participant['last_attempt']): ?>
                        <span class="inline-flex items-center">
                            <i class="fas fa-calendar text-xs mr-1"></i>
                            <?= date('M j, Y', strtotime($participant['last_attempt'])) ?>
                        </span>
                        <br>
                        <span class="text-xs text-gray-400">
                            <?= date('g:i A', strtotime($participant['last_attempt'])) ?>
                        </span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
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

<!-- Summary Stats -->
<div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
    <?php
        $totalAttempts = array_sum(array_column($participants, 'attempts'));
        $avgScore = count($participants) > 0 ? array_sum(array_column($participants, 'best_score')) / count($participants) : 0;
        $passRate = count($participants) > 0 ? (count(array_filter($participants, fn($p) => $p['best_score'] >= 75)) / count($participants)) * 100 : 0;
    ?>
    <div class="bg-blue-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-blue-700"><?= $totalAttempts ?></div>
        <div class="text-xs text-blue-600 uppercase tracking-wide">Total Attempts</div>
    </div>
    <div class="bg-green-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-green-700"><?= number_format($avgScore, 1) ?>%</div>
        <div class="text-xs text-green-600 uppercase tracking-wide">Average Score</div>
    </div>
    <div class="bg-purple-50 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-purple-700"><?= number_format($passRate, 1) ?>%</div>
        <div class="text-xs text-purple-600 uppercase tracking-wide">Pass Rate (≥75%)</div>
    </div>
</div>

<?php else: ?>
<div class="text-center py-12">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
        <i class="fas fa-users text-3xl text-gray-300"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">No participants yet</h3>
    <p class="text-gray-500 text-sm">No students have taken this quiz yet</p>
</div>
<?php endif; ?>