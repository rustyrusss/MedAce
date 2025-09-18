<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("❌ Quiz not found or you don’t have permission.");
}

$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Questions</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 p-6">
  <div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow-lg">
    <h1 class="text-2xl font-bold mb-4">Manage Questions for Quiz: <?= htmlspecialchars($quiz['title']) ?></h1>

    <div class="mb-6">
      <a href="../professor/add_question.php?quiz_id=<?= $quiz_id ?>" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700">➕ Add Question</a>
      <a href="../professor/dashboard.php" class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">⬅ Back</a>
    </div>

    <h2 class="text-xl font-semibold mb-2">Existing Questions</h2>
    <?php if (!empty($questions)): ?>
      <ul class="space-y-4">
        <?php foreach ($questions as $q): ?>
          <li class="p-4 border rounded-lg bg-gray-50">
            <p class="font-medium"><?= htmlspecialchars($q['question_text']) ?></p>
            <ul class="list-disc pl-6 mt-2 text-gray-700">
              <?php
                $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ?");
                $stmt->execute([$q['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($answers as $a): ?>
                  <li class="<?= $a['is_correct'] ? 'text-green-600 font-semibold' : '' ?>">
                    <?= htmlspecialchars($a['answer_text']) ?> <?= $a['is_correct'] ? '(Correct)' : '' ?>
                  </li>
              <?php endforeach; ?>
            </ul>
            <div class="mt-3 flex gap-3">
              <a href="edit_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">✏️ Edit</a>
              <a href="delete_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>" onclick="return confirm('Are you sure you want to delete this question?')" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">🗑 Delete</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-gray-500">No questions added yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
