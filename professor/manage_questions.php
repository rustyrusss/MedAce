<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];

// ========== PROCESS FORM SUBMISSION (ADD QUESTION) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_text']) && !isset($_POST['question_id'])) {
    try {
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $question_text = trim($_POST['question_text']);
        $question_type = trim($_POST['question_type']);
        $options_json = $_POST['options_json'] ?? '[]';
        $correct_answer_value = $_POST['correct_answer_value'] ?? '';
        $question_points = isset($_POST['question_points']) ? intval($_POST['question_points']) : 1;
        
        if (empty($quiz_id) || empty($question_text) || empty($question_type)) {
            throw new Exception("Please fill in all required fields.");
        }
        
        $stmt = $conn->prepare("SELECT professor_id FROM quizzes WHERE id = ?");
        $stmt->execute([$quiz_id]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz || $quiz['professor_id'] != $professor_id) {
            throw new Exception("Invalid quiz or you don't have permission to modify it.");
        }
        
        $options = json_decode($options_json, true);
        if ($options === null) {
            $options = [];
        }
        
        $options_db = json_encode($options);
        
        $conn->beginTransaction();
        
        // Modified INSERT to include points
        $stmt = $conn->prepare("
            INSERT INTO questions (quiz_id, question_text, question_type, options, correct_answer, time_limit, points) 
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ");
        
        $stmt->execute([
            $quiz_id,
            $question_text,
            $question_type,
            $options_db,
            $correct_answer_value,
            $question_points
        ]);
        
        $question_id = $conn->lastInsertId();
        
        if (in_array($question_type, ['multiple_choice', 'checkbox', 'dropdown', 'true_false']) && !empty($options)) {
            $stmt = $conn->prepare("
                INSERT INTO answers (question_id, answer_text, is_correct) 
                VALUES (?, ?, ?)
            ");
            
            $correct_answers = explode(',', $correct_answer_value);
            $correct_answers = array_map('trim', $correct_answers);
            
            foreach ($options as $option) {
                $is_correct = in_array($option['letter'], $correct_answers) ? 1 : 0;
                $stmt->execute([
                    $question_id,
                    $option['text'],
                    $is_correct
                ]);
            }
        }
        
        $conn->commit();
        
        $_SESSION['success'] = "Question added successfully! 🎉";
        header("Location: manage_questions.php?quiz_id=" . $quiz_id);
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Error adding question: " . $e->getMessage());
        $_SESSION['error'] = "Error adding question: " . $e->getMessage();
    }
}

// ========== PROCESS EDIT QUESTION ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id']) && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $question_id = intval($_POST['question_id']);
        $quiz_id = intval($_POST['quiz_id']);
        $question_text = trim($_POST['question_text']);
        $question_type = trim($_POST['question_type']);
        $options_json = $_POST['options_json'] ?? '[]';
        $correct_answer_value = $_POST['correct_answer_value'] ?? '';
        $question_points = isset($_POST['question_points_edit']) ? intval($_POST['question_points_edit']) : 1;
        
        // Verify ownership
        $stmt = $conn->prepare("
            SELECT q.id FROM questions q 
            JOIN quizzes qz ON q.quiz_id = qz.id 
            WHERE q.id = ? AND qz.professor_id = ?
        ");
        $stmt->execute([$question_id, $professor_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid question or permission denied.");
        }
        
        $options = json_decode($options_json, true) ?? [];
        $options_db = json_encode($options);
        
        $conn->beginTransaction();
        
        // Update question with points
        $stmt = $conn->prepare("
            UPDATE questions 
            SET question_text = ?, question_type = ?, options = ?, correct_answer = ?, points = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $question_text,
            $question_type,
            $options_db,
            $correct_answer_value,
            $question_points,
            $question_id
        ]);
        
        // Delete old answers and insert new ones
        $stmt = $conn->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        if (in_array($question_type, ['multiple_choice', 'checkbox', 'dropdown', 'true_false']) && !empty($options)) {
            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
            $correct_answers = array_map('trim', explode(',', $correct_answer_value));
            
            foreach ($options as $option) {
                $is_correct = in_array($option['letter'], $correct_answers) ? 1 : 0;
                $stmt->execute([$question_id, $option['text'], $is_correct]);
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Question updated successfully! ✓";
        header("Location: manage_questions.php?quiz_id=" . $quiz_id);
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Error updating question: " . $e->getMessage();
    }
}

// ========== GET QUIZ INFO ==========
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if (!$quiz_id) {
    header("Location: dashboard_professor.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: dashboard_professor.php");
    exit();
}

$prof_stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$prof_stmt->execute([$professor_id]);
$prof = $prof_stmt->fetch(PDO::FETCH_ASSOC);

$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";
$profilePic = getProfilePicture($prof, "../");

$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Questions - <?= htmlspecialchars($quiz['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        body { background-color: #cce7ea; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-gray-800 font-sans">

<div x-data="{ 
    sidebarOpen: false, 
    showAddForm: false, 
    currentQuestionIndex: 0,
    editMode: false
}" class="flex min-h-screen">

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" 
           class="bg-white shadow-sm border-r border-gray-200 transition-all duration-300 relative flex flex-col z-30">

        <div class="flex items-center justify-between p-3 border-b">
            <div class="flex items-center gap-2">
                <img src="<?= htmlspecialchars($profilePic) ?>" 
                     class="w-8 h-8 rounded-full object-cover border" />
                <span x-show="sidebarOpen" class="text-sm font-semibold text-sky-700">
                    <?= htmlspecialchars(ucwords(strtolower($profName))) ?>
                </span>
            </div>
            <button @click="sidebarOpen = !sidebarOpen"
                    class="p-1 rounded-md text-gray-600 hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <nav class="flex-1 mt-3 px-1 space-y-1">
            <a href="dashboard.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 text-xl flex justify-center">🏠</div>
                <span x-show="sidebarOpen" class="font-medium">Dashboard</span>
            </a>
            <a href="manage_modules.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 text-xl flex justify-center">📘</div>
                <span x-show="sidebarOpen" class="font-medium">Modules</span>
            </a>
            <a href="manage_quizzes.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 text-xl flex justify-center">📝</div>
                <span x-show="sidebarOpen" class="font-medium">Quizzes</span>
            </a>
            <a href="student_progress.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 text-xl flex justify-center">👨‍🎓</div>
                <span x-show="sidebarOpen" class="font-medium">Student Progress</span>
            </a>
        </nav>

        <div class="px-2 py-4 border-t">
            <a href="../actions/logout_action.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-red-600 hover:bg-red-50 transition">
                <div class="w-8 text-xl flex justify-center">🚪</div>
                <span x-show="sidebarOpen" class="font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <div class="max-w-5xl mx-auto">
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900 font-bold">✕</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900 font-bold">✕</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Header -->
            <div class="bg-white p-6 rounded-xl shadow mb-6">
                <h1 class="text-2xl font-bold mb-4">
                    Manage Questions — <?= htmlspecialchars($quiz['title']) ?>
                </h1>
                
                <div class="flex gap-3">
                    <button @click="showAddForm = !showAddForm; editMode = false" 
                            class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition">
                        <span x-show="!showAddForm">➕ Add New Question</span>
                        <span x-show="showAddForm">❌ Cancel</span>
                    </button>
                    
                    <a href="dashboard.php" 
                       class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400 transition">
                        ⬅ Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Add Question Form -->
            <div x-show="showAddForm" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="bg-white p-6 rounded-xl shadow mb-6">
                
                <h2 class="text-xl font-bold mb-4">Add New Question</h2>

                <form id="questionForm" method="POST" onsubmit="return prepareOptions()">
                    <input type="hidden" name="quiz_id" value="<?= htmlspecialchars($quiz_id) ?>">
                    <input type="hidden" name="options_json" id="options_json">
                    <input type="hidden" name="action" value="add">

                    <div class="space-y-4">
                        <div>
                            <label for="question_text" class="block text-sm font-medium mb-1">Question</label>
                            <textarea id="question_text" name="question_text" required class="w-full p-2 border rounded" rows="3" placeholder="Enter your question here..."></textarea>
                        </div>

                        <div>
                            <label for="question_type" class="block text-sm font-medium mb-1">Question Type</label>
                            <select id="question_type" name="question_type" class="w-full p-2 border rounded" onchange="onTypeChange()" required>
                                <option value="short_answer">Short Answer</option>
                                <option value="multiple_choice" selected>Multiple Choice</option>
                                <option value="checkbox">Checkboxes</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="true_false">True / False</option>
                                <option value="essay">Essay</option>
                            </select>
                        </div>

                        <div id="options_area" class="space-y-2">
                            <label class="block text-sm font-medium mb-1">Options</label>
                            <div id="options_list" class="space-y-2"></div>
                            <div class="flex gap-2">
                                <button type="button" onclick="clearOptions()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Clear</button>
                                <p class="text-sm text-gray-500 ml-auto">Mark the correct answer(s)</p>
                            </div>
                        </div>

                        <div id="text_answer_area" class="space-y-3 hidden">
                            <div>
                                <label class="block text-sm font-medium mb-1">Expected Answer (optional)</label>
                                <input id="expected_answer" name="expected_answer" type="text" class="w-full p-2 border rounded" placeholder="Optional: enter expected answer for reference (not required)">
                                <p class="text-sm text-gray-500">Enter the expected answer (useful as a grading note). Not required for saving.</p>
                            </div>
                            
                            <div class="bg-sky-50 p-4 rounded-lg border border-sky-200">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    🎯 Points for this Question
                                </label>
                                <input type="number" 
                                       id="question_points" 
                                       name="question_points" 
                                       min="1" 
                                       max="100" 
                                       value="10" 
                                       class="w-full px-3 py-2 border-2 border-sky-300 rounded-lg focus:ring-2 focus:ring-sky-400 outline-none font-semibold text-lg">
                                <p class="text-xs text-gray-600 mt-2">
                                    💡 Set the point value for this question (1-100 points)
                                </p>
                            </div>
                        </div>

                        <div id="text_note" class="p-3 bg-blue-50 border border-blue-100 rounded text-sm text-blue-800 hidden">
                            Students will see a text box for this question type. Essay/Short Answer require manual grading.
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">💾 Save Question</button>
                            <button type="button" @click="showAddForm = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Questions Viewer/Editor -->
            <div class="bg-white p-6 rounded-xl shadow">
                <h2 class="text-xl font-semibold mb-4">Existing Questions (<?= count($questions) ?>)</h2>

                <?php if (empty($questions)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📝</div>
                        <p class="text-gray-500 text-lg mb-4">No questions added yet.</p>
                        <button @click="showAddForm = true" 
                                class="px-6 py-3 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition">
                            ➕ Add Your First Question
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Quick Navigation -->
                    <div class="mb-6 pb-4 border-b bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-semibold text-gray-700 mb-3">Quick Navigation:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($questions as $idx => $question): ?>
                                <button 
                                    @click="currentQuestionIndex = <?= $idx ?>; editMode = false; showAddForm = false"
                                    :class="currentQuestionIndex === <?= $idx ?> ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:border-blue-500 hover:bg-blue-50'"
                                    class="w-12 h-12 flex items-center justify-center rounded-full border-2 transition font-bold">
                                    <?= $idx + 1 ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Click a number to view/edit that question</p>
                    </div>

                    <!-- Question Display Area -->
                    <?php foreach ($questions as $index => $q): ?>
                        <?php
                            $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY id ASC");
                            $stmt->execute([$q['id']]);
                            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $points = isset($q['points']) ? intval($q['points']) : 1;
                        ?>
                        
                        <div x-show="currentQuestionIndex === <?= $index ?>" x-cloak
                             class="border-2 border-gray-200 rounded-xl p-6"
                             x-data="{
                                answers: <?= json_encode($answers) ?>
                             }">
                            
                            <!-- View Mode -->
                            <div x-show="!editMode">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="flex items-start gap-4 flex-1">
                                        <div class="flex-shrink-0 w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-xl">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-3">
                                                <span class="inline-block text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-700 font-medium">
                                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($q['question_type']))) ?>
                                                </span>
                                                <?php if (in_array($q['question_type'], ['short_answer', 'essay'])): ?>
                                                    <span class="inline-block text-xs px-3 py-1 rounded-full bg-amber-100 text-amber-700 font-bold">
                                                        🎯 <?= $points ?> point<?= $points != 1 ? 's' : '' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xl font-semibold text-gray-900 leading-relaxed">
                                                <?= htmlspecialchars($q['question_text']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Answers -->
                                <?php if (!empty($answers)): ?>
                                    <div class="ml-16 mb-6">
                                        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Answer Choices:</p>
                                        <div class="space-y-3">
                                            <?php 
                                            $labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                            foreach ($answers as $idx => $a): 
                                            ?>
                                                <div class="flex items-center gap-4 p-3 rounded-lg <?= $a['is_correct'] ? 'bg-green-50 border-2 border-green-300' : 'bg-gray-50 border border-gray-200' ?>">
                                                    <span class="flex-shrink-0 w-9 h-9 rounded-full border-2 <?= $a['is_correct'] ? 'border-green-500 bg-green-100 text-green-700' : 'border-gray-300 bg-white text-gray-600' ?> flex items-center justify-center font-bold">
                                                        <?= $labels[$idx] ?>
                                                    </span>
                                                    <span class="flex-1 <?= $a['is_correct'] ? 'text-green-900 font-semibold' : 'text-gray-700' ?> text-lg">
                                                        <?= htmlspecialchars($a['answer_text']) ?>
                                                    </span>
                                                    <?= $a['is_correct'] ? '<span class="flex-shrink-0 text-xs bg-green-600 text-white px-3 py-1.5 rounded-full font-semibold">✓ CORRECT</span>' : '' ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="ml-16 mb-6 bg-amber-50 rounded-lg p-4 border border-amber-200">
                                        <p class="text-sm text-amber-800">
                                            <?php if (in_array($q['question_type'], ['short_answer', 'paragraph', 'essay'])): ?>
                                                <span class="font-semibold">📝 Open-ended question</span> — Students will provide their own answer
                                            <?php else: ?>
                                                <span class="font-semibold">⚠️ No answer options found</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <div class="ml-16 flex gap-3">
                                    <button @click="editMode = true" 
                                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium shadow-sm">
                                        ✏️ Edit Question
                                    </button>
                                    <a href="../actions/delete_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>"
                                       onclick="return confirm('⚠️ Delete this question permanently?\n\nThis action cannot be undone!')"
                                       class="px-5 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition font-medium shadow-sm">
                                        🗑 Delete
                                    </a>
                                </div>
                            </div>

                            <!-- Edit Mode -->
                            <div x-show="editMode" x-cloak class="bg-orange-50 rounded-xl p-6 border-2 border-orange-300">
                                  <form method="POST" onsubmit="return prepareEditOptions(<?= $q['id'] ?>)">
                                       <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                       <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
                                       <input type="hidden" name="options_json" id="edit_options_json_<?= $q['id'] ?>">
                                       <input type="hidden" name="action" value="edit">
                                       <input type="hidden" name="question_type" value="<?= htmlspecialchars($q['question_type']) ?>">

                                       <div class="flex items-center gap-3 mb-6">
                                           <div class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold text-lg">
                                               <?= $index + 1 ?>
                                           </div>
                                           <span class="text-sm px-4 py-1.5 rounded-full bg-orange-600 text-white font-semibold">
                                               ✏️ EDITING MODE
                                           </span>
                                       </div>

                                       <div class="space-y-4">
                                           <div>
                                               <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text</label>
                                               <textarea name="question_text" required 
                                                         class="w-full p-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition text-lg" 
                                                         rows="3"><?= htmlspecialchars($q['question_text']) ?></textarea>
                                           </div>

                                           <div>
                                               <label class="block text-sm font-semibold text-gray-700 mb-2">Question Type</label>
                                               <select class="w-full p-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition" disabled>
                                                   <option value="short_answer" <?= $q['question_type'] === 'short_answer' ? 'selected' : '' ?>>Short Answer</option>
                                                   <option value="paragraph" <?= $q['question_type'] === 'paragraph' ? 'selected' : '' ?>>Paragraph</option>
                                                   <option value="multiple_choice" <?= $q['question_type'] === 'multiple_choice' ? 'selected' : '' ?>>Multiple Choice</option>
                                                   <option value="checkbox" <?= $q['question_type'] === 'checkbox' ? 'selected' : '' ?>>Checkboxes</option>
                                                   <option value="dropdown" <?= $q['question_type'] === 'dropdown' ? 'selected' : '' ?>>Dropdown</option>
                                                   <option value="true_false" <?= $q['question_type'] === 'true_false' ? 'selected' : '' ?>>True / False</option>
                                                   <option value="essay" <?= $q['question_type'] === 'essay' ? 'selected' : '' ?>>Essay</option>
                                               </select>
                                               <p class="text-xs text-gray-500 mt-1">Question type cannot be changed after creation</p>
                                           </div>

                                           <?php if (!empty($answers)): ?>
                                           <div>
                                               <label class="block text-sm font-semibold text-gray-700 mb-3">Answer Options</label>
                                               <div class="space-y-3" id="edit_options_<?= $q['id'] ?>">
                                                   <?php 
                                                   $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
                                                   foreach ($answers as $idx => $answer): 
                                                   ?>
                                                       <div class="flex items-center gap-3 p-3 bg-white rounded-lg border-2 border-gray-300">
                                                           <span class="w-9 h-9 rounded-full border-2 border-gray-400 flex items-center justify-center font-bold">
                                                               <?= $labels[$idx] ?>
                                                           </span>
                                                           <input type="radio" name="correct_answer_edit_<?= $q['id'] ?>" 
                                                                  value="<?= $labels[$idx] ?>" 
                                                                  <?= $answer['is_correct'] ? 'checked' : '' ?>
                                                                  class="w-5 h-5 text-green-600">
                                                           <input type="text" name="edit_option<?= $idx + 1 ?>" 
                                                                  value="<?= htmlspecialchars($answer['answer_text']) ?>"
                                                                  class="flex-1 p-2 border-2 border-gray-300 rounded focus:border-orange-500 transition"
                                                                  placeholder="Answer option" required>
                                                       </div>
                                                   <?php endforeach; ?>
                                               </div>
                                           </div>
                                           <?php else: ?>
                                           <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-3">
                                               <p class="text-sm text-blue-800">
                                                   <span class="font-semibold">📝 Text-based question</span> — Students will provide their own answer. No options needed.
                                               </p>
                                               <div>
                                                   <label class="block text-sm font-medium mb-1">Expected Answer (optional)</label>
                                                   <input type="text" name="expected_answer_edit_<?= $q['id'] ?>" class="w-full p-2 border rounded" placeholder="Optional expected answer (for teacher reference)">
                                               </div>
                                               <div class="bg-sky-50 p-4 rounded-lg border border-sky-200">
                                                   <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                       🎯 Points for this Question
                                                   </label>
                                                   <input type="number" 
                                                          name="question_points_edit" 
                                                          min="1" 
                                                          max="100" 
                                                          value="<?= $points ?>" 
                                                          class="w-full px-3 py-2 border-2 border-sky-300 rounded-lg focus:ring-2 focus:ring-sky-400 outline-none font-semibold text-lg">
                                                   <p class="text-xs text-gray-600 mt-2">
                                                       💡 Set the point value for this question (1-100 points)
                                                   </p>
                                               </div>
                                           </div>
                                           <?php endif; ?>

                                           <div class="flex gap-3 pt-4">
                                               <button type="submit" 
                                                       class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md">
                                                   💾 Save Changes
                                               </button>
                                               <button type="button" 
                                                       @click="editMode = false" 
                                                       class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-semibold shadow-md">
                                                   ✖ Cancel
                                               </button>
                                           </div>
                                       </div>
                                   </form>
                            </div>

                        </div>
                    <?php endforeach; ?>

                    <!-- Navigation Controls -->
                    <div class="mt-6 flex justify-between items-center">
                        <button 
                            @click="currentQuestionIndex = currentQuestionIndex > 0 ? currentQuestionIndex - 1 : <?= count($questions) - 1 ?>; editMode = false"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition font-medium">
                            ← Previous
                        </button>
                        <span class="text-gray-600 font-medium">
                            Question <span x-text="currentQuestionIndex + 1"></span> of <?= count($questions) ?>
                        </span>
                        <button 
                            @click="currentQuestionIndex = currentQuestionIndex < <?= count($questions) - 1 ?> ? currentQuestionIndex + 1 : 0; editMode = false"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition font-medium">
                            Next →
                        </button>
                    </div>

                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
function onTypeChange() {
    const questionType = document.getElementById('question_type').value;
    const optionsArea = document.getElementById('options_area');
    const textNote = document.getElementById('text_note');
    const textAnswerArea = document.getElementById('text_answer_area');

    if (questionType === 'multiple_choice' || questionType === 'dropdown') {
        optionsArea.classList.remove('hidden');
        textNote.classList.add('hidden');
        textAnswerArea.classList.add('hidden');

        document.getElementById('options_list').innerHTML = `
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">A)</span>
                <input type="radio" name="correct_answer" value="A" class="border rounded p-2" required>
                <input type="text" name="option1" placeholder="Option A" class="w-full p-2 border rounded" required>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">B)</span>
                <input type="radio" name="correct_answer" value="B" class="border rounded p-2">
                <input type="text" name="option2" placeholder="Option B" class="w-full p-2 border rounded" required>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">C)</span>
                <input type="radio" name="correct_answer" value="C" class="border rounded p-2">
                <input type="text" name="option3" placeholder="Option C" class="w-full p-2 border rounded" required>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">D)</span>
                <input type="radio" name="correct_answer" value="D" class="border rounded p-2">
                <input type="text" name="option4" placeholder="Option D" class="w-full p-2 border rounded" required>
            </div>
        `;
    } else if (questionType === 'checkbox') {
        optionsArea.classList.remove('hidden');
        textNote.classList.add('hidden');
        textAnswerArea.classList.add('hidden');

        document.getElementById('options_list').innerHTML = `
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">A)</span>
                <input type="checkbox" name="correct_answers[]" value="A" class="w-5 h-5">
                <input type="text" name="option1" placeholder="Option A" class="w-full p-2 border rounded" required>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">B)</span>
                <input type="checkbox" name="correct_answers[]" value="B" class="w-5 h-5">
                <input type="text" name="option2" placeholder="Option B" class="w-full p-2 border rounded" required>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">C)</span>
                <input type="checkbox" name="correct_answers[]" value="C" class="w-5 h-5">
                <input type="text" name="option3" placeholder="Option C" class="w-full p-2 border rounded" required>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">D)</span>
                <input type="checkbox" name="correct_answers[]" value="D" class="w-5 h-5">
                <input type="text" name="option4" placeholder="Option D" class="w-full p-2 border rounded" required>
            </div>
        `;
    } else if (questionType === 'true_false') {
        optionsArea.classList.remove('hidden');
        textNote.classList.add('hidden');
        textAnswerArea.classList.add('hidden');

        document.getElementById('options_list').innerHTML = `
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">A)</span>
                <input type="radio" name="correct_answer" value="A" checked required>
                <input type="text" name="option1" value="True" class="w-full p-2 border rounded bg-gray-100" readonly>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">B)</span>
                <input type="radio" name="correct_answer" value="B">
                <input type="text" name="option2" value="False" class="w-full p-2 border rounded bg-gray-100" readonly>
            </div>
        `;
    } else {
        // short_answer, essay -> show text area note and points input
        optionsArea.classList.add('hidden');
        textNote.classList.remove('hidden');
        textAnswerArea.classList.remove('hidden');
        document.getElementById('options_list').innerHTML = '';
    }
}

function clearOptions() {
    document.querySelectorAll('#options_list input[type="text"]:not([readonly])').forEach(input => {
        input.value = "";
    });
    document.querySelectorAll('#options_list input[type="radio"], #options_list input[type="checkbox"]').forEach(input => {
        input.checked = false;
    });
}

function prepareOptions() {
    const questionType = document.getElementById('question_type').value;
    
    if (['multiple_choice', 'checkbox', 'dropdown', 'true_false'].includes(questionType)) {
        const options = [];
        const optionInputs = document.querySelectorAll('#options_list input[type="text"]');
        
        optionInputs.forEach((input, index) => {
            if (input.value.trim() !== '') {
                options.push({
                    letter: String.fromCharCode(65 + index),
                    text: input.value.trim()
                });
            }
        });
        
        if (options.length === 0) {
            alert('Please add at least one option.');
            return false;
        }
        
        let correctAnswer = '';
        
        if (questionType === 'checkbox') {
            const checkedBoxes = document.querySelectorAll('input[name="correct_answers[]"]:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one correct answer.');
                return false;
            }
            correctAnswer = Array.from(checkedBoxes).map(cb => cb.value).join(',');
        } else {
            const checkedRadio = document.querySelector('input[name="correct_answer"]:checked');
            if (!checkedRadio) {
                alert('Please select the correct answer.');
                return false;
            }
            correctAnswer = checkedRadio.value;
        }
        
        document.getElementById('options_json').value = JSON.stringify(options);
        
        let correctAnswerInput = document.getElementById('correct_answer_value');
        if (!correctAnswerInput) {
            correctAnswerInput = document.createElement('input');
            correctAnswerInput.type = 'hidden';
            correctAnswerInput.name = 'correct_answer_value';
            correctAnswerInput.id = 'correct_answer_value';
            document.getElementById('questionForm').appendChild(correctAnswerInput);
        }
        correctAnswerInput.value = correctAnswer;
    } else {
        // text-based questions: no options
        document.getElementById('options_json').value = '[]';
        let correctAnswerInput = document.getElementById('correct_answer_value');
        const expected = document.getElementById('expected_answer') ? document.getElementById('expected_answer').value.trim() : '';
        if (!correctAnswerInput) {
            correctAnswerInput = document.createElement('input');
            correctAnswerInput.type = 'hidden';
            correctAnswerInput.name = 'correct_answer_value';
            correctAnswerInput.id = 'correct_answer_value';
            document.getElementById('questionForm').appendChild(correctAnswerInput);
        }
        correctAnswerInput.value = expected;
    }
    
    return true;
}

function prepareEditOptions(questionId) {
    const optionsContainer = document.querySelector('#edit_options_' + questionId);
    
    if (!optionsContainer || optionsContainer.children.length === 0) {
        // text-based question
        document.getElementById('edit_options_json_' + questionId).value = '[]';
        const form = document.querySelector('#edit_options_' + questionId) ? document.querySelector('#edit_options_' + questionId).closest('form') : document.querySelector('form');
        let expectedInput = form ? form.querySelector('input[name="expected_answer_edit_' + questionId + '"]') : null;
        let correctAnswerInput = form ? form.querySelector('input[name="correct_answer_value"]') : null;
        if (!correctAnswerInput && form) {
            correctAnswerInput = document.createElement('input');
            correctAnswerInput.type = 'hidden';
            correctAnswerInput.name = 'correct_answer_value';
            form.appendChild(correctAnswerInput);
        }
        if (correctAnswerInput) {
            correctAnswerInput.value = expectedInput ? expectedInput.value.trim() : '';
        }
        return true;
    }
    
    const options = [];
    const optionInputs = document.querySelectorAll('#edit_options_' + questionId + ' input[type="text"]');
    
    optionInputs.forEach((input, index) => {
        if (input.value.trim() !== '') {
            options.push({
                letter: String.fromCharCode(65 + index),
                text: input.value.trim()
            });
        }
    });
    
    const checkedRadio = document.querySelector('input[name="correct_answer_edit_' + questionId + '"]:checked');
    let correctAnswer = checkedRadio ? checkedRadio.value : '';
    
    if (options.length > 0 && !correctAnswer) {
        alert('Please select the correct answer.');
        return false;
    }
    
    document.getElementById('edit_options_json_' + questionId).value = JSON.stringify(options);
    
    const form = document.querySelector('#edit_options_' + questionId).closest('form');
    let correctAnswerInput = form.querySelector('input[name="correct_answer_value"]');
    if (!correctAnswerInput) {
        correctAnswerInput = document.createElement('input');
        correctAnswerInput.type = 'hidden';
        correctAnswerInput.name = 'correct_answer_value';
        form.appendChild(correctAnswerInput);
    }
    correctAnswerInput.value = correctAnswer;
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    onTypeChange();
});
</script>

</body>
</html>