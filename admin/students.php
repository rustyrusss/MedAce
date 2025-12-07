<?php
session_start();
require_once '../config/db_conn.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow dean
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dean') {
    header("Location: ../public/index.php");
    exit();
}

$deanId = $_SESSION['user_id'];

// Fetch dean info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$deanId]);
$dean = $stmt->fetch(PDO::FETCH_ASSOC);
$deanName = $dean ? $dean['firstname'] . " " . $dean['lastname'] : "Dean";

// Default avatar
if (!empty($dean['gender'])) {
    if (strtolower($dean['gender']) === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif (strtolower($dean['gender']) === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}

$profilePic = !empty($dean['profile_pic']) ? "../" . $dean['profile_pic'] : $defaultAvatar;

// Handle suspend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suspend_student'])) {
    $studentId = intval($_POST['student_id']);
    
    $suspendStmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'student'");
    if ($suspendStmt->execute([$studentId])) {
        $_SESSION['success_message'] = "Student account suspended successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to suspend student.";
    }
    header("Location: students.php");
    exit();
}

// Handle unsuspend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsuspend_student'])) {
    $studentId = intval($_POST['student_id']);
    
    $unsuspendStmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'student'");
    if ($unsuspendStmt->execute([$studentId])) {
        $_SESSION['success_message'] = "Student account re-approved successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to re-approve student.";
    }
    header("Location: students.php");
    exit();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studentId = intval($_POST['student_id']);
    
    // Optional: Check if student has related data (quizzes, results, etc.) before deleting
    // You might want to use CASCADE DELETE in your database or handle related records here
    
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    if ($deleteStmt->execute([$studentId])) {
        $_SESSION['success_message'] = "Student deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete student.";
    }
    header("Location: students.php");
    exit();
}

// Handle bulk suspend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_suspend'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $bulkSuspendStmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id IN ($placeholders) AND role = 'student'");
        if ($bulkSuspendStmt->execute($studentIds)) {
            $_SESSION['success_message'] = count($studentIds) . " student(s) suspended successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to suspend students.";
        }
    }
    header("Location: students.php");
    exit();
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $bulkDeleteStmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'student'");
        if ($bulkDeleteStmt->execute($studentIds)) {
            $_SESSION['success_message'] = count($studentIds) . " student(s) deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete students.";
        }
    }
    header("Location: students.php");
    exit();
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $studentId = intval($_POST['student_id']);
    $section = trim($_POST['section']);
    $year = trim($_POST['year']);
    
    $updateStmt = $conn->prepare("UPDATE users SET section = ?, year = ? WHERE id = ? AND role = 'student'");
    if ($updateStmt->execute([$section, $year, $studentId])) {
        $_SESSION['success_message'] = "Student information updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update student information.";
    }
    header("Location: students.php");
    exit();
}

// Handle approve request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_student'])) {
    $studentId = intval($_POST['student_id']);

    // 1. Get student information BEFORE approving
    $stmt = $conn->prepare("SELECT firstname, lastname, email FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error_message'] = "Student not found.";
        header("Location: students.php");
        exit();
    }

    // 2. Approve student
    $approveStmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'student'");
    if ($approveStmt->execute([$studentId])) {
        
        // 3. Load email functions
        require_once '../config/email_config.php';

        // 4. Prepare email
        $subject = "MedAce - Student Account Approved!";
        $body = getApprovalEmailHTML(
            $student['firstname'],
            $student['lastname'],
            $student['email']
        );

        // 5. Send email
        $emailSent = sendEmail($student['email'], $subject, $body);

        // 6. Success message
        if ($emailSent) {
            $_SESSION['success_message'] = "Student approved successfully! Email sent.";
        } else {
            $_SESSION['success_message'] = "Student approved successfully — but email could NOT be sent.";
        }

    } else {
        $_SESSION['error_message'] = "Failed to approve student.";
    }

    header("Location: students.php");
    exit();
}

// Handle reject request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_student'])) {
    $studentId = intval($_POST['student_id']);
    
    $rejectStmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'student'");
    if ($rejectStmt->execute([$studentId])) {
        $_SESSION['success_message'] = "Student rejected.";
    } else {
        $_SESSION['error_message'] = "Failed to reject student.";
    }
    header("Location: students.php");
    exit();
}

