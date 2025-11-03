<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$quiz_id = $_GET['id'] ?? null;

if (!$quiz_id) {
    header("Location: ../professor/dashboard.php");
    exit();
}

// Fetch the quiz details
$stmt = $conn->prepare("
    SELECT id, title, description, status, publish_time, deadline_time, time_limit 
    FROM quizzes 
    WHERE id = ? AND professor_id = ?
");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: ../professor/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = $_POST['title'];
    $description  = $_POST['description'];
    $status       = $_POST['status'];
    $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
    $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;
    $time_limit   = (int)$_POST['time_limit'];

    // Update quiz
    $stmt = $conn->prepare("
        UPDATE quizzes 
        SET title = ?, description = ?, status = ?, publish_time = ?, deadline_time = ?, time_limit = ? 
        WHERE id = ? AND professor_id = ?
    ");
    $stmt->execute([$title, $description, $status, $publish_time, $deadline_time, $time_limit, $quiz_id, $professor_id]);

    header("Location: ../professor/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: false }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Quiz</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    html { scroll-behavior: smooth; }
    .transition-all * { transition: all 0.2s ease-in-out; }
  </style>
</head>
<body class="transition-all min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 text-gray-800">

<!-- Sidebar -->
<aside class="fixed inset-y-0 left-0 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col z-30 transition-all duration-300"
       :class="{'w-64': !collapsed, 'w-20': collapsed, '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768}">
    
    <!-- Profile -->
    <div class="flex items-center mb-10 transition-all" :class="collapsed ? 'justify-center' : 'space-x-4'">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
             class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
        <div x-show="!collapsed" class="flex flex-col overflow-hidden">
            <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></p>
            <p class="text-sm text-gray-500">Professor</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
        <div>
            <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
            <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">🏠</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Dashboard</span>
            </a>
            <a href="manage_modules.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">📘</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Modules</span>
            </a>
            <a href="manage_quizzes.php" class="flex items-center p-2 rounded-lg bg-teal-100 transition">
                <span class="text-xl">📝</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Quizzes</span>
            </a>
            <a href="student_progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
                <span class="text-xl">👨‍🎓</span>
                <span x-show="!collapsed" class="ml-3 font-medium">Student Progress</span>
            </a>
        </div>
    </nav>

    <!-- Logout -->
    <div class="mt-auto">
        <a href="../actions/logout_action.php"
           class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
            <span class="text-xl">🚪</span>
            <span x-show="!collapsed" class="ml-3 font-medium">Logout</span>
        </a>
    </div>
</aside>

<!-- Main content -->
<div class="md:ml-64 p-6 space-y-10 transition-all">
    <h1 class="text-3xl font-semibold text-gray-800 mb-8">Edit Quiz: <?= htmlspecialchars($quiz['title']) ?></h1>

    <form id="quizForm" action="edit_quiz.php?id=<?= $quiz['id'] ?>" method="POST" class="space-y-6 bg-white p-8 rounded-lg shadow-lg max-w-3xl mx-auto">
      <!-- Title -->
      <div class="flex flex-col">
        <label for="title" class="text-lg font-semibold text-gray-700 mb-2">Title</label>
        <input type="text" name="title" id="title" value="<?= htmlspecialchars($quiz['title']) ?>" class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500" required>
      </div>

      <!-- Description -->
      <div class="flex flex-col">
        <label for="description" class="text-lg font-semibold text-gray-700 mb-2">Description</label>
        <textarea name="description" id="description" class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500"><?= htmlspecialchars($quiz['description']) ?></textarea>
      </div>

      <!-- Status -->
      <div class="flex flex-col">
        <label for="status" class="text-lg font-semibold text-gray-700 mb-2">Status</label>
        <select name="status" id="status" class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
          <option value="active" <?= $quiz['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $quiz['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <!-- Publish Time -->
      <div class="flex flex-col">
        <label for="publish_time" class="text-lg font-semibold text-gray-700 mb-2">Publish Time</label>
        <input type="datetime-local" name="publish_time" id="publish_time" value="<?= $quiz['publish_time'] ? date('Y-m-d\TH:i', strtotime($quiz['publish_time'])) : '' ?>" class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
      </div>

      <!-- Deadline Time -->
      <div class="flex flex-col">
        <label for="deadline_time" class="text-lg font-semibold text-gray-700 mb-2">Deadline Time</label>
        <input type="datetime-local" name="deadline_time" id="deadline_time" value="<?= $quiz['deadline_time'] ? date('Y-m-d\TH:i', strtotime($quiz['deadline_time'])) : '' ?>" class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
      </div>

      <!-- Time Limit -->
      <div class="flex flex-col">
        <label for="time_limit" class="text-lg font-semibold text-gray-700 mb-2">Time Limit (minutes)</label>
        <input type="number" name="time_limit" id="time_limit" value="<?= htmlspecialchars($quiz['time_limit']) ?>" class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500" min="0">
      </div>

      <!-- Buttons -->
      <div class="flex justify-between mt-6">
        <button type="submit" class="px-6 py-3 bg-teal-600 text-white rounded-lg shadow-md hover:bg-teal-700 focus:outline-none">💾 Save Changes</button>
        <a href="../professor/dashboard.php" class="px-6 py-3 bg-gray-300 text-black rounded-lg shadow-md hover:bg-gray-400 focus:outline-none">Cancel</a>
      </div>
    </form>
  </main>

  <!-- Validation Script -->
  <script>
    document.getElementById("quizForm").addEventListener("submit", function(event) {
      const publish = document.getElementById("publish_time").value;
      const deadline = document.getElementById("deadline_time").value;

      if (publish && deadline && new Date(deadline) < new Date(publish)) {
        alert("⚠️ Deadline cannot be earlier than the publish time.");
        event.preventDefault();
      }
    });
  </script>
</body>
</html>
