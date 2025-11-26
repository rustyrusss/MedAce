<?php
session_start();
require_once '../config/db_conn.php';

// Only dean can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dean') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professorId = intval($_POST['professor_id']);
    $action      = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'professor'");
        $stmt->execute([$professorId]);
        $_SESSION['success'] = "Professor account approved successfully.";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'professor'");
        $stmt->execute([$professorId]);
        $_SESSION['success'] = "Professor account rejected successfully.";
    } else {
        $_SESSION['error'] = "Invalid action.";
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
}

// Redirect back to dean dashboard inside admin folder
header("Location: dashboard.php");
exit();
