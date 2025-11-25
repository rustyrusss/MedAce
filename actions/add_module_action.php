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
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // FIX: Get raw status and normalize it properly
    $statusRaw = isset($_POST['status']) ? $_POST['status'] : 'draft';
    $status = strtolower(trim($statusRaw));
    
    // Debug logging
    error_log("=== ADD MODULE DEBUG ===");
    error_log("Professor ID: " . $professorId);
    error_log("Title: " . $title);
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
    if (empty($title)) {
        $_SESSION['error'] = "Module title is required.";
        header("Location: ../professor/manage_modules.php");
        exit();
    }
    
    // Handle file upload
    $filePath = null;
    $fileUploaded = false;
    $destination = null;
    
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
            error_log("File uploaded successfully: " . $filePath);
        } else {
            $_SESSION['error'] = "Failed to upload file. Please try again.";
            header("Location: ../professor/manage_modules.php");
            exit();
        }
    }
    
    try {
        // Get the highest display_order value
        $stmt = $conn->prepare("SELECT MAX(display_order) as max_order FROM modules WHERE professor_id = ?");
        $stmt->execute([$professorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $displayOrder = ($result['max_order'] ?? -1) + 1;
        
        // FIX: Explicit INSERT with parameter logging
        $sql = "INSERT INTO modules (professor_id, title, description, content, status, display_order, created_at) 
                VALUES (:professor_id, :title, :description, :content, :status, :display_order, NOW())";
        
        $params = [
            'professor_id' => $professorId,
            'title' => $title,
            'description' => $description,
            'content' => $filePath,
            'status' => $status,
            'display_order' => $displayOrder
        ];
        
        error_log("SQL: " . $sql);
        error_log("Parameters: " . print_r($params, true));
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($params);
        
        $newModuleId = $conn->lastInsertId();
        error_log("INSERT executed. New module ID: " . $newModuleId);
        
        // FIX: Verify the insert actually worked with correct status
        $verifyStmt = $conn->prepare("SELECT status FROM modules WHERE id = ?");
        $verifyStmt->execute([$newModuleId]);
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Verified status in DB: '" . $verifyResult['status'] . "'");
        error_log("Expected status: '" . $status . "'");
        
        if ($verifyResult['status'] !== $status) {
            error_log("WARNING: Status mismatch! Expected '" . $status . "' but got '" . $verifyResult['status'] . "'");
            error_log("This indicates a database constraint or default value issue!");
        }
        
        error_log("=== END DEBUG ===");
        
        // Set success message with file upload notification
        if ($fileUploaded) {
            $_SESSION['file_uploaded'] = true;
            $_SESSION['success'] = "Module created successfully with file uploaded!";
        } else {
            $_SESSION['success'] = "Module created successfully (no file uploaded).";
        }
        
        header("Location: ../professor/manage_modules.php");
        exit();
        
    } catch (PDOException $e) {
        // If database insert fails, delete uploaded file
        if ($fileUploaded && $destination && file_exists($destination)) {
            unlink($destination);
            error_log("Cleaned up uploaded file due to database error");
        }
        
        error_log("Database error: " . $e->getMessage());
        error_log("Error code: " . $e->getCode());
        $_SESSION['error'] = "Failed to create module: " . $e->getMessage();
        header("Location: ../professor/manage_modules.php");
        exit();
    }
    
} else {
    // If not POST request, redirect back
    header("Location: ../professor/manage_modules.php");
    exit();
}
?>