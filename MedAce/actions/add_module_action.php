<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];
$title = $_POST['title'];
$description = $_POST['description'];
$filePath = "";

// Handle file upload
if ($_FILES['module_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['module_file'];
    $allowedTypes = ['pdf', 'ppt', 'pptx'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($fileType, $allowedTypes)) {
        $uploadDir = "../uploads/modules/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($file['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $filePath = "uploads/modules/" . $fileName;
        } else {
            die("File upload failed.");
        }
    } else {
        die("Invalid file type. Only PDF, PPT, and PPTX are allowed.");
    }
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO modules (professor_id, title, description, content, created_at, status) VALUES (?, ?, ?, ?, NOW(), 'active')");
$stmt->execute([$professorId, $title, $description, $filePath]);

header("Location: ../professor/manage_modules.php");
exit();
