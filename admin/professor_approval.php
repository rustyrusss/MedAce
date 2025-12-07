<?php
session_start();
require_once '../config/db_conn.php';
require_once '../config/email_config.php'; // <-- Added for sending email

// Only dean can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dean') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professorId = intval($_POST['professor_id']);
    $action      = $_POST['action'];

    // Fetch professor info (needed for sending email)
    $stmt = $conn->prepare("SELECT firstname, lastname, email, username FROM users WHERE id = ? AND role = 'professor'");
    $stmt->execute([$professorId]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        $_SESSION['error'] = "Professor not found.";
        header("Location: dashboard.php");
        exit();
    }

    if ($action === 'approve') {

        // Update status
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'professor'");
        $stmt->execute([$professorId]);

        // Build approval email
        $subject = "MedAce - Professor Account Approved!";
        $body = getApprovalEmailHTML(
            $prof['firstname'],
            $prof['lastname'],
            $prof['username']
        );

        // Try sending email
        $emailSent = sendEmail($prof['email'], $subject, $body);

        // Success message
        $_SESSION['success'] = "Professor approved successfully."
            . (!$emailSent ? " (Email not sent)" : "");

    } elseif ($action === 'reject') {

        // Update status
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'professor'");
        $stmt->execute([$professorId]);

        $_SESSION['success'] = "Professor account rejected successfully.";

    } else {
        $_SESSION['error'] = "Invalid action.";
    }

} else {
    $_SESSION['error'] = "Invalid request method.";
}

// Redirect back to dean dashboard
header("Location: dashboard.php");
exit();
?>
