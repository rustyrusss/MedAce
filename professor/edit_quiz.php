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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Quiz</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-lg p-4">
    <h2 class="text-xl font-bold mb-6">Professor Panel</h2>
    <nav class="space-y-3">
      <a href="../professor/dashboard.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">🏠 Dashboard</a>
      <a href="add_quiz.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">➕ Add Quiz</a>
      <a href="../actions/logout_action.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8">
    <h1 class="text-2xl font-bold mb-4">Edit Quiz: <?= htmlspecialchars($quiz['title']) ?></h1>

    <form id="quizForm" action="edit_quiz.php?id=<?= $quiz['id'] ?>" method="POST" class="space-y-4 bg-white p-6 rounded-lg shadow">
      <!-- Title -->
      <div>
        <label for="title" class="block text-sm font-medium">Title</label>
        <input type="text" name="title" id="title" 
               value="<?= htmlspecialchars($quiz['title']) ?>" 
               class="w-full p-2 border rounded-lg" required>
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="block text-sm font-medium">Description</label>
        <textarea name="description" id="description" class="w-full p-2 border rounded-lg"><?= htmlspecialchars($quiz['description']) ?></textarea>
      </div>

      <!-- Status -->
      <div>
        <label for="status" class="block text-sm font-medium">Status</label>
        <select name="status" id="status" class="w-full p-2 border rounded-lg">
          <option value="active" <?= $quiz['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $quiz['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <!-- Publish Time -->
      <div>
        <label for="publish_time" class="block text-sm font-medium">Publish Time</label>
        <input type="datetime-local" name="publish_time" id="publish_time" 
               value="<?= $quiz['publish_time'] ? date('Y-m-d\TH:i', strtotime($quiz['publish_time'])) : '' ?>"
               class="w-full p-2 border rounded-lg">
      </div>

      <!-- Deadline Time -->
      <div>
        <label for="deadline_time" class="block text-sm font-medium">Deadline Time</label>
        <input type="datetime-local" name="deadline_time" id="deadline_time" 
               value="<?= $quiz['deadline_time'] ? date('Y-m-d\TH:i', strtotime($quiz['deadline_time'])) : '' ?>"
               class="w-full p-2 border rounded-lg">
      </div>

      <!-- Time Limit -->
      <div>
        <label for="time_limit" class="block text-sm font-medium">Time Limit (minutes)</label>
        <input type="number" name="time_limit" id="time_limit" 
               value="<?= htmlspecialchars($quiz['time_limit']) ?>" 
               class="w-full p-2 border rounded-lg" min="0">
      </div>

      <!-- Buttons -->
      <div class="flex justify-between">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">💾 Save Changes</button>
        <a href="../professor/dashboard.php" class="px-4 py-2 bg-gray-300 text-black rounded-lg hover:bg-gray-400">Cancel</a>
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
