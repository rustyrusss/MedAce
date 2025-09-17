<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? null;

if (!$quiz_id) {
    header("Location: dashboard_professor.php");
    exit();
}

// Check if the quiz exists and belongs to the professor
$stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: dashboard_professor.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = $_POST['question'];
    $correct_answer = $_POST['correct_answer'];
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'];
    $option_d = $_POST['option_d'];

    // Insert the question into the database
    $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, correct_answer, option_a, option_b, option_c, option_d) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$quiz_id, $question_text, $correct_answer, $option_a, $option_b, $option_c, $option_d]);

    // Redirect to the add questions page after insertion
    header("Location: add_questions.php?quiz_id=$quiz_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Question - <?= htmlspecialchars($quiz['title']) ?></title>
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
    <h1 class="text-2xl font-bold mb-4">Add Questions to <?= htmlspecialchars($quiz['title']) ?></h1>

    <form method="POST" class="space-y-4">
      <div>
        <label for="question" class="block text-sm font-medium">Question</label>
        <textarea name="question" id="question" class="w-full p-2 border rounded-lg" required></textarea>
      </div>

      <div>
        <label for="option_a" class="block text-sm font-medium">Option A</label>
        <input type="text" name="option_a" id="option_a" class="w-full p-2 border rounded-lg" required>
      </div>

      <div>
        <label for="option_b" class="block text-sm font-medium">Option B</label>
        <input type="text" name="option_b" id="option_b" class="w-full p-2 border rounded-lg" required>
      </div>

      <div>
        <label for="option_c" class="block text-sm font-medium">Option C</label>
        <input type="text" name="option_c" id="option_c" class="w-full p-2 border rounded-lg" required>
      </div>

      <div>
        <label for="option_d" class="block text-sm font-medium">Option D</label>
        <input type="text" name="option_d" id="option_d" class="w-full p-2 border rounded-lg" required>
      </div>

      <div>
        <label for="correct_answer" class="block text-sm font-medium">Correct Answer</label>
        <select name="correct_answer" id="correct_answer" class="w-full p-2 border rounded-lg" required>
          <option value="A">Option A</option>
          <option value="B">Option B</option>
          <option value="C">Option C</option>
          <option value="D">Option D</option>
        </select>
      </div>

      <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add Question</button>
    </form>

    <hr class="my-6">

    <h2 class="text-lg font-semibold mb-4">Existing Questions</h2>
    <ul>
      <?php
      // Fetch questions for this quiz
      $stmt = $conn->prepare("SELECT id, question_text FROM questions WHERE quiz_id = ?");
      $stmt->execute([$quiz_id]);
      $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if ($questions):
        foreach ($questions as $question):
      ?>
        <li class="bg-white p-4 rounded-lg shadow mb-4">
          <?= htmlspecialchars($question['question_text']) ?>
        </li>
      <?php endforeach; else: ?>
        <p>No questions added yet.</p>
      <?php endif; ?>
    </ul>
  </main>
</body>
</html>
