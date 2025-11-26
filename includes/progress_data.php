<?php
require_once '../config/db_conn.php';

function getProgressBreakdown($conn, $studentId) {
    // Helper function: safely get table count
    function safeCount($conn, $table) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM $table");
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Table not found — return 0 safely
            return 0;
        }
    }

    // Helper: safely get completed count
    function safeCompleted($conn, $query, $studentId) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute([$studentId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // Count total items
    $totalModules = safeCount($conn, 'modules');
    $totalQuizzes = safeCount($conn, 'quizzes');
    $totalAssignments = safeCount($conn, 'assignments');
    $totalReports = safeCount($conn, 'reports');

    // Completed items
    $completedModules = safeCompleted($conn, "SELECT COUNT(*) FROM student_progress WHERE student_id = ? AND status = 'Completed'", $studentId);
    $completedQuizzes = safeCompleted($conn, "SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ? AND status = 'Completed'", $studentId);
    $completedAssignments = safeCompleted($conn, "SELECT COUNT(*) FROM student_assignments WHERE student_id = ? AND status = 'Submitted'", $studentId);
    $completedReports = safeCompleted($conn, "SELECT COUNT(*) FROM student_reports WHERE student_id = ? AND status = 'Submitted'", $studentId);

    // Compute percentages safely
    $moduleProgress = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;
    $quizProgress = $totalQuizzes > 0 ? round(($completedQuizzes / $totalQuizzes) * 100) : 0;
    $assignmentProgress = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100) : 0;
    $reportProgress = $totalReports > 0 ? round(($completedReports / $totalReports) * 100) : 0;

    return [
        'modules' => $moduleProgress,
        'quizzes' => $quizProgress,
        'assignments' => $assignmentProgress,
        'reports' => $reportProgress
    ];
}
?>
