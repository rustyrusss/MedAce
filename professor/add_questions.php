<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Only professors can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Check quiz ownership
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("❌ Quiz not found or you don’t have permission.");
}

$message = "";

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = trim($_POST['question_text']);
    $answers = $_POST['answers'];
    $correct = $_POST['correct']; // index of correct answer

    if ($question_text !== "" && !empty($answers)) {
        // Insert question
        $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
        $stmt->execute([$quiz_id, $question_text]);
        $question_id = $conn->lastInsertId();

        // Insert answers
        foreach ($answers as $i => $answer_text) {
            if (trim($answer_text) !== "") {
                $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, $answer_text, ($i == $correct ? 1 : 0)]);
            }
        }

        $message = "✅ Question added successfully!";
    } else {
        $message = "⚠️ Please enter a question and at least one answer.";
    }
}

// Fetch existing questions for display
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

    <?php if ($message): ?>
      <div class="mb-4 p-3 rounded bg-green-50 text-green-700"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Add Question Form -->
    <form method="POST" class="space-y-4 mb-8">
      <div>
        <label class="block text-sm font-medium">Question</label>
        <textarea name="question_text" required class="w-full p-2 border rounded"></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium mb-2">Answers</label>
        <?php for ($i=0; $i<4; $i++): ?>
          <div class="flex items-center mb-2">
            <input type="radio" name="correct" value="<?= $i ?>" class="mr-2" <?= $i==0 ? 'checked' : '' ?>>
            <input type="text" name="answers[]" class="flex-1 p-2 border rounded" placeholder="Answer option <?= $i+1 ?>">
          </div>
        <?php endfor; ?>
      </div>

      <div class="flex justify-between">
        <a href="dashboard_professor.php" class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">⬅ Back</a>
        <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700">Add Question</button>
      </div>
    </form>

    <!-- Existing Questions -->
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
                    <?= htmlspecialchars($a['answer_text']) ?>
                    <?= $a['is_correct'] ? '(Correct)' : '' ?>
                  </li>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-gray-500">No questions added yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
