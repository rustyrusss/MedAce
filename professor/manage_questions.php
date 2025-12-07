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
        
        // Only allow custom points for essay and short_answer
        if (in_array($question_type, ['essay', 'short_answer'])) {
            $question_points = isset($_POST['question_points']) ? intval($_POST['question_points']) : 10;
        } else {
            $question_points = 1; // Fixed at 1 point for other question types
        }
        
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
        
        $_SESSION['success'] = "Question added successfully!";
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
        
        // Only allow custom points for essay and short_answer
        if (in_array($question_type, ['essay', 'short_answer'])) {
            $question_points = isset($_POST['question_points_edit']) ? intval($_POST['question_points_edit']) : 10;
        } else {
            $question_points = 1; // Fixed at 1 point for other question types
        }
        
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
        $_SESSION['success'] = "Question updated successfully!";
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
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: dashboard.php");
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
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - <?= htmlspecialchars($quiz['title']) ?> - MedAce</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
textarea {
    white-space: pre-wrap;
    word-wrap: break-word;
}
textarea, .question-text {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.whitespace-pre-wrap {
    white-space: pre-wrap;
    word-wrap: break-word;
}
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }

        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-collapsed {
            width: 5rem;
        }

        .sidebar-collapsed .nav-text,
        .sidebar-collapsed .profile-info {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-expanded {
            width: 18rem;
        }

        .sidebar-expanded .nav-text,
        .sidebar-expanded .profile-info {
            opacity: 1;
            width: auto;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 800px;
            width: 90%;
            animation: slideUp 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        @media (max-width: 1024px) {
            .sidebar-collapsed {
                width: 0;
                transform: translateX(-100%);
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div x-data="{ 
    showAddForm: false, 
    currentQuestionIndex: 0,
    editMode: false
}" class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition sidebar-collapsed">
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between px-4 py-5 border-b border-gray-200">
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                        <p class="text-xs text-gray-500">Professor</p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 border-b border-gray-200">
                <button onclick="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="manage_modules.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-book text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Modules</span>
                </a>
                <a href="manage_quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="student_progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Student Progress</span>
                </a>
            </nav>

            <div class="px-3 py-4 border-t border-gray-200">
                <a href="../actions/logout_action.php" class="flex items-center space-x-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-all">
                    <i class="fas fa-sign-out-alt w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 transition-all duration-300" style="margin-left: 5rem;">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars text-gray-600 text-xl"></i>
                </button>
                <div class="flex items-center space-x-3">
                    <a href="manage_quizzes.php" class="text-gray-600 hover:text-gray-900 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors font-semibold flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden sm:inline">Back to Quizzes</span>
                    </a>
                    <button @click="showAddForm = !showAddForm; editMode = false" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors font-semibold flex items-center space-x-2 shadow-sm">
                        <i class="fas" :class="showAddForm ? 'fa-times' : 'fa-plus'"></i>
                        <span class="hidden sm:inline" x-text="showAddForm ? 'Cancel' : 'Add Question'"></span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-lg"></i>
                        <span class="font-medium"><?= htmlspecialchars($_SESSION['success']) ?></span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-800 hover:text-green-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
                        <span class="font-medium"><?= htmlspecialchars($_SESSION['error']) ?></span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-800 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Page Header -->
            <div class="mb-8 animate-fade-in-up">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-2xl p-6 shadow-lg">
                    <h1 class="text-2xl sm:text-3xl font-bold mb-2">Manage Questions</h1>
                    <p class="text-primary-100">Quiz: <?= htmlspecialchars($quiz['title']) ?></p>
                    <div class="mt-4 inline-flex items-center px-4 py-2 bg-white/20 rounded-lg backdrop-blur-sm">
                        <i class="fas fa-question-circle mr-2"></i>
                        <span class="font-semibold"><?= count($questions) ?> Question<?= count($questions) != 1 ? 's' : '' ?></span>
                    </div>
                </div>
            </div>

            <!-- Add Question Form -->
            <div x-show="showAddForm" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-plus-circle text-primary-600 mr-2"></i>
                    Add New Question
                </h2>

                <form id="questionForm" method="POST" onsubmit="return prepareOptions()" class="space-y-5">
                    <input type="hidden" name="quiz_id" value="<?= htmlspecialchars($quiz_id) ?>">
                    <input type="hidden" name="options_json" id="options_json">
                    <input type="hidden" name="action" value="add">

                   <div>
    <label for="question_text" class="block text-sm font-semibold text-gray-700 mb-2">
        <i class="fas fa-question text-primary-500 mr-1"></i>
        Question Text *
    </label>
    <textarea id="question_text" name="question_text" required 
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none font-sans" 
              rows="6" 
              placeholder="Enter your question here..."
              style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6;"></textarea>
    <p class="text-xs text-gray-500 mt-1 italic">
        <i class="fas fa-info-circle mr-1"></i>
        Press Enter to create new paragraphs. Spacing will be preserved.
    </p>
</div>

                    <div>
                        <label for="question_type" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-list text-primary-500 mr-1"></i>
                            Question Type
                        </label>
                        <select id="question_type" name="question_type" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" onchange="onTypeChange()" required>
                            <option value="multiple_choice" selected>Multiple Choice</option>
                            <option value="checkbox">Checkboxes (Multiple Answers)</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>

                    <div id="options_area" class="space-y-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-check-circle text-primary-500 mr-1"></i>
                            Answer Options
                        </label>
                        <div id="options_list" class="space-y-3"></div>
                        <p class="text-sm text-gray-500 italic">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select the correct answer(s) using the radio buttons or checkboxes
                        </p>
                    </div>

                    <div id="text_answer_area" class="space-y-4 hidden">
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <p class="text-sm text-blue-800 mb-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Open-ended question:</strong> Students will type their answer. Manual grading required.
                            </p>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Expected Answer (Optional)</label>
                                <input id="expected_answer" name="expected_answer" type="text" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" 
                                       placeholder="Reference answer for grading (optional)">
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 p-5 rounded-lg border border-indigo-200">
                            <label class="block text-sm font-semibold text-gray-900 mb-2">
                                <i class="fas fa-trophy text-indigo-600 mr-1"></i>
                                Points for this Question
                            </label>
                            <input type="number" 
                                   id="question_points" 
                                   name="question_points" 
                                   min="1" 
                                   max="100" 
                                   value="10" 
                                   class="w-full px-4 py-3 border-2 border-indigo-300 rounded-lg focus:ring-2 focus:ring-indigo-400 outline-none font-semibold text-lg">
                            <p class="text-xs text-gray-600 mt-2">
                                <i class="fas fa-lightbulb text-yellow-500 mr-1"></i>
                                Set point value (1-100 points)
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button type="submit" 
                                class="flex-1 sm:flex-none bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors shadow-sm">
                            <i class="fas fa-save mr-2"></i>
                            Save Question
                        </button>
                        <button type="button" @click="showAddForm = false" 
                                class="flex-1 sm:flex-none bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Questions List -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list-ol text-primary-500 mr-2"></i>
                        Questions (<?= count($questions) ?>)
                    </h2>
                </div>

                <?php if (empty($questions)): ?>
                    <div class="text-center py-16 px-4">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-question-circle text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No questions yet</h3>
                        <p class="text-gray-600 mb-6">Start by adding your first question to this quiz</p>
                        <button @click="showAddForm = true" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors font-semibold inline-flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add First Question</span>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Quick Navigation -->
                    <div class="px-6 py-5 bg-gray-50 border-b border-gray-200">
                        <p class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-compass mr-2"></i>
                            Quick Navigation:
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($questions as $idx => $question): ?>
                                <button 
                                    @click="currentQuestionIndex = <?= $idx ?>; editMode = false; showAddForm = false"
                                    :class="currentQuestionIndex === <?= $idx ?> ? 'bg-primary-600 text-white border-primary-600 shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:border-primary-500 hover:bg-primary-50'"
                                    class="w-12 h-12 flex items-center justify-center rounded-lg border-2 transition-all font-bold">
                                    <?= $idx + 1 ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Question Display -->
                    <div class="p-6">
                        <?php foreach ($questions as $index => $q): ?>
                            <?php
                                $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY id ASC");
                                $stmt->execute([$q['id']]);
                                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $points = isset($q['points']) ? intval($q['points']) : 1;
                            ?>
                            
                            <div x-show="currentQuestionIndex === <?= $index ?>" x-cloak
                                 class="border-2 border-gray-200 rounded-xl p-6">
                                
                                <!-- View Mode -->
                                <div x-show="!editMode">
                                    <div class="flex items-start justify-between mb-6">
                                        <div class="flex items-start gap-4 flex-1">
                                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-primary-600 to-primary-700 text-white rounded-xl flex items-center justify-center font-bold text-xl shadow-md">
                                                <?= $index + 1 ?>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-3 flex-wrap">
                                                    <span class="badge bg-primary-100 text-primary-700">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($q['question_type']))) ?>
                                                    </span>
                                                    <?php if (in_array($q['question_type'], ['short_answer', 'essay'])): ?>
                                                        <span class="badge bg-amber-100 text-amber-700">
                                                            <i class="fas fa-trophy mr-1"></i>
                                                            <?= $points ?> point<?= $points != 1 ? 's' : '' ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-gray-100 text-gray-600">
                                                            <i class="fas fa-trophy mr-1"></i>
                                                            1 point
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xl font-semibold text-gray-900 leading-relaxed whitespace-pre-wrap" 
   style="word-wrap: break-word; line-height: 1.8;">
    <?= htmlspecialchars($q['question_text']) ?>
</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Answers -->
                                    <?php if (!empty($answers)): ?>
                                        <div class="ml-18 mb-6 space-y-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-3">
                                                <i class="fas fa-list-ul mr-1"></i>
                                                Answer Choices:
                                            </p>
                                            <?php 
                                            $labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                            foreach ($answers as $idx => $a): 
                                            ?>
                                                <div class="flex items-center gap-4 p-4 rounded-lg <?= $a['is_correct'] ? 'bg-green-50 border-2 border-green-400 shadow-sm' : 'bg-gray-50 border border-gray-200' ?>">
                                                    <span class="flex-shrink-0 w-10 h-10 rounded-full border-2 <?= $a['is_correct'] ? 'border-green-500 bg-green-100 text-green-700' : 'border-gray-300 bg-white text-gray-600' ?> flex items-center justify-center font-bold">
                                                        <?= $labels[$idx] ?>
                                                    </span>
                                                    <span class="flex-1 <?= $a['is_correct'] ? 'text-green-900 font-semibold' : 'text-gray-700' ?> text-lg">
                                                        <?= htmlspecialchars($a['answer_text']) ?>
                                                    </span>
                                                    <?= $a['is_correct'] ? '<span class="badge bg-green-600 text-white shadow-sm"><i class="fas fa-check mr-1"></i>CORRECT</span>' : '' ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="ml-18 mb-6 bg-blue-50 rounded-lg p-4 border-l-4 border-blue-400">
                                            <p class="text-sm text-blue-800">
                                                <i class="fas fa-pencil-alt mr-2"></i>
                                                <strong>Open-ended question:</strong> Students will provide their own answer
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <div class="ml-18 flex gap-3 pt-4 border-t border-gray-200">
                                        <button @click="editMode = true" 
                                                class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all font-medium shadow-sm">
                                            <i class="fas fa-edit mr-2"></i>
                                            Edit Question
                                        </button>
                                        <a href="../actions/delete_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>"
                                           onclick="return confirm('⚠️ Delete this question permanently?\n\nThis action cannot be undone!')"
                                           class="inline-flex items-center px-5 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all font-medium shadow-sm">
                                            <i class="fas fa-trash-alt mr-2"></i>
                                            Delete
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
                                            <div class="w-12 h-12 bg-orange-600 text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-md">
                                                <?= $index + 1 ?>
                                            </div>
                                            <span class="badge bg-orange-600 text-white shadow-md">
                                                <i class="fas fa-pen mr-1"></i>
                                                EDITING MODE
                                            </span>
                                        </div>

                                        <div class="space-y-5">
                                            <div>
    <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text</label>
    <textarea name="question_text" required 
              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all text-lg resize-none font-sans" 
              rows="6"
              style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6;"><?= htmlspecialchars($q['question_text']) ?></textarea>
    <p class="text-xs text-gray-500 mt-1 italic">
        <i class="fas fa-info-circle mr-1"></i>
        Press Enter to create new paragraphs. Spacing will be preserved.
    </p>
</div>

                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Question Type</label>
                                                <select class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed" disabled>
                                                    <option><?= ucfirst(str_replace('_', ' ', $q['question_type'])) ?></option>
                                                </select>
                                                <p class="text-xs text-gray-500 mt-1 italic">
                                                    <i class="fas fa-lock mr-1"></i>
                                                    Question type cannot be changed after creation
                                                </p>
                                            </div>

                                            <?php if (!empty($answers)): ?>
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-3">Answer Options</label>
                                                <div class="space-y-3" id="edit_options_<?= $q['id'] ?>">
                                                    <?php 
                                                    $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
                                                    foreach ($answers as $idx => $answer): 
                                                    ?>
                                                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border-2 border-gray-300 hover:border-orange-400 transition-colors">
                                                            <span class="w-10 h-10 rounded-full border-2 border-gray-400 flex items-center justify-center font-bold flex-shrink-0">
                                                                <?= $labels[$idx] ?>
                                                            </span>
                                                            <input type="radio" name="correct_answer_edit_<?= $q['id'] ?>" 
                                                                   value="<?= $labels[$idx] ?>" 
                                                                   <?= $answer['is_correct'] ? 'checked' : '' ?>
                                                                   class="w-5 h-5 text-green-600 flex-shrink-0">
                                                            <input type="text" name="edit_option<?= $idx + 1 ?>" 
                                                                   value="<?= htmlspecialchars($answer['answer_text']) ?>"
                                                                   class="flex-1 px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all"
                                                                   placeholder="Answer option" required>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-2 italic">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Multiple choice, checkbox, and true/false questions are worth 1 point each
                                                </p>
                                            </div>
                                            <?php else: ?>
                                            <div class="space-y-4">
                                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                                    <p class="text-sm text-blue-800">
                                                        <i class="fas fa-info-circle mr-1"></i>
                                                        <strong>Text-based question:</strong> Students will provide their own answer
                                                    </p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Expected Answer (Optional)</label>
                                                    <input type="text" name="expected_answer_edit_<?= $q['id'] ?>" 
                                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-orange-500 transition-all" 
                                                           placeholder="Reference answer for grading">
                                                </div>
                                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 p-5 rounded-lg border border-indigo-200">
                                                    <label class="block text-sm font-semibold text-gray-900 mb-2">
                                                        <i class="fas fa-trophy text-indigo-600 mr-1"></i>
                                                        Points for this Question
                                                    </label>
                                                    <input type="number" 
                                                           name="question_points_edit" 
                                                           min="1" 
                                                           max="100" 
                                                           value="<?= $points ?>" 
                                                           class="w-full px-4 py-3 border-2 border-indigo-300 rounded-lg focus:ring-2 focus:ring-indigo-400 outline-none font-semibold text-lg">
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <div class="flex gap-3 pt-4 border-t border-gray-200">
                                                <button type="submit" 
                                                        class="flex-1 sm:flex-none px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md transition-all">
                                                    <i class="fas fa-save mr-2"></i>
                                                    Save Changes
                                                </button>
                                                <button type="button" 
                                                        @click="editMode = false" 
                                                        class="flex-1 sm:flex-none px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-semibold shadow-md transition-all">
                                                    <i class="fas fa-times mr-2"></i>
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                            </div>
                        <?php endforeach; ?>

                        <!-- Navigation Controls -->
                        <div class="mt-6 flex justify-between items-center pt-6 border-t border-gray-200">
                            <button 
                                @click="currentQuestionIndex = currentQuestionIndex > 0 ? currentQuestionIndex - 1 : <?= count($questions) - 1 ?>; editMode = false"
                                class="px-5 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all font-medium shadow-sm">
                                <i class="fas fa-arrow-left mr-2"></i>
                                <span class="hidden sm:inline">Previous</span>
                            </button>
                            <span class="text-gray-600 font-semibold">
                                Question <span x-text="currentQuestionIndex + 1"></span> of <?= count($questions) ?>
                            </span>
                            <button 
                                @click="currentQuestionIndex = currentQuestionIndex < <?= count($questions) - 1 ?> ? currentQuestionIndex + 1 : 0; editMode = false"
                                class="px-5 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all font-medium shadow-sm">
                                <span class="hidden sm:inline">Next</span>
                                <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    let sidebarExpanded = false;

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebarExpanded = !sidebarExpanded;
        
        if (window.innerWidth < 1024) {
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            overlay.classList.toggle('hidden');
            if (sidebarExpanded) {
                mainContent.style.marginLeft = '0';
            }
        } else {
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            
            if (sidebarExpanded) {
                mainContent.style.marginLeft = '18rem';
            } else {
                mainContent.style.marginLeft = '5rem';
            }
        }
    }

    function closeSidebar() {
        if (window.innerWidth < 1024 && sidebarExpanded) {
            toggleSidebar();
        }
    }

    function onTypeChange() {
        const questionType = document.getElementById('question_type').value;
        const optionsArea = document.getElementById('options_area');
        const textAnswerArea = document.getElementById('text_answer_area');

        if (questionType === 'multiple_choice') {
            optionsArea.classList.remove('hidden');
            textAnswerArea.classList.add('hidden');

            document.getElementById('options_list').innerHTML = `
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">A</span>
                    <input type="radio" name="correct_answer" value="A" class="w-5 h-5 text-primary-600 flex-shrink-0" required>
                    <input type="text" name="option1" placeholder="Option A" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">B</span>
                    <input type="radio" name="correct_answer" value="B" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option2" placeholder="Option B" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">C</span>
                    <input type="radio" name="correct_answer" value="C" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option3" placeholder="Option C" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">D</span>
                    <input type="radio" name="correct_answer" value="D" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option4" placeholder="Option D" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
            `;
        } else if (questionType === 'checkbox') {
            optionsArea.classList.remove('hidden');
            textAnswerArea.classList.add('hidden');

            document.getElementById('options_list').innerHTML = `
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">A</span>
                    <input type="checkbox" name="correct_answers[]" value="A" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option1" placeholder="Option A" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">B</span>
                    <input type="checkbox" name="correct_answers[]" value="B" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option2" placeholder="Option B" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">C</span>
                    <input type="checkbox" name="correct_answers[]" value="C" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option3" placeholder="Option C" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">D</span>
                    <input type="checkbox" name="correct_answers[]" value="D" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option4" placeholder="Option D" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 transition-all" required>
                </div>
            `;
        } else if (questionType === 'true_false') {
            optionsArea.classList.remove('hidden');
            textAnswerArea.classList.add('hidden');

            document.getElementById('options_list').innerHTML = `
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">A</span>
                    <input type="radio" name="correct_answer" value="A" checked required class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option1" value="True" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed" readonly>
                </div>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold flex-shrink-0">B</span>
                    <input type="radio" name="correct_answer" value="B" class="w-5 h-5 text-primary-600 flex-shrink-0">
                    <input type="text" name="option2" value="False" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed" readonly>
                </div>
            `;
        } else {
            optionsArea.classList.add('hidden');
            textAnswerArea.classList.remove('hidden');
            document.getElementById('options_list').innerHTML = '';
        }
    }

    function prepareOptions() {
        const questionType = document.getElementById('question_type').value;
        
        if (['multiple_choice', 'checkbox', 'true_false'].includes(questionType)) {
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
            document.getElementById('edit_options_json_' + questionId).value = '[]';
            const form = optionsContainer ? optionsContainer.closest('form') : document.querySelector('form');
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
        
        const form = optionsContainer.closest('form');
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

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('main-content');
                const overlay = document.getElementById('sidebar-overlay');
                
                if (window.innerWidth >= 1024) {
                    overlay.classList.add('hidden');
                    if (sidebarExpanded) {
                        mainContent.style.marginLeft = '18rem';
                    } else {
                        mainContent.style.marginLeft = '5rem';
                    }
                } else {
                    mainContent.style.marginLeft = '0';
                    if (!sidebarExpanded) {
                        sidebar.classList.add('sidebar-collapsed');
                        sidebar.classList.remove('sidebar-expanded');
                    }
                }
            }, 250);
        });
    });
</script>

</body>
</html>