<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// ✅ Get current profile picture
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentPic = $stmt->fetchColumn();

// ✅ Remove old file (if it exists and is not a default avatar)
$uploadDir = "../uploads/profile_pics/";
if (!empty($currentPic) && file_exists($uploadDir . $currentPic)) {
    unlink($uploadDir . $currentPic);
}

// ✅ Reset profile picture column in DB
$stmt = $conn->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
$stmt->execute([$userId]);

// ✅ Redirect back to profile page
header("Location: ../member/profile_edit.php");
exit();
