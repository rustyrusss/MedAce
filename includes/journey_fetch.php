<?php
require_once __DIR__ . '/../config/db_conn.php';

/**
 * Get student modules + quizzes + progress
 *
 * Rules:
 * - A quiz's displayed score & status are determined by the student's HIGHEST score for that quiz.
 * - total_score is used when available; otherwise we fall back to SUM(points) from the questions table.
 * - Returned quiz fields include: highest_score, total_score, percentage, status ('passed'|'failed'|'pending'), attempted_at
 */

function getStudentJourney($conn, $studentId) {

    // 1) Fetch modules + progress
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.title,
            m.description,
            COALESCE(sp.status, 'pending') AS status
        FROM modules m
        LEFT JOIN student_progress sp
            ON sp.module_id = m.id AND sp.student_id = ?
        WHERE m.status = 'active' OR m.status IS NULL
        ORDER BY m.order_number ASC
    ");
    $stmt->execute([$studentId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Prepare a quiz_total points subquery for fallback when quiz_attempts.total_score IS NULL
    //    This gets the sum of question points per quiz
    //    We'll join this to compute the correct total possible points.
    $stmtQuizTotals = $conn->prepare("
        SELECT quiz_id, COALESCE(SUM(points), 0) AS quiz_total
        FROM questions
        GROUP BY quiz_id
    ");
    $stmtQuizTotals->execute();
    $quizTotalsRaw = $stmtQuizTotals->fetchAll(PDO::FETCH_ASSOC);
    $quizTotals = [];
    foreach ($quizTotalsRaw as $qt) {
        $quizTotals[(int)$qt['quiz_id']] = (float)$qt['quiz_total'];
    }

    // 3) Fetch quizzes with the student's BEST (highest) attempt per quiz
    //    best subquery returns MAX(score), MAX(total_score) and latest attempted_at among the attempts
    $stmt = $conn->prepare("
        SELECT 
            q.id AS quiz_id,
            q.title,
            q.description,
            q.status AS quiz_status,
            -- best attempt info for this student (highest score)
            best.max_score AS highest_score,
            best.max_total_score AS saved_total_score,
            best.last_attempt_at AS attempted_at
        FROM quizzes q
        LEFT JOIN (
            SELECT 
                quiz_id,
                MAX(score) AS max_score,
                MAX(total_score) AS max_total_score,
                MAX(attempted_at) AS last_attempt_at
            FROM quiz_attempts
            WHERE student_id = ?
            GROUP BY quiz_id
        ) best ON best.quiz_id = q.id
        WHERE q.status = 'active' OR q.status IS NULL
        ORDER BY q.publish_time ASC
    ");
    $stmt->execute([$studentId]);
    $quizzesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4) Normalize quiz data and compute final total_score and percentage
    $quizzes = [];
    foreach ($quizzesRaw as $q) {
        $quizId = (int)$q['quiz_id'];

        // Highest score may be NULL if no attempts
        $highestScore = isset($q['highest_score']) ? (float)$q['highest_score'] : null;

        // Prefer saved total_score (from attempts), otherwise fallback to quizTotals[quiz_id] (sum of question points)
        $savedTotal = isset($q['saved_total_score']) && $q['saved_total_score'] !== null
            ? (float)$q['saved_total_score']
            : null;

        $fallbackTotal = isset($quizTotals[$quizId]) ? (float)$quizTotals[$quizId] : 0.0;

        // finalTotal: use savedTotal if > 0 else fallbackTotal (if both zero then null)
        $finalTotal = $savedTotal > 0 ? $savedTotal : ($fallbackTotal > 0 ? $fallbackTotal : null);

        // percentage: compute only when finalTotal is available and > 0
        $percentage = (is_null($highestScore) || is_null($finalTotal) || $finalTotal == 0)
            ? null
            : round(($highestScore / $finalTotal) * 100, 1);

        // status based on highest score (>= 75% = passed)
        if (is_null($highestScore)) {
            $status = 'pending';
        } elseif (!is_null($percentage) && $percentage >= 75.0) {
            $status = 'passed';
        } elseif (!is_null($percentage)) {
            $status = 'failed';
        } else {
            // If we have a highestScore but no finalTotal, fall back to comparing raw score >= 75 points
            $status = ($highestScore !== null && $highestScore >= 75.0) ? 'passed' : 'failed';
        }

        $quizzes[] = [
            'id' => $quizId,
            'title' => $q['title'],
            'description' => $q['description'],
            // expose both raw values for debugging/consumption
            'highest_score' => $highestScore !== null ? $highestScore : null,
            'total_score' => $finalTotal !== null ? $finalTotal : null,
            'percentage' => $percentage, // nullable
            'status' => $status, // 'passed'|'failed'|'pending'
            'attempted_at' => $q['attempted_at'] ?? null,
            'passing_score' => 75
        ];
    }

    // 5) Build steps: modules first, then quizzes
    $stepsModules = array_map(function($m) {
        $status = strtolower($m['status']);
        if ($status === 'completed' || $status === 'done') $status = 'completed';
        elseif ($status === 'in_progress' || $status === 'current') $status = 'current';
        else $status = 'pending';

        return [
            'type' => 'module',
            'id' => $m['id'],
            'title' => $m['title'],
            'description' => $m['description'] ?? '',
            'status' => $status
        ];
    }, $modules);

    $stepsQuizzes = array_map(function($q) {
        // Keep quiz status as returned ('passed','failed','pending'). For UI that expects 'completed', map there.
        $uiStatus = $q['status'] === 'passed' ? 'completed' : $q['status'];

        return [
            'type' => 'quiz',
            'id' => $q['id'],
            'title' => $q['title'],
            'description' => $q['description'] ?? '',
            'status' => $uiStatus, // 'completed'|'failed'|'pending'
            'highest_score' => $q['highest_score'],
            'total_score' => $q['total_score'],
            'percentage' => $q['percentage'],
            'passing_score' => 75,
            'attempted_at' => $q['attempted_at']
        ];
    }, $quizzes);

    $steps = array_merge($stepsModules, $stepsQuizzes);

    // 6) Stats
    $total = count($steps);
    $completedCount = count(array_filter($steps, fn($s) => $s['status'] === 'completed'));
    $current = count(array_filter($steps, fn($s) => $s['status'] === 'current'));
    $pendingCount = count(array_filter($steps, fn($s) => $s['status'] === 'pending'));
    $failedCount = count(array_filter($steps, fn($s) => $s['status'] === 'failed'));

    $progressPercent = $total > 0 ? round(($completedCount / $total) * 100) : 0;

    return [
        'modules' => $modules,
        'quizzes' => $quizzes,
        'steps' => $steps,
        'stats' => [
            'total' => $total,
            'completed' => $completedCount,
            'current' => $current,
            'pending' => $pendingCount,
            'failed' => $failedCount,
            'progress' => $progressPercent
        ]
    ];
}


/**
 * Fetch ALL quiz attempts for scoreboard (attempt-level details).
 * percentage uses COALESCE(attempt.total_score, quiz_total_from_questions)
 */
function getStudentQuizResults($conn, $studentId) {
    // We'll join a derived quiz_total table to get question sums
    $stmt = $conn->prepare("
        SELECT 
            qa.id AS attempt_id,
            qa.quiz_id,
            q.title AS quiz_title,
            qa.score,
            qa.total_score,
            COALESCE(qa.total_score, qt.quiz_total) AS final_total_score,
            CASE 
                WHEN COALESCE(qa.total_score, qt.quiz_total) > 0 
                    THEN ROUND((qa.score / COALESCE(qa.total_score, qt.quiz_total)) * 100, 1)
                ELSE NULL
            END AS percentage,
            qa.correct_answers,
            qa.attempted_at
        FROM quiz_attempts qa
        JOIN quizzes q ON q.id = qa.quiz_id
        LEFT JOIN (
            SELECT quiz_id, COALESCE(SUM(points),0) AS quiz_total
            FROM questions
            GROUP BY quiz_id
        ) qt ON qt.quiz_id = q.id
        WHERE qa.student_id = ?
        ORDER BY qa.attempted_at DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * BEST attempt per quiz (highest score) - returns highest score + attempts count + total possible
 */
function getStudentBestAttempts($conn, $studentId) {
    $stmt = $conn->prepare("
        SELECT 
            q.id AS quiz_id,
            q.title AS quiz_title,
            best.max_score AS highest_score,
            best.attempt_count,
            COALESCE(best.max_total_score, qt.quiz_total) AS total_score,
            CASE 
                WHEN COALESCE(best.max_total_score, qt.quiz_total) > 0 
                    THEN ROUND((best.max_score / COALESCE(best.max_total_score, qt.quiz_total)) * 100, 1)
                ELSE NULL
            END AS percentage,
            CASE WHEN best.max_score IS NULL THEN 'pending'
                 WHEN (best.max_score / NULLIF(COALESCE(best.max_total_score, qt.quiz_total),0)) * 100 >= 75 THEN 'passed'
                 ELSE 'failed'
            END AS result
        FROM quizzes q
        LEFT JOIN (
            SELECT 
                quiz_id,
                COUNT(*) AS attempt_count,
                MAX(score) AS max_score,
                MAX(total_score) AS max_total_score
            FROM quiz_attempts
            WHERE student_id = ?
            GROUP BY quiz_id
        ) best ON best.quiz_id = q.id
        LEFT JOIN (
            SELECT quiz_id, COALESCE(SUM(points),0) AS quiz_total
            FROM questions
            GROUP BY quiz_id
        ) qt ON qt.quiz_id = q.id
        WHERE q.status = 'active' OR q.status IS NULL
        ORDER BY q.publish_time ASC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if student passed a quiz (based on best/highest score)
 */
function hasPassedQuiz($conn, $studentId, $quizId) {
    // fetch max score and the total for that quiz
    $stmt = $conn->prepare("
        SELECT 
            MAX(qa.score) AS max_score,
            MAX(qa.total_score) AS max_total_score,
            qt.quiz_total
        FROM quiz_attempts qa
        LEFT JOIN (
            SELECT quiz_id, COALESCE(SUM(points),0) AS quiz_total
            FROM questions
            WHERE quiz_id = ?
            GROUP BY quiz_id
        ) qt ON qt.quiz_id = qa.quiz_id
        WHERE qa.student_id = ? AND qa.quiz_id = ?
    ");
    $stmt->execute([$quizId, $studentId, $quizId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxScore = isset($r['max_score']) ? (float)$r['max_score'] : null;
    $maxTotal = isset($r['max_total_score']) && $r['max_total_score'] !== null ? (float)$r['max_total_score'] : null;
    $quizTotal = isset($r['quiz_total']) ? (float)$r['quiz_total'] : null;

    $finalTotal = $maxTotal > 0 ? $maxTotal : ($quizTotal > 0 ? $quizTotal : null);

    if (is_null($maxScore)) return false; // no attempts -> not passed
    if (is_null($finalTotal)) {
        // If we don't know total, fall back to raw threshold (score >= 75 points)
        return $maxScore >= 75.0;
    }

    $pct = ($finalTotal > 0) ? ($maxScore / $finalTotal) * 100 : 0;
    return $pct >= 75.0;
}

/**
 * Quiz summary for dashboard analytics (uses best score per quiz)
 */
function getQuizSummary($conn, $studentId) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_quizzes,
            SUM(CASE WHEN best.max_score IS NOT NULL AND (best.max_score / NULLIF(COALESCE(best.max_total_score, qt.quiz_total),0)) * 100 >= 75 THEN 1 ELSE 0 END) AS passed_quizzes,
            SUM(CASE WHEN best.max_score IS NOT NULL AND (best.max_score / NULLIF(COALESCE(best.max_total_score, qt.quiz_total),0)) * 100 < 75 THEN 1 ELSE 0 END) AS failed_quizzes,
            SUM(CASE WHEN best.max_score IS NULL THEN 1 ELSE 0 END) AS pending_quizzes
        FROM quizzes q
        LEFT JOIN (
            SELECT 
                quiz_id,
                MAX(score) AS max_score,
                MAX(total_score) AS max_total_score
            FROM quiz_attempts
            WHERE student_id = ?
            GROUP BY quiz_id
        ) best ON best.quiz_id = q.id
        LEFT JOIN (
            SELECT quiz_id, COALESCE(SUM(points),0) AS quiz_total
            FROM questions
            GROUP BY quiz_id
        ) qt ON qt.quiz_id = q.id
        WHERE q.status = 'active' OR q.status IS NULL
    ");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_quizzes' => (int)($row['total_quizzes'] ?? 0),
        'passed_quizzes' => (int)($row['passed_quizzes'] ?? 0),
        'failed_quizzes' => (int)($row['failed_quizzes'] ?? 0),
        'pending_quizzes' => (int)($row['pending_quizzes'] ?? 0)
    ];
}
