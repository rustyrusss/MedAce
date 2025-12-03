<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_conn.php';

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    http_response_code(403);
    echo '<div class="text-center py-8"><p class="text-red-600">Access denied</p></div>';
    exit();
}

$professorId = $_SESSION['user_id'];
$attemptId = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

if ($attemptId <= 0) {
    echo '<div class="text-center py-8"><p class="text-red-600">Invalid attempt ID</p></div>';
    exit();
}

// Verify attempt belongs to professor's quiz
$stmt = $conn->prepare("
    SELECT qa.*, q.professor_id 
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.id = ? AND q.professor_id = ?
");
$stmt->execute([$attemptId, $professorId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    echo '<div class="text-center py-8"><p class="text-red-600">Attempt not found</p></div>';
    exit();
}

// Fetch student answers with question details
$stmt = $conn->prepare("
    SELECT 
        sa.*,
        q.question_text,
        q.question_type,
        q.options,
        q.correct_answer,
        q.points
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    WHERE sa.attempt_id = ?
    ORDER BY q.id ASC
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($answers) === 0) {
    echo '<div class="text-center py-8"><p class="text-gray-500">No answers found</p></div>';
    exit();
}
?>

<div class="space-y-4">
    <?php foreach ($answers as $index => $answer): 
        $questionNum = $index + 1;
        $isEssay = ($answer['question_type'] === 'essay');
        $isShortAnswer = ($answer['question_type'] === 'short_answer');
        $isMultipleChoice = ($answer['question_type'] === 'multiple_choice');
        
        // Determine if correct (for multiple choice)
        $isCorrect = false;
        if ($isMultipleChoice) {
            $isCorrect = (trim(strtolower($answer['answer_text'])) === trim(strtolower($answer['correct_answer'])));
        }
    ?>
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <!-- Question Header -->
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-600 text-white font-semibold text-sm">
                            <?= $questionNum ?>
                        </span>
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?= match($answer['question_type']) {
                            'multiple_choice' => 'bg-blue-100 text-blue-700',
                            'essay' => 'bg-purple-100 text-purple-700',
                            'short_answer' => 'bg-green-100 text-green-700',
                            default => 'bg-gray-100 text-gray-700'
                        } ?>">
                            <?= ucwords(str_replace('_', ' ', $answer['question_type'])) ?>
                        </span>
                        <span class="text-sm text-gray-600">
                            (<?= $answer['points'] ?> point<?= $answer['points'] != 1 ? 's' : '' ?>)
                        </span>
                    </div>
                    <p class="text-sm text-gray-900 font-medium"><?= nl2br(htmlspecialchars($answer['question_text'])) ?></p>
                </div>
                <?php if ($isMultipleChoice): ?>
                    <span class="ml-4 inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?= $isCorrect ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <i class="fas fa-<?= $isCorrect ? 'check' : 'times' ?> mr-1"></i>
                        <?= $isCorrect ? 'Correct' : 'Incorrect' ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Answer Section -->
        <div class="p-4">
            <?php if ($isMultipleChoice): ?>
                <!-- Multiple Choice Answer -->
                <?php
                    $options = json_decode($answer['options'], true);
                    $studentAnswer = $answer['answer_text'] ? trim($answer['answer_text']) : '';
                    $correctAnswer = $answer['correct_answer'] ? trim($answer['correct_answer']) : '';
                ?>
                <div class="space-y-2">
                    <?php foreach ($options as $option): ?>
                        <?php 
                            // Handle if option is an array or string
                            $optionText = is_array($option) ? $option['text'] : $option;
                            $isStudentChoice = (trim($optionText) === $studentAnswer);
                            $isCorrectChoice = (trim($optionText) === $correctAnswer);
                        ?>
                        <div class="flex items-center gap-2 p-3 rounded-lg <?= 
                            $isCorrectChoice ? 'bg-green-50 border-2 border-green-300' : 
                            ($isStudentChoice ? 'bg-red-50 border-2 border-red-300' : 'bg-gray-50 border border-gray-200') 
                        ?>">
                            <i class="fas fa-<?= $isCorrectChoice ? 'check-circle text-green-600' : ($isStudentChoice ? 'times-circle text-red-600' : 'circle text-gray-400') ?>"></i>
                            <span class="text-sm <?= ($isStudentChoice || $isCorrectChoice) ? 'font-semibold' : '' ?>"><?= htmlspecialchars($optionText) ?></span>
                            <?php if ($isStudentChoice && !$isCorrectChoice): ?>
                                <span class="ml-auto text-xs text-red-600 font-medium">Student's Answer</span>
                            <?php elseif ($isCorrectChoice): ?>
                                <span class="ml-auto text-xs text-green-600 font-medium">Correct Answer</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($isEssay || $isShortAnswer): ?>
                <!-- Essay/Short Answer with Grading -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-2">Student's Answer:</label>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                            <?php if (!empty($answer['answer_text'])): ?>
                                <p class="text-sm text-gray-900 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($answer['answer_text'])) ?></p>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 italic">No answer provided</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Grading Section -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-1">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-star text-yellow-500 mr-1"></i>
                                    Award Points:
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" 
                                           id="points-<?= $answer['id'] ?>" 
                                           value="<?= intval($answer['points_earned'] ?? 0) ?>"
                                           min="0" 
                                           max="<?= $answer['points'] ?>" 
                                           step="1"
                                           class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                           onchange="autoSaveGrade(<?= $answer['id'] ?>, <?= $attemptId ?>)">
                                    <span class="text-sm text-gray-600">/ <?= $answer['points'] ?> points</span>
                                    <span id="save-status-<?= $answer['id'] ?>" class="text-xs text-gray-500 ml-2"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Feedback Section -->
                        <div class="mt-3">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">
                                <i class="fas fa-comment text-primary-500 mr-1"></i>
                                Feedback (Optional):
                            </label>
                            <textarea id="feedback-<?= $answer['id'] ?>" 
                                      rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm resize-none"
                                      placeholder="Provide feedback to the student..."
                                      onchange="autoSaveGrade(<?= $answer['id'] ?>, <?= $attemptId ?>)"><?= htmlspecialchars($answer['feedback'] ?? '') ?></textarea>
                        </div>

                        <div id="grade-status-<?= $answer['id'] ?>" class="mt-2 text-sm"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Recalculate Score Button -->
    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-primary-50 to-blue-50 border border-primary-200 rounded-lg">
        <div>
            <p class="text-sm font-semibold text-gray-900">Recalculate Final Score</p>
            <p class="text-xs text-gray-600">Grades are saved automatically. Click to update the total score.</p>
        </div>
        <button onclick="recalculateScore(<?= $attemptId ?>)" 
                id="recalc-btn-<?= $attemptId ?>"
                class="px-6 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-semibold text-sm transition-colors shadow-sm">
            <i class="fas fa-calculator mr-2"></i>
            Recalculate Score
        </button>
    </div>
</div>

<script>
function updateGradeButton(answerId) {
    const saveBtn = document.getElementById(`save-btn-${answerId}`);
    if (saveBtn) {
        saveBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
        saveBtn.classList.add('bg-orange-600', 'hover:bg-orange-700', 'animate-pulse');
        saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Unsaved';
    }
}

function saveGrade(answerId, attemptId) {
    const points = document.getElementById(`points-${answerId}`).value;
    const feedback = document.getElementById(`feedback-${answerId}`).value;
    const statusDiv = document.getElementById(`grade-status-${answerId}`);
    const saveBtn = document.getElementById(`save-btn-${answerId}`);
    
    // Show loading
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-primary-600"></i> Saving...';
    saveBtn.disabled = true;
    
    fetch('save_grade_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            answer_id: answerId,
            attempt_id: attemptId,
            points: parseFloat(points),
            feedback: feedback
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Grade saved successfully!</span>';
            saveBtn.classList.remove('bg-orange-600', 'hover:bg-orange-700', 'animate-pulse');
            saveBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            saveBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Saved';
            
            setTimeout(() => {
                statusDiv.innerHTML = '';
                saveBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                saveBtn.classList.add('bg-primary-600', 'hover:bg-primary-700');
                saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Grade';
                saveBtn.disabled = false;
            }, 2000);
        } else {
            statusDiv.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>' + (data.error || 'Failed to save') + '</span>';
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>Error saving grade</span>';
        saveBtn.disabled = false;
    });
}

function recalculateScore(attemptId) {
    const recalcBtn = document.getElementById(`recalc-btn-${attemptId}`);
    const originalHTML = recalcBtn.innerHTML;
    
    recalcBtn.disabled = true;
    recalcBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Calculating...';
    
    fetch('recalculate_score_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            attempt_id: attemptId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            recalcBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Score Updated: ' + data.new_score + '%';
            recalcBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
            recalcBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            
            // Reload the participants modal after a delay
            setTimeout(() => {
                // Refresh the parent modal if needed
                const participantsModal = window.parent.document.getElementById('participantsContent');
                if (participantsModal) {
                    // Trigger a refresh of the participants list
                    window.location.reload();
                }
            }, 1500);
        } else {
            recalcBtn.innerHTML = '<i class="fas fa-times mr-2"></i>' + (data.error || 'Failed');
            recalcBtn.classList.add('bg-red-600');
            setTimeout(() => {
                recalcBtn.innerHTML = originalHTML;
                recalcBtn.classList.remove('bg-red-600');
                recalcBtn.disabled = false;
            }, 2000);
        }
    })
    .catch(error => {
        recalcBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Error';
        setTimeout(() => {
            recalcBtn.innerHTML = originalHTML;
            recalcBtn.disabled = false;
        }, 2000);
    });
}
</script>