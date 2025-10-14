<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// Get professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";

// Default avatar
if (!empty($prof['gender'])) {
    if (strtolower($prof['gender']) === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif (strtolower($prof['gender']) === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png";
    }
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}

$profilePic = !empty($prof['profile_pic']) ? "../" . $prof['profile_pic'] : $defaultAvatar;

// Dashboard counts
$totalModules = $conn->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$totalQuizzes = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalStudents = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// Get lists
$modules = $conn->query("SELECT id, title, created_at FROM modules ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$quizzes = $conn->query("SELECT id, title, publish_time, deadline_time FROM quizzes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Professor Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    html { scroll-behavior: smooth; }
    .transition-all * { transition: all 0.2s ease-in-out; }
  </style>
</head>
<body class="transition-all min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 text-gray-800">

<!-- Sidebar -->
<aside class="fixed inset-y-0 left-0 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col z-30 transition-all duration-300 w-64">
    
    <!-- Profile -->
    <div class="flex items-center mb-10 space-x-4">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
             class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
        <div class="flex flex-col overflow-hidden">
            <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></p>
            <p class="text-sm text-gray-500">Professor</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
        <div>
            <p class="text-xs uppercase text-gray-400 font-semibold mb-2">Main</p>
            <a href="dashboard.php" class="flex items-center p-2 rounded-lg bg-teal-100 font-medium">
                <span class="text-xl">🏠</span>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="manage_modules.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">📘</span>
                <span class="ml-3">Modules</span>
            </a>
            <a href="manage_quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">📝</span>
                <span class="ml-3">Quizzes</span>
            </a>
            <a href="student_progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">👨‍🎓</span>
                <span class="ml-3">Student Progress</span>
            </a>
        </div>
    </nav>

    <!-- Logout -->
    <div class="mt-auto">
        <a href="../actions/logout_action.php"
           class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
            <span class="text-xl">🚪</span>
            <span class="ml-3 font-medium">Logout</span>
        </a>
    </div>
</aside>

<!-- Main content -->
<div class="md:ml-64 p-6 space-y-10 transition-all">

    <h1 class="text-3xl font-bold text-gray-800">
      Welcome, <?= htmlspecialchars(ucwords(strtolower($profName))) ?> 👋
    </h1>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white/80 p-6 rounded-2xl shadow-lg border border-gray-200">
            <h2 class="text-sm text-gray-500">📘 Total Modules</h2>
            <p class="text-3xl font-bold text-teal-600"><?= $totalModules ?></p>
        </div>
        <div class="bg-white/80 p-6 rounded-2xl shadow-lg border border-gray-200">
            <h2 class="text-sm text-gray-500">📝 Total Quizzes</h2>
            <p class="text-3xl font-bold text-blue-600"><?= $totalQuizzes ?></p>
        </div>
        <div class="bg-white/80 p-6 rounded-2xl shadow-lg border border-gray-200">
            <h2 class="text-sm text-gray-500">👨‍🎓 Total Students</h2>
            <p class="text-3xl font-bold text-indigo-600"><?= $totalStudents ?></p>
        </div>
    </div>

    <!-- Modules -->
    <div class="bg-white/80 p-6 rounded-2xl shadow-lg border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">📘 Recent Modules</h2>
            <a href="manage_modules.php" class="text-teal-600 hover:underline font-medium">Go to Modules →</a>
        </div>
        <?php if (empty($modules)): ?>
            <p class="text-gray-500">No modules created yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-gray-700">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="p-2">Title</th>
                            <th class="p-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $m): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="p-2"><?= htmlspecialchars($m['title']) ?></td>
                            <td class="p-2"><?= date("F j, Y", strtotime($m['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quizzes -->
    <div class="bg-white/80 p-6 rounded-2xl shadow-lg border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">📝 Recent Quizzes</h2>
            <a href="manage_quizzes.php" class="text-blue-600 hover:underline font-medium">Go to Quizzes →</a>
        </div>
        <?php if (empty($quizzes)): ?>
            <p class="text-gray-500">No quizzes available yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-gray-700">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="p-2">Title</th>
                            <th class="p-2">Publish</th>
                            <th class="p-2">Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $q): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="p-2"><?= htmlspecialchars($q['title']) ?></td>
                            <td class="p-2"><?= $q['publish_time'] ? date("M j, Y g:i A", strtotime($q['publish_time'])) : '-' ?></td>
                            <td class="p-2"><?= $q['deadline_time'] ? date("M j, Y g:i A", strtotime($q['deadline_time'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
