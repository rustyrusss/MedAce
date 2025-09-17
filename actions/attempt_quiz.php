<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$quizId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$quizId) {
    echo "Invalid quiz ID.";
    exit();
}

// ✅ Get quiz details
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "Quiz not found.";
    exit();
}

// ✅ Get questions for this quiz
$stmt = $conn->prepare("SELECT id, question_text, options FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Check if the student has already taken the quiz
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE student_id = ? AND quiz_id = ?");
$stmt->execute([$studentId, $quizId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($attempt && $attempt['status'] === 'completed') {
    echo "You have already completed this quiz.";
    exit();
}

// ✅ If the form is submitted, handle the attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = 0;
    $totalQuestions = count($questions);
    
    // Loop through all questions and calculate the score
    foreach ($questions as $question) {
        $questionId = $question['id'];
        $correctAnswer = $question['correct_answer'];
        
        if (isset($_POST['question_' . $questionId]) && $_POST['question_' . $questionId] == $correctAnswer) {
            $score++;
        }
    }

    // ✅ Save the student's quiz attempt
    $stmt = $conn->prepare("INSERT INTO quiz_attempts (student_id, quiz_id, score, attempt_date, status) VALUES (?, ?, ?, NOW(), 'completed')");
    $stmt->execute([$studentId, $quizId, $score]);

    // ✅ Display the results
    echo "You completed the quiz! Your score: $score / $totalQuestions";
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> - Take Quiz</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex flex-col items-center p-8">

    <h1 class="text-2xl font-bold mb-6"><?= htmlspecialchars($quiz['title']) ?></h1>

    <!-- Quiz Form -->
    <form action="attempt_quiz.php?id=<?= $quizId ?>" method="POST" class="w-full max-w-2xl bg-white p-6 rounded-xl shadow">
        
        <?php foreach ($questions as $question): ?>
            <div class="mb-6">
                <p class="font-semibold text-lg mb-2"><?= htmlspecialchars($question['question_text']) ?></p>

                <?php
                // Decode options (assuming options are stored as a JSON array in the database)
                $options = json_decode($question['options'], true);
                if ($options) {
                    foreach ($options as $index => $option) {
                        $optionId = 'question_' . $question['id'] . '_option_' . $index;
                        ?>
                        <label class="block mb-2">
                            <input type="radio" name="question_<?= $question['id'] ?>" value="<?= $option ?>" class="mr-2" required>
                            <?= htmlspecialchars($option) ?>
                        </label>
                        <?php
                    }
                }
                ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="w-full py-3 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition">Submit Quiz</button>
    </form>

</body>
</html>
