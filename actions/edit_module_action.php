<?php
session_start();
require_once __DIR__ . '/../config/db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moduleId = $_POST['module_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $professorId = $_SESSION['user_id'];
    
    // Handle file upload if new file provided
    if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] === 0) {
        // Upload logic here
        $uploadDir = '../uploads/modules/';
        // ... your upload code
    }
    
    // Update module
    $stmt = $conn->prepare("UPDATE modules SET title = ?, description = ?, status = ? WHERE id = ? AND professor_id = ?");
    $stmt->execute([$title, $description, $status, $moduleId, $professorId]);
    
    $_SESSION['success'] = "Module updated successfully!";
    header("Location: ../professor/manage_modules.php");
    exit();
}
?>