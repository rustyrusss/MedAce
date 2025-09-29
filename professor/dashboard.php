<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$professor_name = $_SESSION['firstname'] . " " . $_SESSION['lastname'];

// === Fetch Quizzes for this professor ===
$stmt = $conn->prepare("
    SELECT id, title, created_at, publish_time, deadline_time, status 
    FROM quizzes 
    WHERE professor_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$professor_id]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Fetch Stats ===
// Total students
$studentsCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

// Total quiz attempts for this professor's quizzes
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE q.professor_id = ?
");
$stmt->execute([$professor_id]);
$attemptsCount = $stmt->fetchColumn();

// Daily tip
$tips = [
    "Encourage students to ask questions during lectures.",
    "Provide feedback that is specific, constructive, and timely.",
    "Balance theory with practical applications.",
    "Use active learning techniques to increase engagement.",
    "Stay updated with new teaching tools and methods."
];
$dailyTip = $tips[array_rand($tips)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Professor Dashboard</title>
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
    <h1 class="text-2xl font-bold mb-4">Welcome, <?= htmlspecialchars($professor_name) ?> 👨‍🏫</h1>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
      <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold">📊 Students Enrolled</h2>
        <p class="text-2xl font-bold text-blue-600"><?= $studentsCount ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold">📝 Quiz Attempts</h2>
        <p class="text-2xl font-bold text-green-600"><?= $attemptsCount ?></p>
      </div>
    </div>

    <!-- Quizzes -->
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">Your Quizzes</h2>
        <a href="add_quiz.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ Add Quiz</a>
      </div>
      <table class="w-full border border-gray-200 rounded-lg">
        <thead>
          <tr class="bg-gray-100 text-left">
            <th class="p-3">Title</th>
            <th class="p-3">Created At</th>
            <th class="p-3">Publish Time</th>
            <th class="p-3">Deadline</th>
            <th class="p-3">Status</th>
            <th class="p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($quizzes)): ?>
            <?php foreach ($quizzes as $quiz): ?>
              <tr class="border-t hover:bg-gray-50">
                <td class="p-3"><?= htmlspecialchars($quiz['title']) ?></td>
                <td class="p-3">
                  <?= $quiz['created_at'] 
                        ? date("F j, Y - g:i A", strtotime($quiz['created_at'])) 
                        : '<span class="text-gray-500">N/A</span>' ?>
                </td>
                <td class="p-3">
                  <?= $quiz['publish_time'] 
                        ? date("F j, Y - g:i A", strtotime($quiz['publish_time'])) 
                        : '<span class="text-gray-500">Not set</span>' ?>
                </td>
                <td class="p-3">
                  <?php if ($quiz['deadline_time']): ?>
                    <?php if (strtotime($quiz['deadline_time']) < time()): ?>
                      <span class="text-red-600 font-semibold">
                        <?= date("F j, Y - g:i A", strtotime($quiz['deadline_time'])) ?> (Expired)
                      </span>
                    <?php else: ?>
                      <?= date("F j, Y - g:i A", strtotime($quiz['deadline_time'])) ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-gray-500">No deadline</span>
                  <?php endif; ?>
                </td>
                <td class="p-3">
                  <span class="px-2 py-1 rounded-lg text-sm 
                    <?= $quiz['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= ucfirst($quiz['status']) ?>
                  </span>
                </td>
                <td class="p-3 space-x-2">
                  <a href="edit_quiz.php?id=<?= $quiz['id'] ?>" 
                     class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200">
                     Edit
                  </a>
                  <a href="../actions/delete_quiz.php?id=<?= $quiz['id'] ?>" 
                    onclick="return confirm('Are you sure you want to delete this quiz?')"
                     class="px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">
                       Delete
                  </a>
                  <a href="add_questions.php?quiz_id=<?= $quiz['id'] ?>" 
                     class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                     Questions
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-gray-500 py-4">No quizzes created yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Daily Teaching Tip -->
    <div class="bg-blue-50 p-6 rounded-xl shadow">
      <h2 class="text-lg font-semibold mb-2">💡 Teaching Tip of the Day</h2>
      <p class="text-gray-700"><?= $dailyTip ?></p>
    </div>
  </main>
</body>
</html>
