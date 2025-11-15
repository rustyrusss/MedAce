<?php
require_once __DIR__ . '/../config/db_conn.php';

function getStudentJourney($conn, $studentId) {
    // 1. Fetch modules + student progress
    $stmt = $conn->prepare("
        SELECT m.id, m.title, m.description, COALESCE(sp.status, 'pending') AS status
        FROM modules m
        LEFT JOIN student_progress sp 
            ON sp.module_id = m.id AND sp.student_id = ?
        ORDER BY m.order_number ASC
    ");
    $stmt->execute([$studentId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch quizzes + latest attempt using attempted_at
    $stmt = $conn->prepare("
        SELECT q.id, q.title, q.description, 
               COALESCE(latest.status, 'pending') AS status
        FROM quizzes q
        LEFT JOIN (
            SELECT qa.*
            FROM quiz_attempts qa
            INNER JOIN (
                SELECT quiz_id, MAX(attempted_at) AS latest_attempt
                FROM quiz_attempts
                WHERE student_id = ?
                GROUP BY quiz_id
            ) last_attempt
            ON qa.quiz_id = last_attempt.quiz_id 
               AND qa.attempted_at = last_attempt.latest_attempt
            WHERE qa.student_id = ?
        ) latest
        ON q.id = latest.quiz_id
        ORDER BY q.publish_time ASC
    ");
    $stmt->execute([$studentId, $studentId]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Merge modules and quizzes for steps
    $steps = array_merge(
        array_map(fn($m) => [
            'type' => 'module',
            'id' => $m['id'],
            'title' => $m['title'],
            'description' => $m['description'] ?? '',
            'status' => strtolower($m['status'])
        ], $modules),
        array_map(fn($q) => [
            'type' => 'quiz',
            'id' => $q['id'],
            'title' => $q['title'],
            'description' => $q['description'] ?? '',
            'status' => strtolower($q['status'])
        ], $quizzes)
    );

    // 4. Stats
    $total = count($steps);
    $completed = count(array_filter($steps, fn($s) => $s['status'] === 'completed'));
    $current = count(array_filter($steps, fn($s) => $s['status'] === 'current'));
    $pending = count(array_filter($steps, fn($s) => $s['status'] === 'pending'));
    $progressPercent = $total > 0 ? round(($completed / $total) * 100) : 0;

    return [
        'modules' => $modules,
        'quizzes' => $quizzes,
        'steps' => $steps,
        'stats' => [
            'total' => $total,
            'completed' => $completed,
            'current' => $current,
            'pending' => $pending,
            'progress' => $progressPercent
        ]
    ];
}
?>