// Handle bulk approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $bulkStmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id IN ($placeholders) AND role = 'student'");
        if ($bulkStmt->execute($studentIds)) {
            $_SESSION['success_message'] = count($studentIds) . " student(s) approved successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to approve students.";
        }
    }
    header("Location: students.php");
    exit();
}

// Handle bulk reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_reject'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $bulkStmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id IN ($placeholders) AND role = 'student'");
        if ($bulkStmt->execute($studentIds)) {
            $_SESSION['success_message'] = count($studentIds) . " student(s) rejected.";
        } else {
            $_SESSION['error_message'] = "Failed to reject students.";
        }
    }
    header("Location: students.php");
    exit();
}

// Get pending students
$pendingStudents = $conn->query("
    SELECT id, firstname, lastname, email, section, student_id, year, created_at, status
    FROM users 
    WHERE role='student' AND status='pending'
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = count($pendingStudents);

// Get all approved and suspended students with their details
$students = $conn->query("
    SELECT id, firstname, lastname, email, section, student_id, year, created_at, status
    FROM users 
    WHERE role='student' AND status IN ('approved', 'suspended')
    ORDER BY 
        CASE 
            WHEN status = 'approved' THEN 1 
            WHEN status = 'suspended' THEN 2 
        END,
        section ASC, 
        lastname ASC, 
        firstname ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = 0;
$suspendedCount = 0;
foreach($students as $student) {
    if($student['status'] === 'approved') {
        $totalStudents++;
    } elseif($student['status'] === 'suspended') {
        $suspendedCount++;
    }
}

// Get rejected students count
$rejectedCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='rejected'")->fetchColumn();

// Get unique sections (from approved students)
$sections = $conn->query("
    SELECT DISTINCT section 
    FROM users 
    WHERE role='student' AND status='approved' AND section IS NOT NULL AND section != ''
    ORDER BY section ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Get unique years (from approved students)
$years = $conn->query("
    SELECT DISTINCT year 
    FROM users 
    WHERE role='student' AND status='approved' AND year IS NOT NULL AND year != ''
    ORDER BY year ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Get success/error messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Dean Dashboard</title>
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
                            50: '#faf5ff',
                            100: '#f3e8ff',
                            200: '#e9d5ff',
                            300: '#d8b4fe',
                            400: '#c084fc',
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7e22ce',
                            800: '#6b21a8',
                            900: '#581c87',
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

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-scale-in {
            animation: scaleIn 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-down {
            animation: slideDown 0.4s ease-out;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .pulse-badge::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            background: inherit;
            animation: pulse-ring 1.5s ease-out infinite;
        }

        .shadow-custom {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        }

        .shadow-custom-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .stat-icon-1 {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon-2 {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon-3 {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
        }

        .stat-icon-4 {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon-5 {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .table-row-hover {
            transition: background-color 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: #faf5ff;
        }

        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

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
            border-color: #a855f7;
            background: #faf5ff;
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
            border-color: #a855f7;
            background-color: #a855f7;
        }

        .sidebar-toggle-btn.active .toggle-icon::after {
            transform: rotate(135deg);
            right: 4px;
        }

        .sidebar-toggle-btn.active .toggle-icon::before {
            background-color: #a855f7;
        }

        .sidebar-toggle-btn.active {
            border-color: #a855f7;
            background: #faf5ff;
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

        #sidebar-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        #sidebar-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width: 1023px) {
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

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        body {
            overflow-x: hidden;
        }

        #main-content {
            max-width: 100vw;
            overflow-x: hidden;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-container::-webkit-scrollbar {
            height: 6px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
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
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            margin: 1rem;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .pending-card {
            transition: all 0.3s ease;
        }

        .pending-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .custom-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.375rem;
            cursor: pointer;
        }

        .custom-checkbox:checked {
            background-color: #9333ea;
            border-color: #9333ea;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition sidebar-collapsed">
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between px-4 py-5 border-b border-gray-200">
                <div class="flex items-center space-x-3 min-w-0 flex-1">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0 flex-1">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($deanName))) ?></h3>
                        <p class="text-xs text-gray-500">Dean</p>
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
                <a href="professors.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chalkboard-teacher text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Professors</span>
                </a>
                <a href="students.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-user-graduate text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Students</span>
                    <?php if($pendingCount > 0): ?>
                    <span class="nav-text sidebar-transition ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pendingCount ?></span>
                    <?php endif; ?>

            
            
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
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

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300" onclick="closeSidebar()"></div>

    <!-- Edit Student Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="gradient-bg px-6 py-5 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-user-edit mr-3"></i>
                        Edit Student
                    </h2>
                    <button onclick="closeEditModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <form method="POST" action="" class="p-6">
                <input type="hidden" name="update_student" value="1">
                <input type="hidden" name="student_id" id="edit_student_id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-primary-500 mr-2"></i>Student Name
                        </label>
                        <input type="text" id="edit_student_name" readonly 
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-id-card text-primary-500 mr-2"></i>Student ID
                        </label>
                        <input type="text" id="edit_student_id_display" readonly 
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-users text-primary-500 mr-2"></i>Section
                        </label>
                        <input type="text" name="section" id="edit_section" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                               placeholder="Enter section (e.g., A, B, 1A)">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt text-primary-500 mr-2"></i>Year Level
                        </label>
                        <select name="year" id="edit_year" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors shadow-sm">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 w-full transition-all duration-300 lg:ml-20">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-3 sm:px-6 lg:px-8 py-3 sm:py-4">
            <div class="flex items-center justify-between gap-3">
                <button onclick="toggleSidebar()" class="sidebar-toggle-btn lg:hidden" id="mobileHamburgerBtn" aria-label="Toggle sidebar">
                    <div class="toggle-icon"></div>
                </button>
                <div class="flex items-center space-x-3 sm:space-x-4 ml-auto">
                    <div class="text-right">
                        <p class="text-xs text-gray-500 hidden sm:block">Today</p>
                        <p class="text-xs sm:text-sm font-semibold text-gray-900"><?= date('D, M d, Y') ?></p>
                    </div>
                </div>
            </div>
        </header>

        <div class="px-3 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-full">
            <?php if ($successMessage): ?>
            <div class="mb-6 animate-slide-down">
                <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 flex items-start justify-between shadow-sm">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-green-800 font-medium"><?= htmlspecialchars($successMessage) ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div class="mb-6 animate-slide-down">
                <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 flex items-start justify-between shadow-sm">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-red-800 font-medium"><?= htmlspecialchars($errorMessage) ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-6 sm:mb-8 animate-fade-in-up">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Students Management</h1>
                <p class="text-gray-600 text-sm sm:text-base">Manage student registrations and approvals</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-1 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-user-graduate text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Approved</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= $totalStudents ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100 <?= $pendingCount > 0 ? 'ring-2 ring-amber-400' : '' ?>" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-4 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg relative">
                            <i class="fas fa-clock text-xl sm:text-2xl"></i>
                            <?php if($pendingCount > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center text-xs font-bold text-white pulse-badge"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Pending</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold <?= $pendingCount > 0 ? 'text-amber-600' : 'text-gray-900' ?>"><?= $pendingCount ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100 <?= $suspendedCount > 0 ? 'ring-2 ring-red-400' : '' ?>" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-5 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg relative">
                            <i class="fas fa-ban text-xl sm:text-2xl"></i>
                            <?php if($suspendedCount > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center text-xs font-bold text-white pulse-badge"><?= $suspendedCount ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Suspended</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold <?= $suspendedCount > 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= $suspendedCount ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover animate-scale-in border border-gray-100" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="stat-icon-2 w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-users text-xl sm:text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Sections</h3>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl sm:text-4xl font-bold text-gray-900"><?= count($sections) ?></p>
                    </div>
                </div>
                            </div>
            </div>

            <?php if($pendingCount > 0): ?>
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl sm:rounded-2xl shadow-custom p-4 sm:p-6 mb-6 sm:mb-8 border border-amber-200 animate-fade-in-up">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-user-clock text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-gray-900">Pending Approvals</h2>
                            <p class="text-sm text-gray-600"><?= $pendingCount ?> student(s) waiting for approval</p>
                        </div>
                    </div>
                    <form method="POST" id="bulkApprovalForm" class="flex gap-2">
                        <button type="submit" name="bulk_approve" onclick="return confirmBulkAction('approve')" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" 
                                id="bulkApproveBtn" disabled>
                            <i class="fas fa-check-double mr-1"></i> Approve Selected
                        </button>
                        <button type="submit" name="bulk_reject" onclick="return confirmBulkAction('reject')" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" 
                                id="bulkRejectBtn" disabled>
                            <i class="fas fa-times mr-1"></i> Reject Selected
                        </button>
                    </form>
                </div>

                <div class="grid gap-3 sm:gap-4">
                    <?php foreach($pendingStudents as $pending): ?>
                    <div class="pending-card bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="student_ids[]" value="<?= $pending['id'] ?>" form="bulkApprovalForm"
                                   class="custom-checkbox mt-1" onchange="updateBulkButtons()">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div>
                                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($pending['firstname'] . ' ' . $pending['lastname']) ?></h3>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($pending['email']) ?></p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                            <i class="fas fa-id-card mr-1"></i><?= htmlspecialchars($pending['student_id'] ?? 'N/A') ?>
                                        </span>
                                        <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-medium">
                                            <i class="fas fa-layer-group mr-1"></i><?= htmlspecialchars($pending['section'] ?? 'N/A') ?>
                                        </span>
                                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full font-medium">
                                            <i class="fas fa-calendar mr-1"></i>Year <?= htmlspecialchars($pending['year'] ?? 'N/A') ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-3">
                                    <span class="text-xs text-gray-400">
                                        <i class="fas fa-clock mr-1"></i>Registered <?= date('M d, Y h:i A', strtotime($pending['created_at'])) ?>
                                    </span>
                                    <div class="flex gap-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="student_id" value="<?= $pending['id'] ?>">
                                            <button type="submit" name="approve_student" 
                                                    onclick="return confirm('Approve this student?')"
                                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                <i class="fas fa-check mr-1"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="student_id" value="<?= $pending['id'] ?>">
                                            <button type="submit" name="reject_student" 
                                                    onclick="return confirm('Reject this student? They will not be able to access the system.')"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                <i class="fas fa-times mr-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom p-4 sm:p-6 mb-6 sm:mb-8 border border-gray-100 animate-fade-in-up">
                <div class="flex flex-col gap-3 sm:gap-4">
                    <div class="flex-1">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by name, email, or student ID..." 
                            class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                        >
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                        <div class="flex-1 sm:w-48">
                            <select 
                                id="sectionFilter" 
                                class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                            >
                                <option value="">All Sections</option>
                                <?php foreach($sections as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 sm:w-48">
                            <select 
                                id="yearFilter" 
                                class="w-full px-3 sm:px-4 py-2 sm:py-2.5 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                            >
                                <option value="">All Years</option>
                                <?php foreach($years as $year): ?>
                                    <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl sm:rounded-2xl shadow-custom border border-gray-100 animate-fade-in-up overflow-hidden">
                <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900">
                        <i class="fas fa-users text-primary-500 mr-2"></i>All Students
                        <span class="text-sm font-normal text-gray-500 ml-2">(<?= count($students) ?>)</span>
                    </h2>
                    <form method="POST" id="bulkActionForm" class="flex gap-2">
                        <button type="submit" name="bulk_suspend" onclick="return confirmBulkSuspend()" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" 
                                id="bulkSuspendBtn" disabled>
                            <i class="fas fa-ban mr-1"></i> <span class="hidden sm:inline">Suspend</span>
                        </button>
                        <button type="submit" name="bulk_delete" onclick="return confirmBulkDelete()" 
                                class="bg-red-500 hover:bg-red-600 text-white px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" 
                                id="bulkDeleteBtn" disabled>
                            <i class="fas fa-trash-alt mr-1"></i> <span class="hidden sm:inline">Delete</span>
                        </button>
                    </form>
                </div>
                
                <?php if(count($students) > 0): ?>
                <div class="table-container">
                    <table class="min-w-full bg-white" id="studentsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-3 sm:p-4">
                                    <input type="checkbox" id="selectAll" class="custom-checkbox" onchange="toggleSelectAll()">
                                </th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Status</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Student ID</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Name</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider hidden md:table-cell">Email</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Section</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider hidden sm:table-cell">Year</th>
                                <th class="text-left p-3 sm:p-4 font-semibold text-gray-700 text-xs uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($students as $student): ?>
                            <tr class="table-row-hover <?= $student['status'] === 'suspended' ? 'bg-red-50' : '' ?>" data-student="<?= htmlspecialchars(json_encode($student), ENT_QUOTES, 'UTF-8') ?>">
                                <td class="p-3 sm:p-4">
                                    <input type="checkbox" name="student_ids[]" value="<?= $student['id'] ?>" 
                                           form="bulkActionForm" class="custom-checkbox student-checkbox" onchange="updateBulkButtons()">
                                </td>
                                <td class="p-3 sm:p-4">
                                    <?php if($student['status'] === 'suspended'): ?>
                                        <span class="badge bg-red-100 text-red-700 text-xs">
                                            <i class="fas fa-ban mr-1"></i>Suspended
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-green-100 text-green-700 text-xs">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-800 font-medium text-sm sm:text-base">
                                    <?= htmlspecialchars($student['student_id'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-900 font-medium text-sm sm:text-base">
                                    <?= htmlspecialchars($student['firstname'].' '.$student['lastname']) ?>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-600 text-sm sm:text-base hidden md:table-cell">
                                    <a href="mailto:<?= htmlspecialchars($student['email']) ?>" 
                                       class="hover:text-primary-600 hover:underline">
                                       <?= htmlspecialchars($student['email']) ?>
                                    </a>
                                </td>
                                <td class="p-3 sm:p-4">
                                    <span class="badge bg-blue-100 text-blue-700 text-xs">
                                        <?= htmlspecialchars($student['section'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="p-3 sm:p-4 text-gray-700 text-sm sm:text-base hidden sm:table-cell">
                                    <?= htmlspecialchars($student['year'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3 sm:p-4">
    <div class="flex gap-2">
        <button onclick='openEditModal(<?= htmlspecialchars(json_encode($student), ENT_QUOTES, "UTF-8") ?>)'
                class="bg-primary-500 text-white px-3 py-1.5 rounded-lg hover:bg-primary-600 transition text-xs font-medium"
                title="Edit Student">
            <i class="fas fa-edit"></i>
            <span class="hidden lg:inline ml-1">Edit</span>
        </button>
        
        <?php if($student['status'] === 'suspended'): ?>
            <!-- Show Re-approve button for suspended students -->
            <form method="POST" class="inline" onsubmit="return confirm('Re-approve this student account?')">
                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                <button type="submit" name="unsuspend_student"
                        class="bg-green-500 text-white px-3 py-1.5 rounded-lg hover:bg-green-600 transition text-xs font-medium"
                        title="Re-approve Student">
                    <i class="fas fa-check-circle"></i>
                    <span class="hidden lg:inline ml-1">Re-approve</span>
                </button>
            </form>
        <?php else: ?>
            <!-- Show Suspend button for approved students -->
            <form method="POST" class="inline" onsubmit="return confirmSuspend('<?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>')">
                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                <button type="submit" name="suspend_student"
                        class="bg-orange-500 text-white px-3 py-1.5 rounded-lg hover:bg-orange-600 transition text-xs font-medium"
                        title="Suspend Student">
                    <i class="fas fa-ban"></i>
                    <span class="hidden lg:inline ml-1">Suspend</span>
                </button>
            </form>
        <?php endif; ?>
        
        <form method="POST" class="inline" onsubmit="return confirmDelete('<?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>')">
            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
            <button type="submit" name="delete_student"
                    class="bg-red-500 text-white px-3 py-1.5 rounded-lg hover:bg-red-600 transition text-xs font-medium"
                    title="Delete Student">
                <i class="fas fa-trash-alt"></i>
                <span class="hidden lg:inline ml-1">Delete</span>
            </button>
        </form>
    </div>
</td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-8 sm:p-12 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-user-graduate text-5xl sm:text-6xl"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-700 mb-2">No Students</h3>
                    <p class="text-sm sm:text-base text-gray-500">
                        <?php if($pendingCount > 0): ?>
                        There are <?= $pendingCount ?> student(s) waiting for approval above.
                        <?php else: ?>
                        There are currently no students in the system.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
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

    function openEditModal(student) {
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_student_name').value = student.firstname + ' ' + student.lastname;
        document.getElementById('edit_student_id_display').value = student.student_id || 'N/A';
        document.getElementById('edit_section').value = student.section || '';
        document.getElementById('edit_year').value = student.year || '';
        document.getElementById('editModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function confirmDelete(studentName) {
        return confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone and will remove all associated data.`);
    }

    function confirmSuspend(studentName) {
        return confirm(`Are you sure you want to suspend ${studentName}? They will not be able to access their account until reactivated.`);
    }

    function confirmBulkSuspend() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        const count = checkboxes.length;
        
        if (count === 0) {
            alert('Please select at least one student to suspend.');
            return false;
        }
        
        return confirm(`Are you sure you want to suspend ${count} student(s)? They will not be able to access their accounts until reactivated.`);
    }

    function confirmBulkDelete() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        const count = checkboxes.length;
        
        if (count === 0) {
            alert('Please select at least one student to delete.');
            return false;
        }
        
        return confirm(`Are you sure you want to delete ${count} student(s)? This action cannot be undone and will remove all associated data.`);
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateBulkButtons();
    }

    function updateBulkButtons() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const bulkSuspendBtn = document.getElementById('bulkSuspendBtn');
        const selectAll = document.getElementById('selectAll');
        
        if (bulkDeleteBtn && bulkSuspendBtn) {
            const hasSelected = checkboxes.length > 0;
            bulkDeleteBtn.disabled = !hasSelected;
            bulkSuspendBtn.disabled = !hasSelected;
        }
        
        // Update select all checkbox
        const allCheckboxes = document.querySelectorAll('.student-checkbox');
        if (selectAll && allCheckboxes.length > 0) {
            selectAll.checked = checkboxes.length === allCheckboxes.length;
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
            if (sidebarExpanded && window.innerWidth < 1024) {
                closeSidebar();
            }
        }
    });

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    function updateBulkButtons() {
        const checkboxes = document.querySelectorAll('input[name="student_ids[]"]:checked');
        const bulkApproveBtn = document.getElementById('bulkApproveBtn');
        const bulkRejectBtn = document.getElementById('bulkRejectBtn');
        
        if (bulkApproveBtn && bulkRejectBtn) {
            const hasSelected = checkboxes.length > 0;
            bulkApproveBtn.disabled = !hasSelected;
            bulkRejectBtn.disabled = !hasSelected;
        }
    }

    function confirmBulkAction(action) {
        const checkboxes = document.querySelectorAll('input[name="student_ids[]"]:checked');
        const count = checkboxes.length;
        
        if (count === 0) {
            alert('Please select at least one student.');
            return false;
        }
        
        const actionText = action === 'approve' ? 'approve' : 'reject';
        return confirm(`Are you sure you want to ${actionText} ${count} student(s)?`);
    }

    const searchInput = document.getElementById('searchInput');
    const sectionFilter = document.getElementById('sectionFilter');
    const yearFilter = document.getElementById('yearFilter');
    const tableRows = document.querySelectorAll('#studentsTable tbody tr');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedSection = sectionFilter.value.toLowerCase();
        const selectedYear = yearFilter.value.toLowerCase();

        tableRows.forEach(row => {
            const studentData = JSON.parse(row.getAttribute('data-student'));
            const name = (studentData.firstname + ' ' + studentData.lastname).toLowerCase();
            const email = (studentData.email || '').toLowerCase();
            const studentId = (studentData.student_id || '').toLowerCase();
            const section = (studentData.section || '').toLowerCase();
            const year = (studentData.year || '').toLowerCase();

            const matchesSearch = name.includes(searchTerm) || 
                                email.includes(searchTerm) || 
                                studentId.includes(searchTerm);
            const matchesSection = !selectedSection || section === selectedSection;
            const matchesYear = !selectedYear || year === selectedYear;

            if (matchesSearch && matchesSection && matchesYear) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterTable);
    sectionFilter.addEventListener('change', filterTable);
    yearFilter.addEventListener('change', filterTable);

    document.addEventListener('DOMContentLoaded', function() {
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