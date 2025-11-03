<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Only professors can submit
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $content      = trim($_POST['content']);
    $lesson_id    = intval($_POST['lesson_id']);
    $status       = $_POST['status'] ?? 'active';
    $professorId  = $_SESSION['user_id'];

    // ✅ Publish + Deadline Times
    $publish_time  = !empty($_POST['publish_time']) ? $_POST['publish_time'] : null;
    $deadline_time = !empty($_POST['deadline_time']) ? $_POST['deadline_time'] : null;

    // ✅ Validation
    if (empty($title) || empty($lesson_id)) {
        $_SESSION['message'] = "Title and Lesson are required.";
        header("Location: ../professor/add_quiz.php");
        exit();
    }

    // ✅ Ensure content is always valid JSON
    if ($content === '') {
        $jsonContent = json_encode(['instructions' => 'Instructions will be provided in class.']);
    } elseif (json_decode($content) === null) {
        $jsonContent = json_encode(['instructions' => $content]);
    } else {
        $jsonContent = $content; // already valid JSON
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO quizzes (title, description, content, professor_id, lesson_id, status, publish_time, deadline_time, created_at)
            VALUES (:title, :description, :content, :professor_id, :lesson_id, :status, :publish_time, :deadline_time, NOW())
        ");
        $stmt->execute([
            ':title'        => $title,
            ':description'  => $description,
            ':content'      => $jsonContent,
            ':professor_id' => $professorId,
            ':lesson_id'    => $lesson_id,
            ':status'       => $status,
            ':publish_time' => $publish_time,
            ':deadline_time'=> $deadline_time
        ]);

        $_SESSION['message'] = "Quiz added successfully!";
        header("Location: ../professor/dashboard.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        header("Location: ../professor/add_quiz.php");
        exit();
    }
}
