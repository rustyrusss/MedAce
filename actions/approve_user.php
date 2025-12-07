<?php
// File: actions/approve_user.php
// Called when dean approves a pending user account

session_start();
require_once '../config/db_conn.php';
require_once '../config/email_config.php';

// Check if user is dean (add your own authorization logic)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dean') {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: ../admin/dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    
    try {
        // Get user details before approval
        $stmt = $conn->prepare("
            SELECT firstname, lastname, email, username, role 
            FROM users 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $_SESSION['error'] = "User not found or already approved.";
            header("Location: ../admin/dashboard.php");
            exit;
        }
        
        // Update user status to approved
        $updateStmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $updateStmt->execute([$userId]);
        
        // Send approval email
        $emailSubject = "MedAce - Account Approved!";
        $emailBody = getApprovalEmailHTML(
            $user['firstname'], 
            $user['lastname'], 
            $user['username']
        );
        
        $emailSent = sendEmail($user['email'], $emailSubject, $emailBody);
        
        // Set success message
        $successMessage = "User {$user['username']} has been approved successfully.";
        if (!$emailSent) {
            $successMessage .= " (Email notification could not be sent.)";
        }
        
        $_SESSION['success'] = $successMessage;
        
    } catch (PDOException $e) {
        error_log("Approval error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to approve user. Please try again.";
    }
    
} else {
    $_SESSION['error'] = "Invalid request.";
}

header("Location: ../admin/dashboard.php");
exit;
?>
