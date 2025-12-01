<?php
session_start();
require_once '../config/db_conn.php';

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
    .success { background-color: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; border-radius: 4px; }
    .error { background-color: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; border-radius: 4px; }
    .info { background-color: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; border-radius: 4px; }
    button { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
    button:hover { background: #218838; }
    .back-link { display: inline-block; margin-top: 20px; color: #007bff; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
</style>";

echo "<h1>🔧 Prerequisite Fix Tool</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    try {
        // Fix the status values
        $stmt = $conn->prepare("UPDATE student_progress SET status = 'completed' WHERE status = 'Completed'");
        $stmt->execute();
        $rowsAffected = $stmt->rowCount();
        
        echo "<div class='success'>";
        echo "<strong>✓ Success!</strong><br>";
        echo "Updated $rowsAffected record(s) from 'Completed' to 'completed'.<br><br>";
        echo "Your quizzes should now be unlocked! 🎉";
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<strong>Next Steps:</strong><br>";
        echo "1. Go to your quizzes page<br>";
        echo "2. The previously locked quizzes should now be accessible<br>";
        echo "3. If still locked, clear your browser cache or use incognito mode";
        echo "</div>";
        
        echo "<a href='quizzes.php' class='back-link'>→ Go to Quizzes</a>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>";
        echo "<strong>❌ Error:</strong><br>";
        echo "Could not update database: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
} else {
    // Show the fix button
    echo "<div class='info'>";
    echo "<strong>What does this do?</strong><br>";
    echo "This tool will fix the case sensitivity issue in your module completion status.<br><br>";
    echo "It will change all 'Completed' (capital C) to 'completed' (lowercase c) so that quizzes with prerequisites can unlock properly.";
    echo "</div>";
    
    // Check if there's anything to fix
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_progress WHERE status = 'Completed'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "<div class='error'>";
        echo "<strong>⚠ Issue Detected:</strong><br>";
        echo "Found " . $result['count'] . " record(s) with incorrect case.<br>";
        echo "Click the button below to fix this automatically.";
        echo "</div>";
        
        echo "<form method='POST'>";
        echo "<button type='submit' name='fix' value='1'>🔧 Fix Status Values Now</button>";
        echo "</form>";
    } else {
        echo "<div class='success'>";
        echo "<strong>✓ No issues found!</strong><br>";
        echo "All your status values are already correct (lowercase 'completed').<br><br>";
        echo "If quizzes are still locked, try:<br>";
        echo "1. Clearing your browser cache<br>";
        echo "2. Using incognito/private browsing mode<br>";
        echo "3. Re-completing the prerequisite module";
        echo "</div>";
    }
    
    echo "<a href='diagnostic_prerequisite.php' class='back-link'>→ View Diagnostic Report</a><br>";
    echo "<a href='quizzes.php' class='back-link'>→ Go to Quizzes</a>";
}
?>