<?php
/**
 * Mark Module as Completed
 * Allows marking when a student finishes viewing/reading a module
 */

session_start();
require_once '../config/db_conn.php';
require_once 'module_completion_helper.php';

// This should be called when student finishes a module
// Can be triggered by:
// 1. Clicking "Mark as Complete" button
// 2. Viewing all pages of a module
// 3. Spending X minutes on module
// 4. Passing a module quiz

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }
    
    $studentId = $_SESSION['user_id'];
    $moduleId = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    
    if ($moduleId === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid module ID']);
        exit();
    }
    
    if ($_POST['action'] === 'mark_complete') {
        // Mark module as 100% complete
        $success = markModuleCompleted($conn, $studentId, $moduleId);
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'Module marked as completed!',
                'status' => 'completed',
                'percentage' => 100
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating completion status']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'update_progress') {
        // Update module progress percentage
        $percentage = isset($_POST['percentage']) ? intval($_POST['percentage']) : 0;
        $success = updateModuleProgress($conn, $studentId, $moduleId, $percentage);
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'Progress updated!',
                'percentage' => $percentage
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating progress']);
        }
        exit();
    }
}

// GET request - show completion status
if (isset($_GET['module_id']) && isset($_SESSION['user_id'])) {
    $studentId = $_SESSION['user_id'];
    $moduleId = intval($_GET['module_id']);
    
    $status = getModuleCompletionStatus($conn, $studentId, $moduleId);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'status' => $status['status'],
        'percentage' => $status['completion_percentage'],
        'completed_at' => $status['completed_at']
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);