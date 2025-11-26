<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_conn.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'professor'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    
    // Validation
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit();
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'New password must be at least 8 characters long'
        ]);
        exit();
    }
    
    // Get current user data
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect'
        ]);
        exit();
    }
    
    // Check if new password is same as current
    if (password_verify($newPassword, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'New password must be different from current password'
        ]);
        exit();
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashedPassword, $userId]);
    
    // Log the password change (optional - create a password_changes table if needed)
    try {
        $logStmt = $conn->prepare("
            INSERT INTO password_changes (user_id, changed_at, ip_address) 
            VALUES (?, NOW(), ?)
        ");
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $logStmt->execute([$userId, $ipAddress]);
    } catch (PDOException $e) {
        // Table might not exist, continue anyway
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>