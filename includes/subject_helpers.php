<?php
/**
 * Subject Helper Functions
 * Handles all subject-related database operations
 */

/**
 * Get all active subjects
 */
function getAllSubjects($conn) {
    try {
        $stmt = $conn->query("
            SELECT id, name, description, color, icon, display_order 
            FROM subjects 
            WHERE status = 'active' 
            ORDER BY display_order ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student progress grouped by subject
 */
function getStudentProgressBySubject($conn, $studentId) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                s.id AS subject_id,
                s.name AS subject_name,
                s.description AS subject_description,
                s.color AS subject_color,
                s.icon AS subject_icon,
                s.display_order,
                
                -- Module stats
                COUNT(DISTINCT m.id) AS total_modules,
                SUM(CASE WHEN LOWER(COALESCE(sp.status, '')) = 'completed' THEN 1 ELSE 0 END) AS completed_modules,
                SUM(CASE WHEN LOWER(COALESCE(sp.status, '')) = 'in progress' THEN 1 ELSE 0 END) AS in_progress_modules,
                
                -- Quiz stats
                COUNT(DISTINCT q.id) AS total_quizzes,
                (SELECT COUNT(DISTINCT qa.quiz_id) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q2 ON qa.quiz_id = q2.id 
                 WHERE qa.student_id = ? 
                 AND q2.subject_id = s.id 
                 AND LOWER(qa.status) = 'passed') AS passed_quizzes,
                (SELECT COUNT(DISTINCT qa.quiz_id) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q2 ON qa.quiz_id = q2.id 
                 WHERE qa.student_id = ? 
                 AND q2.subject_id = s.id 
                 AND LOWER(qa.status) = 'failed') AS failed_quizzes,
                (SELECT AVG(qa.score) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q2 ON qa.quiz_id = q2.id 
                 WHERE qa.student_id = ? 
                 AND q2.subject_id = s.id) AS avg_quiz_score
                
            FROM subjects s
            LEFT JOIN modules m ON m.subject_id = s.id AND m.status = 'active'
            LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
            LEFT JOIN quizzes q ON q.subject_id = s.id AND q.status = 'active'
            WHERE s.status = 'active'
            GROUP BY s.id, s.name, s.description, s.color, s.icon, s.display_order
            ORDER BY s.display_order ASC
        ");
        
        $stmt->execute([$studentId, $studentId, $studentId, $studentId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate progress percentage for each subject
        foreach ($results as &$subject) {
            $totalItems = (int)$subject['total_modules'] + (int)$subject['total_quizzes'];
            $completedItems = (int)$subject['completed_modules'] + (int)$subject['passed_quizzes'];
            
            $subject['total_items'] = $totalItems;
            $subject['completed_items'] = $completedItems;
            $subject['progress_percent'] = $totalItems > 0 
                ? round(($completedItems / $totalItems) * 100) 
                : 0;
            $subject['avg_quiz_score'] = round((float)($subject['avg_quiz_score'] ?? 0), 1);
        }
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("Error fetching subject progress: " . $e->getMessage());
        return [];
    }
}

/**
 * Get modules for a specific subject
 */
function getModulesBySubject($conn, $subjectId, $studentId = null) {
    try {
        $sql = "
            SELECT 
                m.id, m.title, m.description, m.display_order,
                " . ($studentId ? "COALESCE(sp.status, 'Pending') AS status" : "'Pending' AS status") . "
            FROM modules m
            " . ($studentId ? "LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?" : "") . "
            WHERE m.subject_id = ? AND m.status = 'active'
            ORDER BY m.display_order ASC, m.created_at ASC
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($studentId) {
            $stmt->execute([$studentId, $subjectId]);
        } else {
            $stmt->execute([$subjectId]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching modules by subject: " . $e->getMessage());
        return [];
    }
}

/**
 * Get quizzes for a specific subject
 */
function getQuizzesBySubject($conn, $subjectId, $studentId = null) {
    try {
        $sql = "
            SELECT 
                q.id, q.title, q.description, q.time_limit, q.passing_score,
                " . ($studentId ? "
                    (SELECT qa.status 
                     FROM quiz_attempts qa 
                     WHERE qa.quiz_id = q.id 
                     AND qa.student_id = ? 
                     ORDER BY qa.attempted_at DESC 
                     LIMIT 1) AS status,
                    (SELECT qa.score 
                     FROM quiz_attempts qa 
                     WHERE qa.quiz_id = q.id 
                     AND qa.student_id = ? 
                     ORDER BY qa.attempted_at DESC 
                     LIMIT 1) AS latest_score
                " : "'Pending' AS status, NULL AS latest_score") . "
            FROM quizzes q
            WHERE q.subject_id = ? AND q.status = 'active'
            ORDER BY q.publish_time DESC
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($studentId) {
            $stmt->execute([$studentId, $studentId, $subjectId]);
        } else {
            $stmt->execute([$subjectId]);
        }
        
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set default status if null
        foreach ($quizzes as &$quiz) {
            if (empty($quiz['status'])) {
                $quiz['status'] = 'Pending';
            }
        }
        
        return $quizzes;
        
    } catch (PDOException $e) {
        error_log("Error fetching quizzes by subject: " . $e->getMessage());
        return [];
    }
}

/**
 * Get subject details by ID
 */
function getSubjectById($conn, $subjectId) {
    try {
        $stmt = $conn->prepare("
            SELECT id, name, description, color, icon, display_order 
            FROM subjects 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$subjectId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subject: " . $e->getMessage());
        return null;
    }
}

/**
 * Get comprehensive subject statistics for a student
 */
function getSubjectStatistics($conn, $studentId, $subjectId) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                s.id, s.name, s.color, s.icon,
                
                -- Module stats
                COUNT(DISTINCT m.id) AS total_modules,
                SUM(CASE WHEN LOWER(COALESCE(sp.status, '')) = 'completed' THEN 1 ELSE 0 END) AS completed_modules,
                SUM(CASE WHEN LOWER(COALESCE(sp.status, '')) = 'in progress' THEN 1 ELSE 0 END) AS in_progress_modules,
                SUM(CASE WHEN LOWER(COALESCE(sp.status, '')) = 'pending' OR sp.status IS NULL THEN 1 ELSE 0 END) AS pending_modules,
                
                -- Quiz attempt stats
                (SELECT COUNT(*) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = ? AND q.subject_id = s.id) AS total_attempts,
                (SELECT COUNT(DISTINCT qa.quiz_id) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = ? AND q.subject_id = s.id AND LOWER(qa.status) = 'passed') AS passed_quizzes,
                (SELECT COUNT(DISTINCT qa.quiz_id) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = ? AND q.subject_id = s.id AND LOWER(qa.status) = 'failed') AS failed_quizzes,
                (SELECT AVG(qa.score) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = ? AND q.subject_id = s.id) AS avg_score,
                (SELECT MAX(qa.score) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = ? AND q.subject_id = s.id) AS highest_score,
                
                -- Time spent (if tracking)
                (SELECT SUM(TIMESTAMPDIFF(SECOND, qa.attempted_at, qa.submitted_at)) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 WHERE qa.student_id = ? AND q.subject_id = s.id) AS total_time_seconds
                
            FROM subjects s
            LEFT JOIN modules m ON m.subject_id = s.id AND m.status = 'active'
            LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
            WHERE s.id = ? AND s.status = 'active'
            GROUP BY s.id, s.name, s.color, s.icon
        ");
        
        $stmt->execute([
            $studentId, $studentId, $studentId, $studentId, $studentId, 
            $studentId, $studentId, $subjectId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Calculate derived metrics
            $totalItems = (int)$result['total_modules'];
            $completedItems = (int)$result['completed_modules'];
            
            $result['progress_percent'] = $totalItems > 0 
                ? round(($completedItems / $totalItems) * 100) 
                : 0;
            $result['avg_score'] = round((float)($result['avg_score'] ?? 0), 1);
            $result['highest_score'] = round((float)($result['highest_score'] ?? 0), 1);
            
            // Format time spent
            $seconds = (int)($result['total_time_seconds'] ?? 0);
            $result['time_spent_formatted'] = formatTimeSpent($seconds);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error fetching subject statistics: " . $e->getMessage());
        return null;
    }
}

/**
 * Helper function to format time in seconds to readable format
 */
function formatTimeSpent($seconds) {
    if ($seconds < 60) {
        return $seconds . "s";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . "m";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . "h " . $minutes . "m";
    }
}

/**
 * Get weak subjects (subjects with low performance)
 */
function getWeakSubjects($conn, $studentId, $threshold = 60) {
    try {
        $allSubjects = getStudentProgressBySubject($conn, $studentId);
        
        $weakSubjects = array_filter($allSubjects, function($subject) use ($threshold) {
            return $subject['progress_percent'] < $threshold && $subject['total_items'] > 0;
        });
        
        // Sort by progress percentage (lowest first)
        usort($weakSubjects, function($a, $b) {
            return $a['progress_percent'] - $b['progress_percent'];
        });
        
        return array_values($weakSubjects);
        
    } catch (Exception $e) {
        error_log("Error getting weak subjects: " . $e->getMessage());
        return [];
    }
}

/**
 * Get strong subjects (subjects with high performance)
 */
function getStrongSubjects($conn, $studentId, $threshold = 80) {
    try {
        $allSubjects = getStudentProgressBySubject($conn, $studentId);
        
        $strongSubjects = array_filter($allSubjects, function($subject) use ($threshold) {
            return $subject['progress_percent'] >= $threshold && $subject['total_items'] > 0;
        });
        
        // Sort by progress percentage (highest first)
        usort($strongSubjects, function($a, $b) {
            return $b['progress_percent'] - $a['progress_percent'];
        });
        
        return array_values($strongSubjects);
        
    } catch (Exception $e) {
        error_log("Error getting strong subjects: " . $e->getMessage());
        return [];
    }
}

/**
 * Update module's subject
 */
function updateModuleSubject($conn, $moduleId, $subjectId) {
    try {
        $stmt = $conn->prepare("UPDATE modules SET subject_id = ? WHERE id = ?");
        return $stmt->execute([$subjectId, $moduleId]);
    } catch (PDOException $e) {
        error_log("Error updating module subject: " . $e->getMessage());
        return false;
    }
}

/**
 * Update quiz's subject
 */
function updateQuizSubject($conn, $quizId, $subjectId) {
    try {
        $stmt = $conn->prepare("UPDATE quizzes SET subject_id = ? WHERE id = ?");
        return $stmt->execute([$subjectId, $quizId]);
    } catch (PDOException $e) {
        error_log("Error updating quiz subject: " . $e->getMessage());
        return false;
    }
}

/**
 * Get subject color palette
 */
function getSubjectColorPalette() {
    return [
        'red' => '#ef4444',
        'orange' => '#f59e0b',
        'amber' => '#f59e0b',
        'yellow' => '#eab308',
        'lime' => '#84cc16',
        'green' => '#10b981',
        'emerald' => '#10b981',
        'teal' => '#14b8a6',
        'cyan' => '#06b6d4',
        'sky' => '#0ea5e9',
        'blue' => '#3b82f6',
        'indigo' => '#6366f1',
        'violet' => '#8b5cf6',
        'purple' => '#a855f7',
        'fuchsia' => '#d946ef',
        'pink' => '#ec4899',
        'rose' => '#f43f5e',
    ];
}

/**
 * Get FontAwesome icons for subjects
 */
function getSubjectIcons() {
    return [
        'fa-heart', 'fa-brain', 'fa-pills', 'fa-hospital', 'fa-baby',
        'fa-user-nurse', 'fa-stethoscope', 'fa-syringe', 'fa-book-medical',
        'fa-dna', 'fa-microscope', 'fa-heartbeat', 'fa-lungs', 'fa-bone',
        'fa-tooth', 'fa-eye', 'fa-hand-holding-heart', 'fa-users',
        'fa-user-md', 'fa-ambulance', 'fa-first-aid'
    ];
}