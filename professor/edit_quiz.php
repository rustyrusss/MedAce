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
    header("Location: dashboard_professor.php");
    exit();
}

// Fetch the quiz details for editing
$stmt = $conn->prepare("SELECT id, title, description, status FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: dashboard_professor.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];

    // Update the quiz details
    $stmt = $conn->prepare("UPDATE quizzes SET title = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([$title, $description, $status, $quiz_id]);

    header("Location: dashboard_professor.php");
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
      <a href="dashboard_professor.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">🏠 Dashboard</a>
      <a href="add_quiz.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">➕ Add Quiz</a>
      <a href="../actions/logout_action.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8">
    <h1 class="text-2xl font-bold mb-4">Edit Quiz: <?= htmlspecialchars($quiz['title']) ?></h1>

    <form action="edit_quiz.php?id=<?= $quiz['id'] ?>" method="POST" class="space-y-4">
      <div>
        <label for="title" class="block text-sm font-medium">Title</label>
        <input type="text" name="title" id="title" value="<?= htmlspecialchars($quiz['title']) ?>" class="w-full p-2 border rounded-lg" required>
      </div>

      <div>
        <label for="description" class="block text-sm font-medium">Description</label>
        <textarea name="description" id="description" class="w-full p-2 border rounded-lg"><?= htmlspecialchars($quiz['description']) ?></textarea>
      </div>

      <div>
        <label for="status" class="block text-sm font-medium">Status</label>
        <select name="status" id="status" class="w-full p-2 border rounded-lg">
          <option value="active" <?= $quiz['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $quiz['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <div class="flex justify-between">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Changes</button>
        <a href="dashboard_professor.php" class="px-4 py-2 bg-gray-300 text-black rounded-lg hover:bg-gray-400">Cancel</a>
      </div>
    </form>
  </main>
</body>
</html>
