<?php
session_start();
require_once __DIR__ . '/../config/db_conn.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moduleId = $_POST['module_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // FIX: Get raw status and normalize it properly
    $statusRaw = isset($_POST['status']) ? $_POST['status'] : 'draft';
    $status = strtolower(trim($statusRaw));
    
    // Debug logging
    error_log("=== EDIT MODULE DEBUG ===");
    error_log("Module ID: " . $moduleId);
    error_log("Raw POST status: '" . print_r($_POST['status'], true) . "'");
    error_log("Status after trim/lowercase: '" . $status . "'");
    error_log("Status length: " . strlen($status));
    error_log("Status bytes: " . bin2hex($status));
    
    // FIX: Strict validation with no default fallback unless truly invalid
    $validStatuses = ['draft', 'published', 'archived'];
    
    if (!in_array($status, $validStatuses, true)) {
        error_log("ERROR: Invalid status '" . $status . "' not in valid array");
        $_SESSION['error'] = "Invalid status value. Please select Draft, Published, or Archived.";
        header("Location: ../professor/manage_modules.php");
        exit();
    }
    
    error_log("Status validated successfully: '" . $status . "'");
    
    // Validate required fields
    if (empty($moduleId) || empty($title)) {
        $_SESSION['error'] = "Module ID and title are required.";
        header("Location: ../professor/manage_modules.php");
        exit();
    }
    
    // Verify module belongs to professor
    $stmt = $conn->prepare("SELECT id, content FROM modules WHERE id = ? AND professor_id = ?");
    $stmt->execute([$moduleId, $professorId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        $_SESSION['error'] = "Module not found or you don't have permission to edit it.";
        header("Location: ../professor/manage_modules.php");
        exit();
    }
    
    // Handle file upload (optional - only if new file is uploaded)
    $filePath = $module['content']; // Keep existing file path by default
    $fileUploaded = false;
    $oldFilePath = $module['content'];
    
    if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['module_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed file types
        $allowed = ['pdf', 'ppt', 'pptx'];
        
        if (!in_array($fileExt, $allowed)) {
            $_SESSION['error'] = "Invalid file type. Only PDF, PPT, and PPTX files are allowed.";
            header("Location: ../professor/manage_modules.php");
            exit();
        }
        
        // Check file size (50MB max)
        if ($fileSize > 50 * 1024 * 1024) {
            $_SESSION['error'] = "File size exceeds 50MB limit.";
            header("Location: ../professor/manage_modules.php");
            exit();
        }
        
        // Create unique filename
        $newFileName = uniqid('module_', true) . '.' . $fileExt;
        
        // Set upload directory
        $uploadDir = __DIR__ . '/../uploads/modules/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $destination = $uploadDir . $newFileName;
        
        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $destination)) {
            $filePath = '../uploads/modules/' . $newFileName;
            $fileUploaded = true;
            
            // Delete old file if it exists
            if ($oldFilePath && file_exists(__DIR__ . '/' . $oldFilePath)) {
                unlink(__DIR__ . '/' . $oldFilePath);
            }
        } else {
            $_SESSION['error'] = "Failed to upload file. Please try again.";
            header("Location: ../professor/manage_modules.php");
            exit();
        }
    }
    
    try {
        // FIX: Explicit UPDATE with parameter logging
        $sql = "UPDATE modules 
                SET title = :title, 
                    description = :description, 
                    content = :content, 
                    status = :status
                WHERE id = :id AND professor_id = :professor_id";
        
        $params = [
            'title' => $title,
            'description' => $description,
            'content' => $filePath,
            'status' => $status,
            'id' => $moduleId,
            'professor_id' => $professorId
        ];
        
        error_log("SQL: " . $sql);
        error_log("Parameters: " . print_r($params, true));
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($params);
        
        error_log("UPDATE executed. Rows affected: " . $stmt->rowCount());
        
        // FIX: Verify the update actually worked
        $verifyStmt = $conn->prepare("SELECT status FROM modules WHERE id = ?");
        $verifyStmt->execute([$moduleId]);
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Verified status in DB: '" . $verifyResult['status'] . "'");
        error_log("=== END DEBUG ===");
        
        // Set success message with file upload notification
        if ($fileUploaded) {
            $_SESSION['file_uploaded'] = true;
            $_SESSION['success'] = "Module updated successfully with new file uploaded!";
        } else {
            $_SESSION['success'] = "Module updated successfully.";
        }
        
        header("Location: ../professor/manage_modules.php");
        exit();
        
    } catch (PDOException $e) {
        // If database update fails and new file was uploaded, delete it
        if ($fileUploaded && file_exists($destination)) {
            unlink($destination);
        }
        
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update module: " . $e->getMessage();
        header("Location: ../professor/manage_modules.php");
        exit();
    }
    
} else {
    // If not POST request, redirect back
    header("Location: ../professor/manage_modules.php");
    exit();
}
?>