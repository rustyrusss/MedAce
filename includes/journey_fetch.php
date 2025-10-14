<?php
require_once __DIR__ . '/../config/db_conn.php';  // or adjust path as needed

function getStudentJourney($conn, $studentId) {
    // 1. Fetch modules + student progress
    $stmt = $conn->prepare("
        SELECT m.id, m.title, m.description, COALESCE(sp.status, 'pending') AS status
        FROM modules m
        LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
        ORDER BY m.order_number ASC
    ");
    $stmt->execute([$studentId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch quizzes + attempts
    $stmt = $conn->prepare("
        SELECT q.id, q.title, q.description, COALESCE(qa.status, 'pending') AS status
        FROM quizzes q
        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ?
        ORDER BY q.publish_time ASC
    ");
    $stmt->execute([$studentId]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Compute stats
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
