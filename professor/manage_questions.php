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
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if (!$quiz_id) {
    header("Location: dashboard_professor.php");
    exit();
}

// Check quiz ownership
$stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: dashboard_professor.php");
    exit();
}

// Fetch professor for display
$prof_stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$prof_stmt->execute([$professor_id]);
$prof = $prof_stmt->fetch(PDO::FETCH_ASSOC);

$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";
$profilePic = getProfilePicture($prof, "../");

// Fetch existing questions
$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log question count
error_log("Questions found for quiz $quiz_id: " . count($questions));
if (!empty($questions)) {
    error_log("First question: " . print_r($questions[0], true));
}
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
        .hover-row:hover { background-color: #e0f2fe; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-gray-800 font-sans">

<div x-data="{ sidebarOpen: false, showAddForm: false, editingQuestion: null }" class="flex min-h-screen">

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" 
           class="bg-white shadow-sm border-r border-gray-200 transition-all duration-300 relative flex flex-col z-30">

        <!-- Profile + Toggle -->
        <div class="flex items-center justify-between p-3 border-b">
            <div class="flex items-center gap-2">
                <img src="<?= htmlspecialchars($profilePic) ?>" 
                     class="w-8 h-8 rounded-full object-cover border" />

                <span x-show="sidebarOpen" class="text-sm font-semibold text-sky-700">
                    <?= htmlspecialchars(ucwords(strtolower($profName))) ?>
                </span>
            </div>

            <!-- Toggle Button -->
            <button @click="sidebarOpen = !sidebarOpen"
                    class="p-1 rounded-md text-gray-600 hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <!-- Navigation -->
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

        <!-- Logout -->
        <div class="px-2 py-4 border-t">
            <a href="../actions/logout_action.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-red-600 hover:bg-red-50 transition">
                <div class="w-8 text-xl flex justify-center">🚪</div>
                <span x-show="sidebarOpen" class="font-medium">Logout</span>
            </a>
        </div>
    </aside>
    <!-- Sidebar End -->

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <div class="max-w-4xl mx-auto">
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">✕</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">✕</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Header -->
            <div class="bg-white p-6 rounded-xl shadow mb-6">
                <h1 class="text-2xl font-bold mb-4">
                    Manage Questions — <?= htmlspecialchars($quiz['title']) ?>
                </h1>
                
                <div class="flex gap-3">
                    <button @click="showAddForm = !showAddForm" 
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

            <!-- Add Question Form (Toggle) -->
            <div x-show="showAddForm" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="bg-white p-6 rounded-xl shadow mb-6">
                
                <h2 class="text-xl font-bold mb-4">Add New Question</h2>

                <!-- Form to Add Question -->
                <form id="questionForm" action="../actions/add_questions_action.php" method="POST" onsubmit="prepareOptions()">
                    <input type="hidden" name="quiz_id" value="<?= htmlspecialchars($quiz_id) ?>">
                    <input type="hidden" name="options_json" id="options_json">

                    <div class="space-y-4">
                        <div>
                            <label for="question_text" class="block text-sm font-medium mb-1">Question</label>
                            <textarea id="question_text" name="question_text" required class="w-full p-2 border rounded" rows="3"></textarea>
                        </div>

                        <div>
                            <label for="question_type" class="block text-sm font-medium mb-1">Question Type</label>
                            <select id="question_type" name="question_type" class="w-full p-2 border rounded" onchange="onTypeChange()" required>
                                <option value="short_answer">Short Answer</option>
                                <option value="paragraph">Paragraph</option>
                                <option value="multiple_choice" selected>Multiple Choice</option>
                                <option value="checkbox">Checkboxes</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="true_false">True / False</option>
                                <option value="essay">Essay</option>
                            </select>
                        </div>

                        <!-- Options area (dynamic) -->
                        <div id="options_area" class="space-y-2">
                            <label class="block text-sm font-medium mb-1">Options</label>

                            <div id="options_list" class="space-y-2">
                                <!-- Static Options for Multiple Choice (A, B, C, D) -->
                                <div class="flex items-center gap-3">
                                    <span class="w-6 font-bold">A)</span>
                                    <input type="radio" name="correct_answer" value="A" class="border rounded p-2">
                                    <input type="text" name="option1" placeholder="Option text" class="w-full p-2 border rounded" value="Option A">
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 font-bold">B)</span>
                                    <input type="radio" name="correct_answer" value="B" class="border rounded p-2">
                                    <input type="text" name="option2" placeholder="Option text" class="w-full p-2 border rounded" value="Option B">
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 font-bold">C)</span>
                                    <input type="radio" name="correct_answer" value="C" class="border rounded p-2">
                                    <input type="text" name="option3" placeholder="Option text" class="w-full p-2 border rounded" value="Option C">
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 font-bold">D)</span>
                                    <input type="radio" name="correct_answer" value="D" class="border rounded p-2">
                                    <input type="text" name="option4" placeholder="Option text" class="w-full p-2 border rounded" value="Option D">
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" onclick="clearOptions()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Clear</button>
                                <p class="text-sm text-gray-500 ml-auto">For checkboxes you can mark multiple correct answers.</p>
                            </div>
                        </div>

                        <!-- Short/Paragraph/Essay note -->
                        <div id="text_note" class="p-3 bg-blue-50 border border-blue-100 rounded text-sm text-blue-800 hidden">
                            Students will see a text box for this question type. Essay/Paragraph require manual grading.
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Question</button>
                            <button type="button" @click="showAddForm = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                        </div>

                    </div>
                </form>
            </div>

            <!-- Existing Questions List -->
            <div class="bg-white p-6 rounded-xl shadow">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Existing Questions (<?= count($questions) ?>)</h2>
                    <?php if (empty($questions)): ?>
                    <!-- No Questions State -->
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📝</div>
                        <p class="text-gray-500 text-lg mb-4">No questions added yet.</p>
                        <button @click="showAddForm = true" 
                                class="px-6 py-3 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition">
                            ➕ Add Your First Question
                        </button>
                    </div>
                <?php else: ?>
                        <a href="?quiz_id=<?= $quiz_id ?>&debug=1" class="text-xs text-blue-600 hover:text-blue-800">
                            🔍 Show Debug Info
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Debug Section -->
                <?php if (isset($_GET['debug']) && !empty($questions)): ?>
                    <div class="mb-6 p-4 bg-yellow-50 border-2 border-yellow-300 rounded-lg">
                        <h3 class="font-bold text-yellow-900 mb-2">🔍 Debug Information:</h3>
                        <div class="text-sm space-y-2">
                            <p><strong>Total Questions:</strong> <?= count($questions) ?></p>
                            <p><strong>Quiz ID:</strong> <?= $quiz_id ?></p>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-yellow-800 font-medium">View Raw Data</summary>
                                <pre class="mt-2 text-xs overflow-auto bg-white p-3 rounded border"><?= htmlspecialchars(print_r($questions, true)) ?></pre>
                            </details>
                        </div>
                        <a href="?quiz_id=<?= $quiz_id ?>" class="inline-block mt-3 text-xs bg-yellow-600 text-white px-3 py-1 rounded hover:bg-yellow-700">
                            Hide Debug
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($questions)): ?>
                    <!-- Question Navigation -->
                    <div class="mb-6 pb-4 border-b bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 mb-3 font-medium">Quick Navigation:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($questions as $idx => $question): ?>
                                <a href="#question-<?= $question['id'] ?>" 
                                   title="Jump to question <?= $idx + 1 ?>"
                                   class="w-10 h-10 flex items-center justify-center rounded-full border-2 border-gray-300 hover:border-blue-500 hover:bg-blue-50 transition font-medium text-gray-700 hover:text-blue-600">
                                    <?= $idx + 1 ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Click a number to jump to that question</p>
                    </div>

                    <ul class="space-y-6">
                        <?php foreach ($questions as $index => $q): ?>
                            <?php
                                // Fetch answers for this question
                                $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY id ASC");
                                $stmt->execute([$q['id']]);
                                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <li id="question-<?= $q['id'] ?>" class="border-2 border-gray-200 rounded-xl bg-white p-5 transition scroll-mt-4 shadow-sm hover:shadow-md"
                                x-data="{ 
                                    editing: false,
                                    questionText: <?= json_encode($q['question_text'] ?? '') ?>,
                                    questionType: <?= json_encode($q['question_type'] ?? 'multiple_choice') ?>
                                }">

                                <!-- View Mode -->
                                <div x-show="!editing">
                                    <!-- Question Header -->
                                    <div class="flex items-start gap-4 mb-4">
                                        <div class="flex-shrink-0 w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-lg">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-700 font-medium">
                                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($q['question_type'] ?? 'Unknown'))) ?>
                                                </span>
                                            </div>
                                            <p class="text-lg font-semibold text-gray-900 leading-relaxed">
                                                <?= htmlspecialchars($q['question_text'] ?? 'No question text') ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Answers Section -->
                                    <?php if (!empty($answers)): ?>
                                        <div class="ml-14 bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Answer Choices:</p>
                                            <ul class="space-y-2">
                                                <?php 
                                                $labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                                foreach ($answers as $idx => $a): 
                                                ?>
                                                    <li class="flex items-center gap-3 p-2 rounded <?= $a['is_correct'] ? 'bg-green-50 border border-green-200' : 'bg-white' ?>">
                                                        <span class="flex-shrink-0 w-7 h-7 rounded-full border-2 <?= $a['is_correct'] ? 'border-green-500 bg-green-100' : 'border-gray-300 bg-white' ?> flex items-center justify-center font-bold text-sm <?= $a['is_correct'] ? 'text-green-700' : 'text-gray-600' ?>">
                                                            <?= $labels[$idx] ?>
                                                        </span>
                                                        <span class="flex-1 <?= $a['is_correct'] ? 'text-green-800 font-semibold' : 'text-gray-700' ?>">
                                                            <?= htmlspecialchars($a['answer_text']) ?>
                                                        </span>
                                                        <?= $a['is_correct'] ? '<span class="flex-shrink-0 text-xs bg-green-500 text-white px-3 py-1 rounded-full font-medium">✓ Correct Answer</span>' : '' ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <div class="ml-14 bg-amber-50 rounded-lg p-4 border border-amber-200">
                                            <p class="text-sm text-amber-800">
                                                <?php if (in_array($q['question_type'], ['short_answer', 'paragraph', 'essay'])): ?>
                                                    <span class="font-semibold">📝 Open-ended question</span> — Students will provide their own answer
                                                <?php else: ?>
                                                    <span class="font-semibold">⚠️ No answer options found</span> — Click "Edit Question" to add answer choices
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <div class="mt-5 ml-14 flex gap-3">
                                        <button @click="editing = true"
                                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium shadow-sm hover:shadow">
                                           ✏️ Edit Question
                                        </button>

                                        <a href="delete_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>"
                                           onclick="return confirm('Are you sure you want to delete this question? This action cannot be undone.')"
                                           class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm font-medium shadow-sm hover:shadow">
                                           🗑 Delete Question
                                        </a>
                                    </div>
                                </div>

                                <!-- Edit Mode -->
                                <div x-show="editing" x-cloak class="bg-orange-50 rounded-xl p-5 border-2 border-orange-200">
                                    <form method="POST" action="../actions/edit_questions_action.php" class="space-y-4">
                                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                        <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">

                                        <div class="flex items-center gap-3 mb-4">
                                            <div class="flex-shrink-0 w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold text-lg">
                                                <?= $index + 1 ?>
                                            </div>
                                            <span class="text-sm px-3 py-1 rounded-full bg-orange-600 text-white font-medium">
                                                ✏️ Editing Mode
                                            </span>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text</label>
                                            <textarea name="question_text" x-model="questionText" required 
                                                      class="w-full p-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" 
                                                      rows="3"></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Question Type</label>
                                            <select name="question_type" x-model="questionType" 
                                                    class="w-full p-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" required>
                                                <option value="short_answer">Short Answer</option>
                                                <option value="paragraph">Paragraph</option>
                                                <option value="multiple_choice">Multiple Choice</option>
                                                <option value="checkbox">Checkboxes</option>
                                                <option value="dropdown">Dropdown</option>
                                                <option value="true_false">True / False</option>
                                                <option value="essay">Essay</option>
                                            </select>
                                        </div>

                                        <!-- Options for Multiple Choice Types -->
                                        <?php if (!empty($answers)): ?>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-3">Answer Options</label>
                                            <div class="space-y-3">
                                                <?php 
                                                $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
                                                foreach ($answers as $idx => $answer): 
                                                ?>
                                                    <div class="flex items-center gap-3 p-3 bg-white rounded-lg border-2 border-gray-200">
                                                        <span class="flex-shrink-0 w-8 h-8 rounded-full border-2 border-gray-400 flex items-center justify-center font-bold text-sm">
                                                            <?= $labels[$idx] ?>
                                                        </span>
                                                        <input type="radio" name="correct_answer" 
                                                               value="<?= $answer['id'] ?>" 
                                                               <?= $answer['is_correct'] ? 'checked' : '' ?>
                                                               class="w-5 h-5 text-green-600 focus:ring-green-500">
                                                        <input type="text" name="answers[<?= $answer['id'] ?>]" 
                                                               value="<?= htmlspecialchars($answer['answer_text']) ?>"
                                                               class="flex-1 p-2 border-2 border-gray-300 rounded focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                                                               placeholder="Answer option">
                                                        <span class="text-xs text-gray-500">Mark as correct →</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="flex gap-3 pt-2">
                                            <button type="submit" 
                                                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium shadow-md hover:shadow-lg transition">
                                                💾 Save Changes
                                            </button>
                                            <button type="button" 
                                                    @click="editing = false; questionText = <?= json_encode($q['question_text']) ?>; questionType = <?= json_encode($q['question_type']) ?>" 
                                                    class="px-6 py-3 bg-gray-400 text-white rounded-lg hover:bg-gray-500 font-medium shadow-md hover:shadow-lg transition">
                                                ✖ Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </div>

        </div>
    </main>
