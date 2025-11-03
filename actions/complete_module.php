<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$moduleId = intval($_POST['module_id'] ?? 0);

if ($moduleId > 0) {
    $stmt = $conn->prepare("UPDATE student_progress SET status = 'Completed', completed_at = NOW() WHERE student_id = ? AND module_id = ?");
    $stmt->execute([$studentId, $moduleId]);
}

header("Location: resources.php");
exit();
