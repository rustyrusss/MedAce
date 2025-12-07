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

    // Handle adding new subject via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
        header('Content-Type: application/json');
        
        $subjectName = trim($_POST['subject_name'] ?? '');
        $subjectDescription = trim($_POST['subject_description'] ?? '');
        
        if (empty($subjectName)) {
            echo json_encode(['success' => false, 'error' => 'Subject name is required']);
            exit();
        }
        
        try {
            // Get max display_order
            $stmt = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM subjects");
            $nextOrder = $stmt->fetchColumn();
            
            $stmt = $conn->prepare("INSERT INTO subjects (name, description, display_order, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
            $stmt->execute([$subjectName, $subjectDescription ?: null, $nextOrder]);
            $newId = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'id' => $newId, 
                'name' => $subjectName
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'error' => 'Subject already exists']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
        }
        exit();
    }

    // ✅ Handle quiz creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_quiz') {
        try {
            $title = trim($_POST['title']);
            $subject = trim($_POST['subject'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $time_limit = intval($_POST['time_limit']);
            $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
            $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
            
            // ✅ Handle module_id properly - convert empty string to NULL
            $module_id = (!empty($_POST['module_id']) && $_POST['module_id'] !== '') ? intval($_POST['module_id']) : null;
            
            // ✅ Handle prerequisite_module_id properly
            $prerequisite_module_id = (!empty($_POST['prerequisite_module_id']) && $_POST['prerequisite_module_id'] !== '') ? intval($_POST['prerequisite_module_id']) : null;
            
            // Validate required fields
            if (empty($title)) {
                throw new Exception("Title is required.");
            }
            
            if (empty($subject)) {
                throw new Exception("Subject is required.");
            }
            
            if ($time_limit < 1) {
                throw new Exception("Time limit is required and must be at least 1 minute.");
            }
            
            // ✅ Verify module belongs to professor (only if module is selected)
            if ($module_id !== null && $module_id > 0) {
                $stmt = $conn->prepare("SELECT id, subject FROM modules WHERE id = ? AND professor_id = ?");
                $stmt->execute([$module_id, $professorId]);
                $moduleRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$moduleRow) {
                    throw new Exception("Invalid module selected. The module doesn't exist or doesn't belong to you.");
                }

                // (Optional) Keep subject consistent with module's subject if it exists
                if (!empty($moduleRow['subject'])) {
                    $subject = $moduleRow['subject'];
                }
            }
            
            // ✅ Verify prerequisite module belongs to professor (if set)
            if ($prerequisite_module_id !== null && $prerequisite_module_id > 0) {
                $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND professor_id = ?");
                $stmt->execute([$prerequisite_module_id, $professorId]);
                if (!$stmt->fetch()) {
                    throw new Exception("Invalid prerequisite module selected.");
                }
            }
            
            // ✅ Insert quiz - NULL will be inserted for module_id if not selected
            $stmt = $conn->prepare("
                INSERT INTO quizzes (title, subject, description, module_id, lesson_id, professor_id, content, status, time_limit, publish_time, deadline_time, prerequisite_module_id, created_at) 
                VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $title,
                $subject,
                $description,
                $module_id,  // Will be NULL if no module selected
                $professorId,
                $content,
                $status,
                $time_limit,
                $publish_time,
                $deadline_time,
                $prerequisite_module_id  // Will be NULL if no prerequisite selected
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
            $subject = trim($_POST['subject'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $time_limit = intval($_POST['time_limit']);
            $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
            $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
            
            // ✅ Handle module_id properly
            $module_id = (!empty($_POST['module_id']) && $_POST['module_id'] !== '') ? intval($_POST['module_id']) : null;
            
            // ✅ Handle prerequisite_module_id properly
            $prerequisite_module_id = (!empty($_POST['prerequisite_module_id']) && $_POST['prerequisite_module_id'] !== '') ? intval($_POST['prerequisite_module_id']) : null;
            
            // Validate
            if (empty($title)) {
                throw new Exception("Title is required.");
            }
            
            if (empty($subject)) {
                throw new Exception("Subject is required.");
            }
            
            if ($time_limit < 1) {
                throw new Exception("Time limit is required and must be at least 1 minute.");
            }
            
            // Verify quiz belongs to professor
            $stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND professor_id = ?");
            $stmt->execute([$quiz_id, $professorId]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid quiz selected.");
            }
            
            // ✅ Verify module belongs to professor (only if module is selected)
            if ($module_id !== null && $module_id > 0) {
                $stmt = $conn->prepare("SELECT id, subject FROM modules WHERE id = ? AND professor_id = ?");
                $stmt->execute([$module_id, $professorId]);
                $moduleRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$moduleRow) {
                    throw new Exception("Invalid module selected.");
                }

                // (Optional) Keep subject consistent with module's subject
                if (!empty($moduleRow['subject'])) {
                    $subject = $moduleRow['subject'];
                }
            }
            
            // ✅ Verify prerequisite module (if set)
            if ($prerequisite_module_id !== null && $prerequisite_module_id > 0) {
                $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND professor_id = ?");
                $stmt->execute([$prerequisite_module_id, $professorId]);
                if (!$stmt->fetch()) {
                    throw new Exception("Invalid prerequisite module selected.");
                }
            }
            
            // Update quiz
            $stmt = $conn->prepare("
                UPDATE quizzes 
                SET title = ?, subject = ?, description = ?, module_id = ?, content = ?, status = ?, 
                    time_limit = ?, publish_time = ?, deadline_time = ?, prerequisite_module_id = ?
                WHERE id = ? AND professor_id = ?
            ");
            $stmt->execute([
                $title,
                $subject,
                $description,
                $module_id,
                $content,
                $status,
                $time_limit,
                $publish_time,
                $deadline_time,
                $prerequisite_module_id,
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

    // Fetch subjects from existing table
    try {
        $stmt = $conn->prepare("
            SELECT id, name, description, icon, color FROM subjects 
            WHERE status = 'active'
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subjects = [];
    }

    // ✅ Fetch modules for dropdown
    $stmt = $conn->prepare("SELECT id, title, subject FROM modules WHERE professor_id = :professor_id ORDER BY created_at DESC");
    $stmt->bindParam(':professor_id', $professorId);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Fetch quizzes with attempt counts and prerequisite module info
    try {
        $stmt = $conn->prepare("
            SELECT q.id, q.title, q.subject, q.description, q.status, q.time_limit, q.created_at, q.module_id, q.content, q.publish_time, q.deadline_time, q.prerequisite_module_id,
                m.title AS module_title,
                pm.title AS prerequisite_module_title,
                COUNT(DISTINCT qa.student_id) as attempts_count,
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students
            FROM quizzes q
            LEFT JOIN modules m ON q.module_id = m.id
            LEFT JOIN modules pm ON q.prerequisite_module_id = pm.id
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
            WHERE q.professor_id = :professor_id
            GROUP BY q.id
            ORDER BY q.created_at DESC
        ");
        $stmt->bindParam(':professor_id', $professorId);
        $stmt->execute();
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback if prerequisite_module_id column doesn't exist
        $stmt = $conn->prepare("
            SELECT q.id, q.title, q.subject, q.description, q.status, q.time_limit, q.created_at, q.module_id, q.content, q.publish_time, q.deadline_time, 
                m.title AS module_title,
                COUNT(DISTINCT qa.student_id) as attempts_count,
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students
            FROM quizzes q
            LEFT JOIN modules m ON q.module_id = m.id
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
            WHERE q.professor_id = :professor_id
            GROUP BY q.id
            ORDER BY q.created_at DESC
        ");
        $stmt->bindParam(':professor_id', $professorId);
        $stmt->execute();
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
                overflow-x: hidden;
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

            /* Subject dropdown with add new option */
            .subject-dropdown-container {
                position: relative;
            }

            .add-subject-inline {
                display: none;
                margin-top: 0.75rem;
                padding: 1rem;
                background: #f8fafc;
                border: 2px dashed #cbd5e1;
                border-radius: 0.5rem;
            }

            .add-subject-inline.show {
                display: block;
            }

            .sidebar-transition {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Desktop Sidebar States */
            @media (min-width: 1024px) {
                .sidebar-collapsed {
                    width: 5rem;
                    transform: translateX(0);
                }

                .sidebar-collapsed .nav-text,
                .sidebar-collapsed .profile-info {
                    opacity: 0;
                    width: 0;
                    overflow: hidden;
                }

                .sidebar-expanded {
                    width: 18rem;
                    transform: translateX(0);
                }

                .sidebar-expanded .nav-text,
                .sidebar-expanded .profile-info {
                    opacity: 1;
                    width: auto;
                }
            }

            /* Mobile Sidebar States */
            @media (max-width: 1023px) {
                .sidebar-collapsed {
                    width: 18rem;
                    transform: translateX(-100%);
                }

                .sidebar-collapsed .nav-text,
                .sidebar-collapsed .profile-info {
                    opacity: 1;
                    width: auto;
                }
                
                .sidebar-expanded {
                    width: 18rem;
                    transform: translateX(0);
                }

                .sidebar-expanded .nav-text,
                .sidebar-expanded .profile-info {
                    opacity: 1;
                    width: auto;
                }
            }

            /* Sidebar Toggle Icon */
            .sidebar-toggle-btn {
                width: 40px;
                height: 40px;
                position: relative;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                background: transparent;
                border: 2px solid #cbd5e1;
                border-radius: 8px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                padding: 0;
                flex-shrink: 0;
            }

            .sidebar-toggle-btn:hover {
                border-color: #0ea5e9;
                background: #f0f9ff;
            }

            .sidebar-toggle-btn:active {
                transform: scale(0.95);
            }

            .sidebar-toggle-btn .toggle-icon {
                width: 24px;
                height: 24px;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar-toggle-btn .toggle-icon::before {
                content: '';
                position: absolute;
                left: 2px;
                width: 3px;
                height: 16px;
                background-color: #64748b;
                border-radius: 2px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-toggle-btn .toggle-icon::after {
                content: '';
                position: absolute;
                right: 2px;
                width: 6px;
                height: 6px;
                border-right: 2px solid #64748b;
                border-bottom: 2px solid #64748b;
                transform: rotate(-45deg);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-toggle-btn:hover .toggle-icon::before,
            .sidebar-toggle-btn:hover .toggle-icon::after {
                border-color: #0ea5e9;
                background-color: #0ea5e9;
            }

            .sidebar-toggle-btn.active .toggle-icon::after {
                transform: rotate(135deg);
                right: 4px;
            }

            .sidebar-toggle-btn.active .toggle-icon::before {
                background-color: #0ea5e9;
            }

            .sidebar-toggle-btn.active {
                border-color: #0ea5e9;
                background: #f0f9ff;
            }

            @media (max-width: 1023px) {
                .sidebar-toggle-btn {
                    width: 36px;
                    height: 36px;
                }

                .sidebar-toggle-btn .toggle-icon {
                    width: 20px;
                    height: 20px;
                }

                .sidebar-toggle-btn .toggle-icon::before {
                    height: 14px;
                }

                .sidebar-toggle-btn .toggle-icon::after {
                    width: 5px;
                    height: 5px;
                }
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
                margin: 1rem;
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

            #sidebar-overlay {
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease-in-out;
            }

            #sidebar-overlay.show {
                opacity: 1;
                pointer-events: auto;
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

            /* Mobile Compact Card View */
            @media (max-width: 768px) {
                .quiz-card {
                    background: white;
                    border-radius: 0.75rem;
                    padding: 1rem;
                    margin-bottom: 0.75rem;
                    border: 1px solid #e5e7eb;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                }

                .quiz-card-header {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.75rem;
                    margin-bottom: 0.75rem;
                }

                .quiz-card-icon {
                    flex-shrink: 0;
                    width: 2.5rem;
                    height: 2.5rem;
                    background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
                    border-radius: 0.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                }

                .quiz-card-content {
                    flex: 1;
                    min-width: 0;
                }

                .quiz-card-title {
                    font-weight: 600;
                    font-size: 0.9375rem;
                    color: #111827;
                    margin-bottom: 0.25rem;
                    line-height: 1.3;
                }

                .quiz-card-meta {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                    font-size: 0.75rem;
                    color: #6b7280;
                    margin-bottom: 0.5rem;
                }

                .quiz-card-meta-item {
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                }

                .quiz-card-stats {
                    display: flex;
                    gap: 0.75rem;
                    padding: 0.75rem;
                    background: #f9fafb;
                    border-radius: 0.5rem;
                    margin-bottom: 0.75rem;
                }

                .quiz-card-stat {
                    flex: 1;
                    text-align: center;
                }

                .quiz-card-stat-value {
                    font-weight: 700;
                    font-size: 1.125rem;
                    color: #111827;
                    display: block;
                }

                .quiz-card-stat-label {
                    font-size: 0.6875rem;
                    color: #6b7280;
                    text-transform: uppercase;
                    letter-spacing: 0.025em;
                }

                .quiz-card-actions {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 0.5rem;
                }

                .quiz-card-action-btn {
                    padding: 0.5rem;
                    border-radius: 0.5rem;
                    font-size: 0.8125rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.375rem;
                    transition: all 0.2s;
                    text-decoration: none;
                    border: none;
                    cursor: pointer;
                    background: transparent;
                }
            }

            @media (max-width: 1024px) {
                #main-content {
                    margin-left: 0 !important;
                }

                #sidebar-overlay {
                    display: none;
                }

                #sidebar-overlay.show {
                    display: block;
                }
            }

            @media (max-width: 640px) {
                .modal-content {
                    padding: 1.25rem;
                    width: 95%;
                    margin: 0.5rem;
                }
            }

            body {
                overflow-x: hidden;
            }

            #main-content {
                max-width: 100vw;
                overflow-x: hidden;
            }
        </style>
        
        <!-- Chart.js for progress graphs -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                    <button onclick="toggleSidebar()" class="sidebar-toggle-btn w-full" id="hamburgerBtn" aria-label="Toggle sidebar">
                        <div class="toggle-icon"></div>
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
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300" onclick="closeSidebar()"></div>

        <!-- Main Content -->
        <main id="main-content" class="flex-1 w-full transition-all duration-300 lg:ml-20">
            <!-- Top Bar -->
            <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-3 sm:px-6 lg:px-8 py-3 sm:py-4">
                <div class="flex items-center justify-between">
                    <button onclick="toggleSidebar()" class="sidebar-toggle-btn lg:hidden" id="mobileHamburgerBtn" aria-label="Toggle sidebar">
                        <div class="toggle-icon"></div>
                    </button>
                    <div class="flex items-center space-x-2 sm:space-x-3 ml-auto">
                        <button onclick="openAddModal()" class="bg-primary-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors font-semibold flex items-center space-x-2 shadow-sm text-sm">
                            <i class="fas fa-plus text-sm"></i>
                            <span class="hidden sm:inline">Add Quiz</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
                <!-- Success Message -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 sm:mb-6 bg-green-50 border border-green-200 text-green-800 px-3 sm:px-4 py-2 sm:py-3 rounded-lg animate-fade-in-up">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 sm:mr-3 text-base sm:text-lg"></i>
                        <span class="font-medium text-sm sm:text-base"><?= htmlspecialchars($_SESSION['success']) ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['success']); endif; ?>

                <!-- Error Message -->
                <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 sm:mb-6 bg-red-50 border border-red-200 text-red-800 px-3 sm:px-4 py-2 sm:py-3 rounded-lg animate-fade-in-up">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 sm:mr-3 text-base sm:text-lg"></i>
                        <span class="font-medium text-sm sm:text-base"><?= htmlspecialchars($_SESSION['error']) ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- Page Header -->
                <div class="mb-4 sm:mb-8 animate-fade-in-up">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 sm:mb-2">Manage Quizzes</h1>
                    <p class="text-gray-600 text-sm sm:text-base">Create, edit, and organize your course quizzes</p>
                </div>

                <!-- Search and Filter Bar -->
                <div class="mb-4 sm:mb-6 animate-slide-in">
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                        <div class="relative flex-1">
                            <input type="text" id="searchInput" placeholder="Search quizzes..." 
                                class="w-full pl-10 sm:pl-12 pr-3 sm:pr-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                            <i class="fas fa-search absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        </div>
                        <select id="statusFilter" class="px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in-up">
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
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Quiz</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Subject</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">Module</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden xl:table-cell">Prerequisite</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Participation</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="quizTableBody">
                                <?php foreach ($quizzes as $index => $quiz): ?>
                                <tr class="table-row-hover quiz-row" 
                                    data-status="<?= strtolower($quiz['status']) ?>" 
                                    data-title="<?= htmlspecialchars($quiz['title']) ?>"
                                    data-subject="<?= htmlspecialchars($quiz['subject'] ?? '') ?>">
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
                                    <td class="px-6 py-4 text-sm">
                                        <?php if (!empty($quiz['subject'])): ?>
                                            <span class="badge bg-purple-50 text-purple-700">
                                                <i class="fas fa-book-open mr-1"></i>
                                                <?= htmlspecialchars($quiz['subject']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No subject</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 hidden lg:table-cell">
                                        <?= htmlspecialchars($quiz['module_title'] ?? '—') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm hidden xl:table-cell">
                                        <?php if (!empty($quiz['prerequisite_module_title'])): ?>
                                            <span class="badge bg-amber-50 text-amber-700">
                                                <i class="fas fa-lock mr-1"></i>
                                                <?= htmlspecialchars($quiz['prerequisite_module_title']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <?php 
                                                        $percentage = $quiz['total_students'] > 0 
                                                            ? ($quiz['attempts_count'] / $quiz['total_students']) * 100 
                                                            : 0;
                                                    ?>
                                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?= round($percentage) ?>%"></div>
                                                </div>
                                            </div>
                                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap">
                                                <?= $quiz['attempts_count'] ?>/<?= $quiz['total_students'] ?>
                                            </span>
                                        </div>
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
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="openParticipantsModal(<?= $quiz['id'] ?>, '<?= htmlspecialchars(addslashes($quiz['title'])) ?>')" 
                                                    class="inline-flex items-center px-3 py-2 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 font-medium transition-colors text-sm"
                                                    title="View Participants">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <a href="manage_questions.php?quiz_id=<?= $quiz['id'] ?>" 
                                            class="inline-flex items-center px-3 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 font-medium transition-colors text-sm"
                                            title="Manage Questions">
                                                <i class="fas fa-question-circle"></i>
                                            </a>
                                            <button onclick='openEditModal(<?= json_encode($quiz) ?>)' 
                                                    class="inline-flex items-center px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-medium transition-colors text-sm"
                                                    title="Edit Quiz">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="../actions/delete_quiz.php?id=<?= $quiz['id'] ?>" 
                                            onclick="return confirm('⚠️ Delete this quiz permanently?\n\nThis will delete all questions and student attempts!');" 
                                            class="inline-flex items-center px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium transition-colors text-sm"
                                            title="Delete Quiz">
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

                <!-- Mobile Card View -->
                <div class="md:hidden" id="mobileQuizList">
                    <?php if (count($quizzes) > 0): ?>
                        <?php foreach ($quizzes as $quiz): 
                            $percentage = $quiz['total_students'] > 0 
                                ? ($quiz['attempts_count'] / $quiz['total_students']) * 100 
                                : 0;
                            $status = strtolower($quiz['status']);
                            $statusConfig = match($status) {
                                'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle'],
                                'inactive' => ['bg' => 'bg-gray-200', 'text' => 'text-gray-700', 'icon' => 'fa-pause-circle'],
                                default => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-info-circle']
                            };
                        ?>
                        <div class="quiz-card quiz-row-mobile" 
                            data-status="<?= $status ?>" 
                            data-title="<?= htmlspecialchars($quiz['title']) ?>"
                            data-subject="<?= htmlspecialchars($quiz['subject'] ?? '') ?>">
                            <div class="quiz-card-header">
                                <div class="quiz-card-icon">
                                    <i class="fas fa-clipboard-question"></i>
                                </div>
                                <div class="quiz-card-content">
                                    <h3 class="quiz-card-title"><?= htmlspecialchars($quiz['title']) ?></h3>
                                    <div class="quiz-card-meta">
                                        <?php if (!empty($quiz['subject'])): ?>
                                            <span class="quiz-card-meta-item">
                                                <i class="fas fa-book-open"></i>
                                                <?= htmlspecialchars($quiz['subject']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($quiz['time_limit']) && $quiz['time_limit'] > 0): ?>
                                            <span class="quiz-card-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <?= $quiz['time_limit'] ?> min
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($quiz['prerequisite_module_title'])): ?>
                                            <span class="quiz-card-meta-item">
                                                <i class="fas fa-lock"></i>
                                                Req: <?= htmlspecialchars($quiz['prerequisite_module_title']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?> text-xs py-1 px-2">
                                            <i class="fas <?= $statusConfig['icon'] ?>"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="quiz-card-stats">
                                <div class="quiz-card-stat">
                                    <span class="quiz-card-stat-value text-green-600"><?= $quiz['attempts_count'] ?></span>
                                    <span class="quiz-card-stat-label">Taken</span>
                                </div>
                                <div class="quiz-card-stat">
                                    <span class="quiz-card-stat-value text-orange-600"><?= $quiz['total_students'] - $quiz['attempts_count'] ?></span>
                                    <span class="quiz-card-stat-label">Pending</span>
                                </div>
                                <div class="quiz-card-stat">
                                    <span class="quiz-card-stat-value"><?= round($percentage) ?>%</span>
                                    <span class="quiz-card-stat-label">Rate</span>
                                </div>
                            </div>

                            <div class="quiz-card-actions">
                                <button onclick="openParticipantsModal(<?= $quiz['id'] ?>, '<?= htmlspecialchars(addslashes($quiz['title'])) ?>')" 
                                        class="quiz-card-action-btn bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
                                    <i class="fas fa-users"></i>
                                    <span>Participants</span>
                                </button>
                                <a href="manage_questions.php?quiz_id=<?= $quiz['id'] ?>" 
                                class="quiz-card-action-btn bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                                    <i class="fas fa-question-circle"></i>
                                    <span>Questions</span>
                                </a>
                                <button onclick='openEditModal(<?= json_encode($quiz) ?>)' 
                                        class="quiz-card-action-btn bg-blue-50 text-blue-700 hover:bg-blue-100">
                                    <i class="fas fa-edit"></i>
                                    <span>Edit</span>
                                </button>
                                <a href="../actions/delete_quiz.php?id=<?= $quiz['id'] ?>" 
                                onclick="return confirm('⚠️ Delete this quiz?');" 
                                class="quiz-card-action-btn bg-red-50 text-red-700 hover:bg-red-100">
                                    <i class="fas fa-trash-alt"></i>
                                    <span>Delete</span>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12 px-4">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-3">
                                <i class="fas fa-clipboard-list text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 mb-1">No quizzes yet</h3>
                            <p class="text-gray-600 mb-4 text-sm">Create your first quiz</p>
                            <button onclick="openAddModal()" class="bg-primary-600 text-white px-5 py-2.5 rounded-lg hover:bg-primary-700 transition-colors font-semibold inline-flex items-center space-x-2 text-sm">
                                <i class="fas fa-plus"></i>
                                <span>Add Quiz</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Quiz Modal - UPDATED -->
    <div id="addQuizModal" class="modal">
        <div class="modal-content">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Add New Quiz</h2>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4 sm:space-y-5">
                <input type="hidden" name="action" value="add_quiz">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-heading text-primary-500 mr-1"></i>
                        Quiz Title *
                    </label>
                    <input type="text" name="title" required 
                        class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                        placeholder="e.g., Anatomy Midterm Quiz">
                </div>

                <!-- UPDATED SUBJECT DROPDOWN -->
                <div class="subject-dropdown-container">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-book-open text-primary-500 mr-1"></i>
                        Subject *
                    </label>
                    <div class="flex gap-2">
                        <select name="subject" id="addQuizSubjectSelect" required 
                                class="flex-1 px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= htmlspecialchars($subject['name']) ?>" 
                                        data-icon="<?= htmlspecialchars($subject['icon'] ?? '') ?>"
                                        data-color="<?= htmlspecialchars($subject['color'] ?? '') ?>">
                                    <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__add_new__">+ Add New Subject</option>
                        </select>
                    </div>
                    
                    <!-- Add New Subject Inline Form -->
                    <div id="addQuizNewSubjectForm" class="add-subject-inline">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-plus-circle text-green-500 mr-1"></i>
                            Add New Subject
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Subject Name *</label>
                                <input type="text" id="addQuizNewSubjectName" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                    placeholder="e.g., Medical-Surgical Nursing">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Description (Optional)</label>
                                <input type="text" id="addQuizNewSubjectDescription" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                    placeholder="e.g., Care of adult patients with medical conditions">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="saveNewQuizSubject('add')" 
                                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">
                                    <i class="fas fa-check mr-1"></i> Save Subject
                                </button>
                                <button type="button" onclick="cancelNewQuizSubject('add')" 
                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fas fa-book text-primary-500 mr-1"></i>
            Select Module <span class="text-gray-400 text-xs">(Optional)</span>
        </label>
        <select name="module_id" id="add_module_id" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
            <option value="">— No module (standalone quiz) —</option>
            <?php foreach ($modules as $module): ?>
                <option value="<?= $module['id'] ?>" data-subject="<?= htmlspecialchars($module['subject'] ?? '') ?>">
                    <?= htmlspecialchars($module['title']) ?>
                </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-amber-500 mr-1"></i>
                        Prerequisite Module (Optional)
                    </label>
                    <!-- ✅ Added ID for JS control -->
                    <select name="prerequisite_module_id" id="add_prerequisite_module_id" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
    <option value="">— No prerequisite —</option>
    <?php foreach ($modules as $module): ?>
        <option value="<?= $module['id'] ?>" data-subject="<?= htmlspecialchars(strtolower($module['subject'] ?? '')) ?>"><?= htmlspecialchars($module['title']) ?></option>
    <?php endforeach; ?>
</select>
                    <p class="text-xs text-gray-500 mt-1">Students must complete this module before taking the quiz</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file-lines text-primary-500 mr-1"></i>
                        Instructions
                    </label>
                    <textarea name="content" rows="2" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none text-sm sm:text-base"
                            placeholder="Additional instructions..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag text-primary-500 mr-1"></i>
                            Status
                        </label>
                        <select name="status" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock text-primary-500 mr-1"></i>
                            Time Limit (min) *
                        </label>
                        <input type="number" name="time_limit" min="1" required
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                            placeholder="Enter time limit in minutes">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-plus text-primary-500 mr-1"></i>
                            Publish Time
                        </label>
                        <input type="datetime-local" name="publish_time" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-xmark text-primary-500 mr-1"></i>
                            Deadline
                        </label>
                        <input type="datetime-local" name="deadline_time" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    </div>
                </div>
                
                <div class="flex gap-2 sm:gap-3 mt-4 sm:mt-6">
                    <button type="button" onclick="closeAddModal()" 
                            class="flex-1 bg-gray-200 text-gray-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors text-sm sm:text-base">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-primary-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors text-sm sm:text-base">
                        <i class="fas fa-save mr-2"></i>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Quiz Modal - UPDATED -->
    <div id="editQuizModal" class="modal">
        <div class="modal-content">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Edit Quiz</h2>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4 sm:space-y-5" id="editQuizForm">
                <input type="hidden" name="action" value="edit_quiz">
                <input type="hidden" name="quiz_id" id="edit_quiz_id">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-heading text-primary-500 mr-1"></i>
                        Quiz Title *
                    </label>
                    <input type="text" name="title" id="edit_title" required 
                        class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                </div>

                <!-- UPDATED SUBJECT DROPDOWN FOR EDIT -->
                <div class="subject-dropdown-container">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-book-open text-primary-500 mr-1"></i>
                        Subject *
                    </label>
                    <div class="flex gap-2">
                        <select name="subject" id="editQuizSubjectSelect" required 
                                class="flex-1 px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= htmlspecialchars($subject['name']) ?>"
                                        data-icon="<?= htmlspecialchars($subject['icon'] ?? '') ?>"
                                        data-color="<?= htmlspecialchars($subject['color'] ?? '') ?>">
                                    <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__add_new__">+ Add New Subject</option>
                        </select>
                    </div>
                    
                    <!-- Add New Subject Inline Form for Edit -->
                    <div id="editQuizNewSubjectForm" class="add-subject-inline">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-plus-circle text-green-500 mr-1"></i>
                            Add New Subject
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Subject Name *</label>
                                <input type="text" id="editQuizNewSubjectName" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                    placeholder="e.g., Medical-Surgical Nursing">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Description (Optional)</label>
                                <input type="text" id="editQuizNewSubjectDescription" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                    placeholder="e.g., Care of adult patients with medical conditions">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="saveNewQuizSubject('edit')" 
                                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">
                                    <i class="fas fa-check mr-1"></i> Save Subject
                                </button>
                                <button type="button" onclick="cancelNewQuizSubject('edit')" 
                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-align-left text-primary-500 mr-1"></i>
                        Description
                    </label>
                    <textarea name="description" id="edit_description" rows="3" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none text-sm sm:text-base"></textarea>
                </div>

            <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fas fa-book text-primary-500 mr-1"></i>
            Select Module <span class="text-gray-400 text-xs">(Optional)</span>
        </label>
        <select name="module_id" id="edit_module_id" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
            <option value="">— No module (standalone quiz) —</option>
            <?php foreach ($modules as $module): ?>
                <option value="<?= $module['id'] ?>" data-subject="<?= htmlspecialchars($module['subject'] ?? '') ?>">
                    <?= htmlspecialchars($module['title']) ?>
                </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-amber-500 mr-1"></i>
                        Prerequisite Module (Optional)
                    </label>
                    <select name="prerequisite_module_id" id="edit_prerequisite_module_id" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
    <option value="">— No prerequisite —</option>
    <?php foreach ($modules as $module): ?>
        <option value="<?= $module['id'] ?>" data-subject="<?= htmlspecialchars(strtolower($module['subject'] ?? '')) ?>"><?= htmlspecialchars($module['title']) ?></option>
    <?php endforeach; ?>
</select>
                    <p class="text-xs text-gray-500 mt-1">Students must complete this module before taking the quiz</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file-lines text-primary-500 mr-1"></i>
                        Instructions
                    </label>
                    <textarea name="content" id="edit_content" rows="2" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none text-sm sm:text-base"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag text-primary-500 mr-1"></i>
                            Status
                        </label>
                        <select name="status" id="edit_status" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock text-primary-500 mr-1"></i>
                            Time Limit (min) *
                        </label>
                        <input type="number" name="time_limit" id="edit_time_limit" min="1" required
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base"
                            placeholder="Enter time limit in minutes">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-plus text-primary-500 mr-1"></i>
                            Publish Time
                        </label>
                        <input type="datetime-local" name="publish_time" id="edit_publish_time" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-xmark text-primary-500 mr-1"></i>
                            Deadline
                        </label>
                        <input type="datetime-local" name="deadline_time" id="edit_deadline_time" 
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                    </div>
                </div>
                
                <div class="flex gap-2 sm:gap-3 mt-4 sm:mt-6">
                    <button type="button" onclick="closeEditModal()" 
                            class="flex-1 bg-gray-200 text-gray-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors text-sm sm:text-base">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-primary-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors text-sm sm:text-base">
                        <i class="fas fa-save mr-2"></i>
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Participants Modal -->
    <div id="participantsModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                    <i class="fas fa-users text-emerald-600 mr-2"></i>
                    <span id="participantsModalTitle">Quiz Participants</span>
                </h2>
                <button onclick="closeParticipantsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
            
            <div id="participantsContent">
                <div class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                    <p class="text-gray-600 mt-4">Loading participants...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Attempts Modal -->
    <div id="attemptsModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                    <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                    <span id="attemptsModalTitle">Student Attempts</span>
                </h2>
                <button onclick="closeAttemptsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
            
            <div id="attemptsContent">
                <div class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                    <p class="text-gray-600 mt-4">Loading attempts...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let sidebarExpanded = false;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const overlay = document.getElementById('sidebar-overlay');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const mobileHamburgerBtn = document.getElementById('mobileHamburgerBtn');
            
            sidebarExpanded = !sidebarExpanded;
            
            hamburgerBtn.classList.toggle('active');
            mobileHamburgerBtn.classList.toggle('active');
            
            if (window.innerWidth < 1024) {
                sidebar.classList.toggle('sidebar-expanded');
                sidebar.classList.toggle('sidebar-collapsed');
                overlay.classList.toggle('hidden');
                overlay.classList.toggle('show');
                
                if (sidebarExpanded) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'auto';
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

        function openAddModal() {
            document.getElementById('addQuizModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addQuizModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            cancelNewQuizSubject('add'); // Clear subject form when closing
        }

        // UPDATED: Modified to handle subject dropdown + module/subject sync
    function openEditModal(quiz) {
        // Set flag to prevent event listeners from interfering
        window.isOpeningEditModal = true;
        
        document.getElementById('edit_quiz_id').value = quiz.id;
        document.getElementById('edit_title').value = quiz.title;
        document.getElementById('edit_description').value = quiz.description || '';
        document.getElementById('edit_content').value = quiz.content || '';
        document.getElementById('edit_status').value = quiz.status;
        document.getElementById('edit_time_limit').value = quiz.time_limit || 1;
        
        const subjectSelect = document.getElementById('editQuizSubjectSelect');
        const moduleSelect = document.getElementById('edit_module_id');
        const subjectValue = (quiz.subject || '').trim();
        
        // Set prerequisite module FIRST (before other changes)
        document.getElementById('edit_prerequisite_module_id').value = quiz.prerequisite_module_id || '';
        
        // Set module
        moduleSelect.value = quiz.module_id;
        
        // Set dates
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
        
        cancelNewQuizSubject('edit');
        document.getElementById('editQuizModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Set subject AFTER modal is visible (critical!)
        setTimeout(() => {
            let subjectFound = false;
            
            // Find and select the subject
            for (let i = 0; i < subjectSelect.options.length; i++) {
                const option = subjectSelect.options[i];
                if (!option.value || option.value === '__add_new__') continue;
                
                if (option.value.trim() === subjectValue) {
                    subjectFound = true;
                    subjectSelect.selectedIndex = i;
                    break;
                }
            }
            
            // If not found, add it
            if (!subjectFound && subjectValue) {
                const newOption = new Option(subjectValue, subjectValue, true, true);
                const addNewOption = subjectSelect.querySelector('option[value="__add_new__"]');
                if (addNewOption) {
                    subjectSelect.insertBefore(newOption, addNewOption);
                } else {
                    subjectSelect.add(newOption);
                }
            }
            
            // Filter modules and update prerequisites
            filterModuleOptions(subjectValue, 'edit_module_id');
            moduleSelect.value = quiz.module_id;
            updatePrerequisiteOptions('edit_module_id', 'edit_prerequisite_module_id', quiz.prerequisite_module_id || null);
            
        }, 100);
        
        // Clear flag
        setTimeout(() => {
            window.isOpeningEditModal = false;
        }, 300);
    }

        function closeEditModal() {
            document.getElementById('editQuizModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            cancelNewQuizSubject('edit'); // Clear subject form when closing
        }

        // NEW: Subject dropdown handlers
        function cancelNewQuizSubject(formType) {
            if (formType === 'add') {
                document.getElementById('addQuizNewSubjectForm').classList.remove('show');
                document.getElementById('addQuizSubjectSelect').value = '';
                document.getElementById('addQuizNewSubjectName').value = '';
                document.getElementById('addQuizNewSubjectDescription').value = '';
            } else {
                document.getElementById('editQuizNewSubjectForm').classList.remove('show');
                const editSelect = document.getElementById('editQuizSubjectSelect');
                editSelect.value = editSelect.dataset.originalValue || '';
                document.getElementById('editQuizNewSubjectName').value = '';
                document.getElementById('editQuizNewSubjectDescription').value = '';
            }
        }

        // NEW: Save new subject via AJAX
        async function saveNewQuizSubject(formType) {
            const nameInput = formType === 'add' ? document.getElementById('addQuizNewSubjectName') : document.getElementById('editQuizNewSubjectName');
            const descInput = formType === 'add' ? document.getElementById('addQuizNewSubjectDescription') : document.getElementById('editQuizNewSubjectDescription');
            const selectEl = formType === 'add' ? document.getElementById('addQuizSubjectSelect') : document.getElementById('editQuizSubjectSelect');
            const formEl = formType === 'add' ? document.getElementById('addQuizNewSubjectForm') : document.getElementById('editQuizNewSubjectForm');
            
            const subjectName = nameInput.value.trim();
            const subjectDescription = descInput.value.trim();
            
            if (!subjectName) {
                alert('Subject name is required');
                nameInput.focus();
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_subject');
                formData.append('subject_name', subjectName);
                formData.append('subject_description', subjectDescription);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Add new option to both selects
                    const newOption = new Option(subjectName, subjectName);
                    
                    // Add to both quiz dropdowns (before the "Add New" option)
                    const addQuizSelect = document.getElementById('addQuizSubjectSelect');
                    const editQuizSelect = document.getElementById('editQuizSubjectSelect');
                    
                    const addNewOptionAdd = addQuizSelect.querySelector('option[value="__add_new__"]');
                    const addNewOptionEdit = editQuizSelect.querySelector('option[value="__add_new__"]');
                    
                    addQuizSelect.insertBefore(newOption.cloneNode(true), addNewOptionAdd);
                    editQuizSelect.insertBefore(newOption.cloneNode(true), addNewOptionEdit);
                    
                    // Select the new subject
                    selectEl.value = subjectName;
                    
                    // Hide the form
                    formEl.classList.remove('show');
                    
                    // Clear inputs
                    nameInput.value = '';
                    descInput.value = '';
                    
                    alert('Subject "' + subjectName + '" added successfully!');
                } else {
                    alert('Error: ' + (data.error || 'Failed to add subject'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to add subject. Please try again.');
            }
        }

        function openParticipantsModal(quizId, quizTitle) {
            document.getElementById('participantsModalTitle').textContent = quizTitle + ' - Participants';
            document.getElementById('participantsModal').classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Reset content
            document.getElementById('participantsContent').innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                    <p class="text-gray-600 mt-4">Loading participants...</p>
                </div>
            `;
            
            // Load participants via AJAX
            fetch(`quiz_participants_ajax.php?quiz_id=${quizId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('participantsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('participantsContent').innerHTML = `
                        <div class="text-center py-12">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500"></i>
                            <p class="text-gray-600 mt-4">Error loading participants</p>
                        </div>
                    `;
                });
        }

        function closeParticipantsModal() {
            document.getElementById('participantsModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function openAttemptsModal(quizId, studentId, studentName) {
            document.getElementById('attemptsModalTitle').textContent = studentName + ' - Attempts';
            document.getElementById('attemptsModal').classList.add('show');
            
            // Reset content
            document.getElementById('attemptsContent').innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-primary-500"></i>
                    <p class="text-gray-600 mt-4">Loading attempts...</p>
                </div>
            `;
            
            // Load attempts via AJAX
            fetch(`student_attempts_ajax.php?quiz_id=${quizId}&student_id=${studentId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('attemptsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('attemptsContent').innerHTML = `
                        <div class="text-center py-12">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500"></i>
                            <p class="text-gray-600 mt-4">Error loading attempts</p>
                        </div>
                    `;
                });
        }

        function closeAttemptsModal() {
            document.getElementById('attemptsModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Function to toggle attempt details (for the attempts modal)
        window.toggleAttemptDetails = function(attemptId) {
            const detailsDiv = document.getElementById(`attempt-details-${attemptId}`);
            
            if (detailsDiv.classList.contains('hidden')) {
                detailsDiv.classList.remove('hidden');
                
                // Load details if not already loaded
                if (!detailsDiv.dataset.loaded) {
                    loadAttemptDetails(attemptId);
                    detailsDiv.dataset.loaded = 'true';
                }
            } else {
                detailsDiv.classList.add('hidden');
            }
        }

        function loadAttemptDetails(attemptId) {
            const detailsDiv = document.getElementById(`attempt-details-${attemptId}`);
            
            fetch(`attempts_details_ajax.php?attempt_id=${attemptId}`)
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

        // Grading functions (called from attempt details)
        window.autoSaveGrade = function(answerId, attemptId) {
            const points = document.getElementById(`points-${answerId}`).value;
            const feedback = document.getElementById(`feedback-${answerId}`).value;
            const statusDiv = document.getElementById(`save-status-${answerId}`);
            
            // Show saving indicator
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-primary-600"></i> Saving...';
            
            fetch('save_grade_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    answer_id: answerId,
                    attempt_id: attemptId,
                    points: parseInt(points),
                    feedback: feedback
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = '<i class="fas fa-check-circle text-green-600"></i> Saved';
                    setTimeout(() => {
                        statusDiv.innerHTML = '';
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<i class="fas fa-times-circle text-red-600"></i> Error';
                }
            })
            .catch(error => {
                statusDiv.innerHTML = '<i class="fas fa-times-circle text-red-600"></i> Error';
            });
        }

        window.recalculateScore = function(attemptId) {
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
                    
                    setTimeout(() => {
                        location.reload();
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

        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // NEW: Filter modules based on subject (for add/edit modals)
        function filterModuleOptions(subjectValue, moduleSelectId) {
    const moduleSelect = document.getElementById(moduleSelectId);
    if (!moduleSelect) return;

    const subject = (subjectValue || '').toLowerCase();
    const options = moduleSelect.querySelectorAll('option');

    options.forEach(option => {
        if (!option.value) return;
        const optSubject = (option.dataset.subject || '').toLowerCase();

        if (!subject) {
            option.disabled = false;
            option.classList.remove('hidden');
        } else {
            const matches = optSubject === subject;
            option.disabled = !matches;
            if (matches) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        }
    });

    if (moduleSelect.selectedOptions.length > 0 && moduleSelect.selectedOptions[0].disabled) {
        moduleSelect.value = '';
    }

    // ✅ Update prerequisite dropdown based on filtered modules
    const prereqSelectId = moduleSelectId === 'add_module_id' ? 'add_prerequisite_module_id' : 'edit_prerequisite_module_id';
    updatePrerequisiteOptionsForSubject(subject, prereqSelectId);
}

// ✅ NEW: Filter prerequisite options based on subject
function updatePrerequisiteOptionsForSubject(subjectValue, prereqSelectId) {
    const prereqSelect = document.getElementById(prereqSelectId);
    if (!prereqSelect) return;

    const subject = (subjectValue || '').toLowerCase();
    const options = prereqSelect.querySelectorAll('option');

    options.forEach(option => {
        const val = option.value;

        // Always keep "No prerequisite" visible
        if (val === '') {
            option.disabled = false;
            option.classList.remove('hidden');
            return;
        }

        // Filter by subject
        const optSubject = (option.dataset.subject || '').toLowerCase();

        if (!subject) {
            // If no subject selected, show all
            option.disabled = false;
            option.classList.remove('hidden');
        } else {
            // Only show modules from the same subject
            const matches = optSubject === subject;
            option.disabled = !matches;
            if (matches) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        }
    });

    // Reset to "No prerequisite" if current selection is now hidden
    if (prereqSelect.selectedOptions.length > 0 && prereqSelect.selectedOptions[0].disabled) {
        prereqSelect.value = '';
    }
}

        // NEW: When picking a module, auto-select its corresponding subject
        function syncSubjectWithModule(moduleSelectId, subjectSelectId) {
            const moduleSelect = document.getElementById(moduleSelectId);
            const subjectSelect = document.getElementById(subjectSelectId);
            if (!moduleSelect || !subjectSelect) return;

            const selectedOption = moduleSelect.selectedOptions[0];
            if (!selectedOption) return;

            const moduleSubject = selectedOption.dataset.subject || '';
            if (!moduleSubject) return;

            let subjectFound = false;
            for (let option of subjectSelect.options) {
                if (option.value === moduleSubject) {
                    subjectFound = true;
                    break;
                }
            }

            if (!subjectFound) {
                const newOption = new Option(moduleSubject, moduleSubject);
                const addNewOption = subjectSelect.querySelector('option[value="__add_new__"]');
                subjectSelect.insertBefore(newOption, addNewOption);
            }

            subjectSelect.value = moduleSubject;

            // After syncing subject, also filter modules so only matching ones are enabled
            filterModuleOptions(moduleSubject, moduleSelectId);
        }

        // ✅ NEW: Limit prerequisite dropdown to "No prerequisite" + selected module
        function updatePrerequisiteOptions(moduleSelectId, prereqSelectId, currentPrereqId = null) {
            const moduleSelect = document.getElementById(moduleSelectId);
            const prereqSelect = document.getElementById(prereqSelectId);
            if (!moduleSelect || !prereqSelect) return;

            const selectedModule = moduleSelect.value || '';

            const options = prereqSelect.querySelectorAll('option');
            options.forEach(option => {
                const val = option.value;

                // Always keep "No prerequisite" visible
                if (val === '') {
                    option.disabled = false;
                    option.classList.remove('hidden');
                    return;
                }

                // Keep selected module visible as allowed prerequisite
                // ALSO keep the existing prerequisite visible (for old data) if provided
                if (val === selectedModule || (currentPrereqId && val === String(currentPrereqId))) {
                    option.disabled = false;
                    option.classList.remove('hidden');
                } else {
                    option.disabled = true;
                    option.classList.add('hidden');
                }
            });

            // If current selected prerequisite is now disabled, reset it
            if (prereqSelect.selectedOptions.length > 0 && prereqSelect.selectedOptions[0].disabled) {
                // Prefer to switch to "No prerequisite"
                prereqSelect.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const quizRows = document.querySelectorAll('.quiz-row');
            const quizRowsMobile = document.querySelectorAll('.quiz-row-mobile');

            const addQuizSubjectSelect = document.getElementById('addQuizSubjectSelect');
            const editQuizSubjectSelect = document.getElementById('editQuizSubjectSelect');
            const addModuleSelect = document.getElementById('add_module_id');
            const editModuleSelect = document.getElementById('edit_module_id');

            // UPDATED: Subject dropdown event listeners (Add Quiz)
            if (addQuizSubjectSelect) {
                addQuizSubjectSelect.addEventListener('change', function() {
                    const value = this.value;

                    if (value === '__add_new__') {
                        document.getElementById('addQuizNewSubjectForm').classList.add('show');
                        document.getElementById('addQuizNewSubjectName').focus();
                        // Do not filter modules when adding new subject (no modules yet)
                    } else {
                        document.getElementById('addQuizNewSubjectForm').classList.remove('show');
                        // Filter modules by the selected subject
                        filterModuleOptions(value, 'add_module_id');
                    }
                });
            }

            // UPDATED: Subject dropdown event listeners (Edit Quiz)
            if (editQuizSubjectSelect) {
                editQuizSubjectSelect.addEventListener('change', function() {
                    const value = this.value;

                    if (value === '__add_new__') {
                        this.dataset.originalValue = this.dataset.originalValue || '';
                        document.getElementById('editQuizNewSubjectForm').classList.add('show');
                        document.getElementById('editQuizNewSubjectName').focus();
                        // Do not filter modules when adding new subject (no modules yet)
                    } else {
                        document.getElementById('editQuizNewSubjectForm').classList.remove('show');
                        // Filter modules by the selected subject
                        filterModuleOptions(value, 'edit_module_id');
                    }
                });
            }

            // NEW: Module change -> sync subject + update prerequisite (Add Quiz)
            if (addModuleSelect && addQuizSubjectSelect) {
                addModuleSelect.addEventListener('change', function() {
                    syncSubjectWithModule('add_module_id', 'addQuizSubjectSelect');
                    updatePrerequisiteOptions('add_module_id', 'add_prerequisite_module_id');
                });
            }

            // NEW: Module change -> sync subject + update prerequisite (Edit Quiz)
            if (editModuleSelect && editQuizSubjectSelect) {
                editModuleSelect.addEventListener('change', function() {
                    syncSubjectWithModule('edit_module_id', 'editQuizSubjectSelect');
                    // Use current selected prerequisite as "currentPrereqId" to preserve it if needed
                    const currentPrereqId = document.getElementById('edit_prerequisite_module_id').value || null;
                    updatePrerequisiteOptions('edit_module_id', 'edit_prerequisite_module_id', currentPrereqId);
                });
            }

            function filterQuizzes() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value.toLowerCase();

                // Filter desktop table rows
                quizRows.forEach(row => {
                    const title = row.getAttribute('data-title').toLowerCase();
                    const subject = row.getAttribute('data-subject').toLowerCase();
                    const status = row.getAttribute('data-status').toLowerCase();

                    const matchesSearch = title.includes(searchTerm) || subject.includes(searchTerm);
                    const matchesStatus = statusValue === 'all' || status === statusValue;

                    if (matchesSearch && matchesStatus) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Filter mobile cards
                quizRowsMobile.forEach(card => {
                    const title = card.getAttribute('data-title').toLowerCase();
                    const subject = card.getAttribute('data-subject').toLowerCase();
                    const status = card.getAttribute('data-status').toLowerCase();

                    const matchesSearch = title.includes(searchTerm) || subject.includes(searchTerm);
                    const matchesStatus = statusValue === 'all' || status === statusValue;

                    if (matchesSearch && matchesStatus) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            searchInput.addEventListener('input', filterQuizzes);
            statusFilter.addEventListener('change', filterQuizzes);

            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            if (window.innerWidth >= 1024) {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.classList.remove('sidebar-expanded');
                mainContent.style.marginLeft = '5rem';
                sidebarExpanded = false;
            } else {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.classList.remove('sidebar-expanded');
                mainContent.style.marginLeft = '0';
                sidebarExpanded = false;
            }

            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    const sidebar = document.getElementById('sidebar');
                    const mainContent = document.getElementById('main-content');
                    const overlay = document.getElementById('sidebar-overlay');
                    const hamburgerBtn = document.getElementById('hamburgerBtn');
                    const mobileHamburgerBtn = document.getElementById('mobileHamburgerBtn');
                    
                    if (window.innerWidth >= 1024) {
                        overlay.classList.add('hidden');
                        overlay.classList.remove('show');
                        document.body.style.overflow = 'auto';
                        
                        if (!sidebar.classList.contains('sidebar-collapsed') && !sidebar.classList.contains('sidebar-expanded')) {
                            sidebar.classList.add('sidebar-collapsed');
                            sidebarExpanded = false;
                        }
                        
                        if (sidebarExpanded) {
                            mainContent.style.marginLeft = '18rem';
                        } else {
                            mainContent.style.marginLeft = '5rem';
                        }
                    } else {
                        mainContent.style.marginLeft = '0';
                        
                        if (sidebarExpanded) {
                            sidebar.classList.remove('sidebar-collapsed');
                            sidebar.classList.add('sidebar-expanded');
                            overlay.classList.remove('hidden');
                            overlay.classList.add('show');
                        } else {
                            sidebar.classList.add('sidebar-collapsed');
                            sidebar.classList.remove('sidebar-expanded');
                            overlay.classList.add('hidden');
                            overlay.classList.remove('show');
                            hamburgerBtn.classList.remove('active');
                            mobileHamburgerBtn.classList.remove('active');
                            document.body.style.overflow = 'auto';
                        }
                    }
                }, 250);
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAddModal();
                    closeEditModal();
                    closeParticipantsModal();
                    closeAttemptsModal();
                    if (sidebarExpanded && window.innerWidth < 1024) {
                        closeSidebar();
                    }
                }
            });

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

            document.getElementById('participantsModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeParticipantsModal();
                }
            });

            document.getElementById('attemptsModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAttemptsModal();
                }
            });
        });

        let touchStartX = 0;
        let touchEndX = 0;
        
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, false);
        
        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, false);
        
        function handleSwipe() {
            if (window.innerWidth < 1024) {
                if (touchEndX - touchStartX > 50 && !sidebarExpanded) {
                    toggleSidebar();
                }
                if (touchStartX - touchEndX > 50 && sidebarExpanded) {
                    toggleSidebar();
                }
            }
        }
    </script>

    </body>
    </html>