</div>

<script>
// Ensure that options only appear based on the question type
function onTypeChange() {
    const questionType = document.getElementById('question_type').value;
    const optionsArea = document.getElementById('options_area');
    const textNote = document.getElementById('text_note');

    // Show options only for multiple choice, checkbox, dropdown, or true/false
    if (questionType === 'multiple_choice' || questionType === 'dropdown') {
        optionsArea.classList.remove('hidden');
        textNote.classList.add('hidden');
        
        // Reset to default radio options
        const optionsList = document.getElementById('options_list');
        optionsList.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">A)</span>
                <input type="radio" name="correct_answer" value="A" class="border rounded p-2">
                <input type="text" name="option1" placeholder="Option A" class="w-full p-2 border rounded">
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">B)</span>
                <input type="radio" name="correct_answer" value="B" class="border rounded p-2">
                <input type="text" name="option2" placeholder="Option B" class="w-full p-2 border rounded">
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">C)</span>
                <input type="radio" name="correct_answer" value="C" class="border rounded p-2">
                <input type="text" name="option3" placeholder="Option C" class="w-full p-2 border rounded">
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">D)</span>
                <input type="radio" name="correct_answer" value="D" class="border rounded p-2">
                <input type="text" name="option4" placeholder="Option D" class="w-full p-2 border rounded">
            </div>
        `;
        
    } else if (questionType === 'checkbox') {
        optionsArea.classList.remove('hidden');
        textNote.classList.add('hidden');
        
        // For checkboxes, use checkbox inputs instead of radio
        const optionsList = document.getElementById('options_list');
        optionsList.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">A)</span>
                <input type="checkbox" name="correct_answers[]" value="A" class="w-5 h-5 border rounded">
                <input type="text" name="option1" placeholder="Option A" class="w-full p-2 border rounded">
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">B)</span>
                <input type="checkbox" name="correct_answers[]" value="B" class="w-5 h-5 border rounded">
                <input type="text" name="option2" placeholder="Option B" class="w-full p-2 border rounded">
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">C)</span>
                <input type="checkbox" name="correct_answers[]" value="C" class="w-5 h-5 border rounded">
                <input type="text" name="option3" placeholder="Option C" class="w-full p-2 border rounded">
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">D)</span>
                <input type="checkbox" name="correct_answers[]" value="D" class="w-5 h-5 border rounded">
                <input type="text" name="option4" placeholder="Option D" class="w-full p-2 border rounded">
            </div>
        `;
        
    } else if (questionType === 'true_false') {
        optionsArea.classList.remove('hidden');
        textNote.classList.add('hidden');
        
        // Limit options to True/False
        const optionsList = document.getElementById('options_list');
        optionsList.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">A)</span>
                <input type="radio" name="correct_answer" value="A" checked class="border rounded p-2">
                <input type="text" name="option1" class="w-full p-2 border rounded bg-gray-100" value="True" readonly>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-6 font-bold">B)</span>
                <input type="radio" name="correct_answer" value="B" class="border rounded p-2">
                <input type="text" name="option2" class="w-full p-2 border rounded bg-gray-100" value="False" readonly>
            </div>
        `;
        
    } else {
        // Short answer, paragraph, essay
        optionsArea.classList.add('hidden');
        textNote.classList.remove('hidden');
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
    // Validate that at least one correct answer is selected for option-based questions
    const questionType = document.getElementById('question_type').value;
    
    if (['multiple_choice', 'checkbox', 'dropdown', 'true_false'].includes(questionType)) {
        const radios = document.querySelectorAll('input[name="correct_answer"]:checked');
        const checkboxes = document.querySelectorAll('input[name="correct_answers[]"]:checked');
        
        if (radios.length === 0 && checkboxes.length === 0) {
            alert('Please select at least one correct answer.');
            return false;
        }
    }
    
    return true;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    onTypeChange();
});
</script>

</body>
</html>