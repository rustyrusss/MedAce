<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// ✅ Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// ✅ Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_quiz') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $module_id = intval($_POST['module_id']);
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $time_limit = isset($_POST['time_limit']) ? intval($_POST['time_limit']) : 0;
        $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
        $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
        
        if (empty($title) || empty($module_id)) {
            throw new Exception("Title and Module are required.");
        }
        
        // Verify module belongs to professor
        $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND professor_id = ?");
        $stmt->execute([$module_id, $professorId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid module selected.");
        }
        
        // Insert quiz
        $stmt = $conn->prepare("
            INSERT INTO quizzes (title, description, module_id, lesson_id, professor_id, content, status, time_limit, publish_time, deadline_time, created_at) 
            VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title,
            $description,
            $module_id,
            $professorId,
            $content,
            $status,
            $time_limit,
            $publish_time,
            $deadline_time
        ]);
        
        $_SESSION['success'] = "Quiz created successfully!";
        header("Location: manage_quizzes.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating quiz: " . $e->getMessage();
    }
}

// ✅ Handle quiz update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_quiz') {
    try {
        $quiz_id = intval($_POST['quiz_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $module_id = intval($_POST['module_id']);
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $time_limit = isset($_POST['time_limit']) ? intval($_POST['time_limit']) : 0;
        $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
        $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
        
        if (empty($title) || empty($module_id)) {
            throw new Exception("Title and Module are required.");
        }
        
        // Verify quiz belongs to professor
        $stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND professor_id = ?");
        $stmt->execute([$quiz_id, $professorId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid quiz selected.");
        }
        
        // Verify module belongs to professor
        $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND professor_id = ?");
        $stmt->execute([$module_id, $professorId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid module selected.");
        }
        
        // Update quiz
        $stmt = $conn->prepare("
            UPDATE quizzes 
            SET title = ?, description = ?, module_id = ?, content = ?, status = ?, 
                time_limit = ?, publish_time = ?, deadline_time = ?
            WHERE id = ? AND professor_id = ?
        ");
        $stmt->execute([
            $title,
            $description,
            $module_id,
            $content,
            $status,
            $time_limit,
            $publish_time,
            $deadline_time,
            $quiz_id,
            $professorId
        ]);
        
        $_SESSION['success'] = "Quiz updated successfully!";
        header("Location: manage_quizzes.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating quiz: " . $e->getMessage();
    }
}

// ✅ Fetch professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";
$profilePic = getProfilePicture($prof, "../");

// ✅ Fetch modules for dropdown
$stmt = $conn->prepare("SELECT id, title FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch quizzes
$stmt = $conn->prepare("
    SELECT q.id, q.title, q.description, q.status, q.time_limit, q.created_at, q.module_id, q.content, q.publish_time, q.deadline_time, m.title AS module_title
    FROM quizzes q
    LEFT JOIN modules m ON q.module_id = m.id
    WHERE q.professor_id = :professor_id
    ORDER BY q.created_at DESC
");
$stmt->bindParam(':professor_id', $professorId);
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes - MedAce</title>
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }

        .animate-slide-in {
            animation: slideIn 0.4s ease-out;
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

        .table-row-hover {
            transition: all 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: #f0f9ff;
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
            max-width: 700px;
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
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">
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
                    <button onclick="openAddModal()" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors font-semibold flex items-center space-x-2 shadow-sm">
                        <i class="fas fa-plus"></i>
                        <span class="hidden sm:inline">Add Quiz</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3 text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg animate-fade-in-up">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Page Header -->
            <div class="mb-8 animate-fade-in-up">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Quizzes</h1>
                <p class="text-gray-600">Create, edit, and organize your course quizzes</p>
            </div>

            <!-- Search and Filter Bar -->
            <div class="mb-6 animate-slide-in">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="relative flex-1">
                        <input type="text" id="searchInput" placeholder="Search quizzes by title..." 
                               class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <select id="statusFilter" class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Quizzes Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up">
                <div class="px-6 py-5 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-clipboard-list text-primary-500 mr-2"></i>
                            Your Quizzes
                        </h2>
                        <span class="badge bg-primary-50 text-primary-700">
                            <?= count($quizzes) ?> Total
                        </span>
                    </div>
                </div>

                <?php if (count($quizzes) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden md:table-cell">Description</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">Module</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">Created</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" id="quizTableBody">
                            <?php foreach ($quizzes as $index => $quiz): ?>
                            <tr class="table-row-hover quiz-row" 
                                data-status="<?= strtolower($quiz['status']) ?>" 
                                data-title="<?= htmlspecialchars($quiz['title']) ?>">
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?= $index + 1 ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-clipboard-question text-purple-600"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($quiz['title']) ?></p>
                                            <?php if (!empty($quiz['time_limit']) && $quiz['time_limit'] > 0): ?>
                                                <p class="text-xs text-gray-500">
                                                    <i class="fas fa-clock mr-1"></i><?= $quiz['time_limit'] ?> min
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 hidden md:table-cell">
                                    <p class="truncate max-w-xs"><?= htmlspecialchars($quiz['description']) ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 hidden lg:table-cell">
                                    <?= htmlspecialchars($quiz['module_title'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $status = strtolower($quiz['status']);
                                        $statusConfig = match($status) {
                                            'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle'],
                                            'inactive' => ['bg' => 'bg-gray-200', 'text' => 'text-gray-700', 'icon' => 'fa-pause-circle'],
                                            default => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-info-circle']
                                        };
                                    ?>
                                    <span class="badge <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>">
                                        <i class="fas <?= $statusConfig['icon'] ?> mr-1"></i>
                                        <?= ucfirst($quiz['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 hidden lg:table-cell">
                                    <?= date('M d, Y', strtotime($quiz['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="manage_questions.php?quiz_id=<?= $quiz['id'] ?>" 
                                           class="inline-flex items-center px-3 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 font-medium transition-colors text-sm">
                                            <i class="fas fa-question-circle mr-1"></i>
                                            <span class="hidden sm:inline">Questions</span>
                                        </a>
                                        <button onclick='openEditModal(<?= json_encode($quiz) ?>)' 
                                                class="inline-flex items-center px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-medium transition-colors text-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="../actions/delete_quiz.php?id=<?= $quiz['id'] ?>" 
                                           onclick="return confirm('⚠️ Delete this quiz permanently?\n\nThis action cannot be undone and will delete all associated questions!');" 
                                           class="inline-flex items-center px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium transition-colors text-sm">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-16 px-4">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-clipboard-list text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No quizzes yet</h3>
                    <p class="text-gray-600 mb-6">Get started by creating your first quiz</p>
                    <button onclick="openAddModal()" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors font-semibold inline-flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Quiz</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add Quiz Modal -->
<div id="addQuizModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Add New Quiz</h2>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="action" value="add_quiz">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-heading text-primary-500 mr-1"></i>
                    Quiz Title *
                </label>
                <input type="text" name="title" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                       placeholder="e.g., Anatomy Midterm Quiz">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-align-left text-primary-500 mr-1"></i>
                    Description
                </label>
                <textarea name="description" rows="3" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"
                          placeholder="Brief description of the quiz..."></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-book text-primary-500 mr-1"></i>
                    Select Module *
                </label>
                <select name="module_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <option value="" disabled selected>— Choose a module —</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-file-lines text-primary-500 mr-1"></i>
                    Instructions / Content
                </label>
                <textarea name="content" rows="3" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"
                          placeholder="Additional instructions for students..."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-primary-500 mr-1"></i>
                        Status
                    </label>
                    <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-clock text-primary-500 mr-1"></i>
                        Time Limit (minutes)
                    </label>
                    <input type="number" name="time_limit" min="0" value="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                           placeholder="0 = No limit">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-plus text-primary-500 mr-1"></i>
                        Publish Time
                    </label>
                    <input type="datetime-local" name="publish_time" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <p class="text-xs text-gray-500 mt-1">Leave empty to publish immediately</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-xmark text-primary-500 mr-1"></i>
                        Deadline Time
                    </label>
                    <input type="datetime-local" name="deadline_time" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for no deadline</p>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeAddModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Save Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Quiz Modal -->
<div id="editQuizModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Edit Quiz</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-5" id="editQuizForm">
            <input type="hidden" name="action" value="edit_quiz">
            <input type="hidden" name="quiz_id" id="edit_quiz_id">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-heading text-primary-500 mr-1"></i>
                    Quiz Title *
                </label>
                <input type="text" name="title" id="edit_title" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-align-left text-primary-500 mr-1"></i>
                    Description
                </label>
                <textarea name="description" id="edit_description" rows="3" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-book text-primary-500 mr-1"></i>
                    Select Module *
                </label>
                <select name="module_id" id="edit_module_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <option value="" disabled>— Choose a module —</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-file-lines text-primary-500 mr-1"></i>
                    Instructions / Content
                </label>
                <textarea name="content" id="edit_content" rows="3" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-primary-500 mr-1"></i>
                        Status
                    </label>
                    <select name="status" id="edit_status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-clock text-primary-500 mr-1"></i>
                        Time Limit (minutes)
                    </label>
                    <input type="number" name="time_limit" id="edit_time_limit" min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                           placeholder="0 = No limit">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-plus text-primary-500 mr-1"></i>
                        Publish Time
                    </label>
                    <input type="datetime-local" name="publish_time" id="edit_publish_time" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-xmark text-primary-500 mr-1"></i>
                        Deadline Time
                    </label>
                    <input type="datetime-local" name="deadline_time" id="edit_deadline_time" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Update Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let sidebarExpanded = false;

    // Sidebar Toggle
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

    // Modal Functions
    function openAddModal() {
        document.getElementById('addQuizModal').classList.add('show');
    }

    function closeAddModal() {
        document.getElementById('addQuizModal').classList.remove('show');
    }

    function openEditModal(quiz) {
        document.getElementById('edit_quiz_id').value = quiz.id;
        document.getElementById('edit_title').value = quiz.title;
        document.getElementById('edit_description').value = quiz.description || '';
        document.getElementById('edit_module_id').value = quiz.module_id;
        document.getElementById('edit_content').value = quiz.content || '';
        document.getElementById('edit_status').value = quiz.status;
        document.getElementById('edit_time_limit').value = quiz.time_limit || 0;
        
        if (quiz.publish_time) {
            const publishDate = new Date(quiz.publish_time);
            document.getElementById('edit_publish_time').value = formatDateTimeLocal(publishDate);
        } else {
            document.getElementById('edit_publish_time').value = '';
        }
        
        if (quiz.deadline_time) {
            const deadlineDate = new Date(quiz.deadline_time);
            document.getElementById('edit_deadline_time').value = formatDateTimeLocal(deadlineDate);
        } else {
            document.getElementById('edit_deadline_time').value = '';
        }
        
        document.getElementById('editQuizModal').classList.add('show');
    }

    function closeEditModal() {
        document.getElementById('editQuizModal').classList.remove('show');
    }

    function formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Search and Filter
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const quizRows = document.querySelectorAll('.quiz-row');

        function filterQuizzes() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();

            quizRows.forEach(row => {
                const title = row.getAttribute('data-title').toLowerCase();
                const status = row.getAttribute('data-status').toLowerCase();

                const matchesSearch = title.includes(searchTerm);
                const matchesStatus = statusValue === 'all' || status === statusValue;

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterQuizzes);
        statusFilter.addEventListener('change', filterQuizzes);

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

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });

        // Close modals when clicking outside
        document.getElementById('addQuizModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('editQuizModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    });
</script>

</body>
</html>