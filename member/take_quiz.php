<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$quizId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$quizId) {
    echo "Invalid quiz ID.";
    exit();
}

// Fetch quiz info
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) {
    echo "Quiz not found.";
    exit();
}

// Fetch questions + answers
$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🎲 SHUFFLE QUESTIONS
shuffle($questions);

// Shuffle answers
$answers = [];
foreach ($questions as $q) {
    if (in_array($q['question_type'], ['multiple_choice', 'true_false'])) {
        $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ?");
        $stmt->execute([$q['id']]);
        $questionAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($questionAnswers);
        $answers[$q['id']] = $questionAnswers;
    }
}

// Determine attempt number
$stmt = $conn->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quizId, $studentId]);
$attemptNumber = $stmt->fetchColumn() + 1;

// Timer setup
$timeLimit = !empty($quiz['time_limit']) ? intval($quiz['time_limit']) * 60 : 0;
$remaining = 0;

if ($timeLimit > 0) {
    $sessionKey = 'quiz_start_'.$quizId;
    $endSessionKey = 'quiz_end_'.$quizId;
    
    if (isset($_SESSION[$sessionKey]) && isset($_SESSION[$endSessionKey])) {
        $endTime = $_SESSION[$endSessionKey];
        $remaining = max(0, $endTime - time());
        
        if ($remaining <= 0) {
            unset($_SESSION[$sessionKey]);
            unset($_SESSION[$endSessionKey]);
            $remaining = 0;
        }
    } else {
        $remaining = $timeLimit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<title><?= htmlspecialchars($quiz['title']) ?> | MedAce</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* {
  -webkit-tap-highlight-color: transparent;
}

body { 
  font-family: 'Inter', sans-serif; 
  background-color: #f3f4f6; 
  color: #111827; 
  margin: 0;
  padding: 0;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.accent { background-color: #3b82f6; transition: all 0.2s ease; }
.accent:hover { background-color: #2563eb; }
.question-card { transition: all 0.3s ease; border: 1px solid #e5e7eb; }
.question-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
.radio-option input:checked + span { color: #2563eb; font-weight: 600; }
.progress-bg { background-color: #e5e7eb; }
::selection { background-color: #dbeafe; color: #111827; }
.question-nav button { transition: all 0.2s ease; }
.question-nav button.answered { background-color: #3b82f6; color: #fff; }
.question-nav button:hover { background-color: #2563eb; color: #fff; }

.text-answer-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: all 0.2s ease;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  resize: vertical;
}

.text-answer-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.text-answer-input::placeholder { color: #9ca3af; }

.text-answer-input.essay { min-height: 300px; line-height: 1.6; }
.text-answer-input.short-answer { min-height: 120px; }

.char-counter {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 0.5rem;
  text-align: right;
}

.char-counter.warning { color: #f59e0b; }
.char-counter.error { color: #ef4444; }

.question-type-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.badge-multiple-choice { background-color: #dbeafe; color: #1e40af; }
.badge-short-answer { background-color: #fef3c7; color: #92400e; }
.badge-essay { background-color: #f3e8ff; color: #6b21a8; }
.badge-true-false { background-color: #dcfce7; color: #166534; }

.points-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color: #78350f;
  margin-left: 0.5rem;
  box-shadow: 0 2px 4px rgba(251, 191, 36, 0.3);
}

.warning-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  padding: 1rem;
  text-align: center;
  font-weight: 600;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from { transform: translateY(-100%); }
  to { transform: translateY(0); }
}

.warning-icon {
  display: inline-block;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

body.warning-active { padding-top: 60px; }

.no-copy {
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
  -webkit-touch-callout: none;
}

.text-answer-input {
  -webkit-user-select: text !important;
  -moz-user-select: text !important;
  -ms-user-select: text !important;
  user-select: text !important;
}

.fullscreen-modal {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background-color: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(8px);
}

.modal-content {
  background: white;
  border-radius: 1.5rem;
  padding: 2rem;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.quiz-content {
  filter: blur(5px);
  pointer-events: none;
  user-select: none;
}

.quiz-content.active {
  filter: none;
  pointer-events: auto;
  user-select: auto;
}

aside .overflow-y-auto::-webkit-scrollbar { width: 6px; }
aside .overflow-y-auto::-webkit-scrollbar-track { background: transparent; }
aside .overflow-y-auto::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
aside .overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* DESKTOP LAYOUT (1025px+) */
@media (min-width: 1025px) {
  .quiz-container {
    display: flex;
    min-height: 100vh;
  }
  
  .quiz-sidebar {
    position: fixed;
    right: 0;
    top: 0;
    width: 20rem;
    height: 100vh;
    background: white;
    box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
    z-index: 40;
  }
  
  .quiz-main {
    flex: 1;
    margin-right: 20rem;
    padding: 3rem 2.5rem;
    overflow-y: auto;
  }
}

/* MOBILE/TABLET LAYOUT (≤1024px) */
@media (max-width: 1024px) {
  body.warning-active {
    padding-top: 55px;
  }
  
  .warning-banner {
    font-size: 0.75rem;
    padding: 0.625rem 0.75rem;
    line-height: 1.4;
  }
  
  .quiz-container {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  
  /* Fixed header */
  .quiz-sidebar {
    position: fixed;
    top: 55px;
    left: 0;
    right: 0;
    width: 100%;
    background: white;
    border-bottom: 2px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    z-index: 40;
    padding: 1rem 1.25rem !important;
  }
  
  .quiz-sidebar > div {
    padding: 0 !important;
  }
  
  /* Header layout */
  .quiz-sidebar .space-y-6 {
    display: flex !important;
    flex-direction: row !important;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem !important;
    margin: 0 !important;
  }
  
  .quiz-sidebar .space-y-6 > div {
    margin: 0 !important;
  }
  
  /* Quiz title */
  .quiz-sidebar > div > div:first-child > div:first-child {
    flex: 1;
    order: 1;
    min-width: 0;
  }
  
  .quiz-sidebar h1 {
    font-size: 1.125rem !important;
    font-weight: 700 !important;
    margin-bottom: 0.375rem !important;
    line-height: 1.4 !important;
    color: #111827;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
  
  .quiz-sidebar .text-sm {
    font-size: 0.75rem !important;
    color: #6b7280;
    display: block;
    line-height: 1.4;
  }
  
  .quiz-sidebar .text-sm:last-child {
    display: none;
  }
  
  /* Timer */
  .quiz-sidebar > div > div:first-child > div:nth-child(2) {
    order: 3;
    flex-shrink: 0;
  }
  
  .quiz-sidebar .p-4 {
    padding: 0.5rem 0.75rem !important;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    background: #fef3c7;
    border: 1.5px solid #fbbf24;
    border-radius: 0.625rem;
  }
  
  .quiz-sidebar .p-4 p {
    display: none;
  }
  
  #timer {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: #92400e;
  }
  
  .quiz-sidebar .p-4::before {
    content: '⏱';
    font-size: 1.125rem;
  }
  
  /* Progress */
  .quiz-sidebar > div > div:first-child > div:nth-child(3) {
    order: 2;
    width: 100%;
    margin-top: 0.5rem !important;
  }
  
  .quiz-sidebar > div > div:first-child > div:nth-child(3) > p:first-child {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6366f1;
    margin-bottom: 0.5rem;
  }
  
  .quiz-sidebar .progress-bg {
    height: 5px !important;
    margin-bottom: 0 !important;
    background-color: #e0e7ff;
    border-radius: 9999px;
  }
  
  .quiz-sidebar #progressBar {
    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 9999px;
  }
  
  .quiz-sidebar > div > div:first-child > div:nth-child(3) > p:last-child {
    display: none;
  }
  
  .quiz-sidebar .space-y-6 {
    flex-wrap: wrap;
  }
  
  /* Hide question navigator */
  .question-nav {
    display: none !important;
  }
  
  /* Hide sidebar submit button */
  .quiz-sidebar > div > div:last-child {
    display: none !important;
  }
  
  /* Main content area */
  .quiz-main {
    flex: 1;
    margin-top: 125px;
    padding: 1.25rem;
    padding-bottom: 2rem;
    overflow-y: auto;
    background: #f9fafb;
  }
  
  .quiz-main .max-w-3xl {
    max-width: 100%;
  }
  
  .quiz-main .space-y-8 {
    gap: 1.25rem !important;
  }
  
  /* Submit button at bottom */
  #submitBtn {
    width: 100%;
    padding: 1.125rem !important;
    font-size: 1.0625rem !important;
    font-weight: 700 !important;
    margin-top: 1.75rem !important;
    background: #111827 !important;
    color: white !important;
    border-radius: 0.875rem !important;
    position: relative;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(17, 24, 39, 0.15);
  }
  
  #submitBtn:hover:not(:disabled) {
    background: #1f2937 !important;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.25);
    transform: translateY(-1px);
  }
  
  #submitBtn:active:not(:disabled) {
    transform: translateY(0);
  }
  
  #submitBtn:disabled {
    background: #9ca3af !important;
    opacity: 0.6;
    box-shadow: none;
  }
  
  #submitBtn::after {
    content: ' →';
    margin-left: 0.5rem;
    font-size: 1.25rem;
  }
  
  /* Question cards */
  .question-card {
    padding: 1.5rem !important;
    background: white;
    border-radius: 1.125rem !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
  }
  
  .question-card p {
    font-size: 1.0625rem !important;
    line-height: 1.65 !important;
    font-weight: 500;
    color: #111827;
  }
  
  /* Answer grid */
  .answer-grid {
    grid-template-columns: 1fr !important;
    gap: 0.75rem !important;
  }
  
  .question-type-badge,
  .points-badge {
    font-size: 0.75rem !important;
    padding: 0.3rem 0.75rem !important;
  }
  
  /* Radio options with better touch targets */
  .radio-option {
    padding: 1rem 1.125rem !important;
    font-size: 0.9375rem !important;
    border: 2px solid #e5e7eb !important;
    border-radius: 0.875rem !important;
    min-height: 52px;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  
  .radio-option:active {
    transform: scale(0.98);
  }
  
  .radio-option:has(input:checked) {
    border-color: #6366f1 !important;
    background-color: #eef2ff !important;
  }
  
  .radio-option input {
    width: 1.25rem !important;
    height: 1.25rem !important;
    flex-shrink: 0;
    cursor: pointer;
  }
  
  .radio-option span {
    flex: 1;
  }
  
  /* Text inputs */
  .text-answer-input {
    font-size: 1rem !important;
    padding: 1rem !important;
    border: 2px solid #e5e7eb !important;
    border-radius: 0.875rem !important;
    line-height: 1.6;
  }
  
  .text-answer-input:focus {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
  }
  
  .text-answer-input.essay {
    min-height: 220px !important;
  }
  
  .text-answer-input.short-answer {
    min-height: 130px !important;
  }
  
  .char-counter {
    font-size: 0.8125rem !important;
    margin-top: 0.5rem !important;
  }
}

/* SMALL MOBILE (≤640px) */
@media (max-width: 640px) {
  body.warning-active {
    padding-top: 48px;
  }
  
  .warning-banner {
    font-size: 0.6875rem;
    padding: 0.5rem 0.625rem;
  }
  
  .quiz-sidebar {
    top: 48px;
    padding: 0.875rem 1rem !important;
  }
  
  .quiz-sidebar h1 {
    font-size: 1rem !important;
    margin-bottom: 0.25rem !important;
  }
  
  .quiz-sidebar .text-sm {
    font-size: 0.6875rem !important;
  }
  
  #timer {
    font-size: 0.9375rem !important;
  }
  
  .quiz-sidebar .p-4 {
    padding: 0.4375rem 0.625rem !important;
  }
  
  .quiz-sidebar .p-4::before {
    font-size: 1rem;
  }
  
  .quiz-sidebar > div > div:first-child > div:nth-child(3) > p:first-child {
    font-size: 0.6875rem !important;
    margin-bottom: 0.375rem;
  }
  
  .quiz-sidebar .progress-bg {
    height: 4px !important;
  }
  
  .quiz-main {
    margin-top: 110px;
    padding: 1rem;
    padding-bottom: 1.5rem;
  }
  
  .quiz-main .space-y-8 {
    gap: 1rem !important;
  }
  
  .question-card {
    padding: 1.125rem !important;
    border-radius: 1rem !important;
  }
  
  .question-card p {
    font-size: 0.9375rem !important;
    line-height: 1.55 !important;
  }
  
  .question-card .mb-3 {
    margin-bottom: 0.625rem !important;
  }
  
  .question-card .mb-4 {
    margin-bottom: 0.75rem !important;
  }
  
  .radio-option {
    padding: 0.875rem 1rem !important;
    font-size: 0.875rem !important;
    min-height: 48px;
  }
  
  .radio-option input {
    width: 1.125rem !important;
    height: 1.125rem !important;
  }
  
  .answer-grid {
    gap: 0.625rem !important;
  }
  
  .text-answer-input {
    font-size: 0.9375rem !important;
    padding: 0.875rem !important;
  }
  
  .text-answer-input.essay {
    min-height: 190px !important;
  }
  
  .text-answer-input.short-answer {
    min-height: 115px !important;
  }
  
  .char-counter {
    font-size: 0.75rem !important;
    margin-top: 0.4375rem !important;
  }
  
  .question-type-badge,
  .points-badge {
    font-size: 0.6875rem !important;
    padding: 0.25rem 0.5625rem !important;
  }
  
  #submitBtn {
    padding: 1rem !important;
    font-size: 1rem !important;
    margin-top: 1.5rem !important;
  }
  
  .modal-content {
    padding: 1.5rem;
    width: 92%;
    border-radius: 1.25rem;
  }
  
  .modal-content h2 {
    font-size: 1.375rem;
    margin-bottom: 0.5rem;
  }
  
  .modal-content p {
    font-size: 0.875rem;
  }
  
  .modal-content button {
    font-size: 0.9375rem;
    padding: 0.75rem 1rem;
  }
}

/* EXTRA SMALL MOBILE (≤374px) */
@media (max-width: 374px) {
  .quiz-sidebar h1 {
    font-size: 0.9375rem !important;
  }
  
  .quiz-sidebar .text-sm {
    font-size: 0.625rem !important;
  }
  
  #timer {
    font-size: 0.875rem !important;
  }
  
  .quiz-sidebar .p-4 {
    padding: 0.375rem 0.5rem !important;
  }
  
  .quiz-main {
    padding: 0.875rem;
  }
  
  .question-card {
    padding: 1rem !important;
  }
  
  .question-card p {
    font-size: 0.875rem !important;
  }
  
  .radio-option {
    padding: 0.75rem 0.875rem !important;
    font-size: 0.8125rem !important;
  }
  
  .text-answer-input {
    font-size: 0.875rem !important;
    padding: 0.75rem !important;
  }
  
  #submitBtn {
    padding: 0.9375rem !important;
    font-size: 0.9375rem !important;
  }
}

/* TABLET (768px - 1024px) */
@media (min-width: 768px) and (max-width: 1024px) {
  body.warning-active {
    padding-top: 58px;
  }
  
  .warning-banner {
    font-size: 0.8125rem;
    padding: 0.75rem 1rem;
  }
  
  .quiz-sidebar {
    top: 58px;
    padding: 1.125rem 1.5rem !important;
  }
  
  .quiz-sidebar h1 {
    font-size: 1.25rem !important;
  }
  
  #timer {
    font-size: 1.0625rem !important;
  }
  
  .quiz-sidebar .p-4 {
    padding: 0.5625rem 0.875rem !important;
  }
  
  .quiz-main {
    margin-top: 135px;
    padding: 1.5rem;
    padding-bottom: 2rem;
  }
  
  .question-card {
    padding: 1.75rem !important;
  }
  
  .question-card p {
    font-size: 1.125rem !important;
  }
  
  .radio-option {
    padding: 1.125rem 1.25rem !important;
    font-size: 1rem !important;
  }
  
  .text-answer-input {
    font-size: 1.0625rem !important;
    padding: 1.125rem !important;
  }
  
  .text-answer-input.essay {
    min-height: 240px !important;
  }
  
  .text-answer-input.short-answer {
    min-height: 140px !important;
  }
  
  #submitBtn {
    padding: 1.25rem !important;
    font-size: 1.125rem !important;
    margin-top: 2rem !important;
  }
}

/* LANDSCAPE MOBILE (short screens) */
@media (max-width: 1024px) and (orientation: landscape) and (max-height: 500px) {
  body.warning-active {
    padding-top: 45px;
  }
  
  .warning-banner {
    font-size: 0.625rem;
    padding: 0.4375rem 0.5rem;
  }
  
  .quiz-sidebar {
    top: 45px;
    padding: 0.625rem 1rem !important;
  }
  
  .quiz-sidebar h1 {
    font-size: 0.9375rem !important;
  }
  
  .quiz-sidebar > div > div:first-child > div:nth-child(3) {
    margin-top: 0.375rem !important;
  }
  
  .quiz-main {
    margin-top: 95px;
    padding: 0.875rem;
    padding-bottom: 1rem;
  }
  
  .question-card {
    padding: 1rem !important;
  }
  
  .text-answer-input.essay {
    min-height: 140px !important;
  }
  
  .text-answer-input.short-answer {
    min-height: 90px !important;
  }
  
  #submitBtn {
    margin-top: 1rem !important;
  }
}
</style>
</head>
<body class="warning-active">

<!-- Fullscreen Modal -->
<div id="fullscreenModal" class="fullscreen-modal">
  <div class="modal-content text-center">
    <div class="mb-6">
      <div class="w-20 h-20 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
        </svg>
      </div>
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Fullscreen Required</h2>
      <p class="text-gray-600 mb-6">
        To maintain quiz integrity and prevent cheating, you must take this quiz in fullscreen mode.
      </p>
    </div>

    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 text-left">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-yellow-700">
            <strong>Important:</strong> Exiting fullscreen or switching tabs will automatically submit your quiz.
          </p>
        </div>
      </div>
    </div>

    <button id="enterFullscreenBtn" 
            class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all transform hover:scale-105">
      🚀 Enter Fullscreen & Start Quiz
    </button>

    <button onclick="window.location.href='quizzes.php'" 
            class="w-full mt-3 bg-gray-100 text-gray-700 font-medium py-2 px-6 rounded-lg hover:bg-gray-200 transition">
      ← Back to Quizzes
    </button>
  </div>
</div>

<!-- Warning Banner -->
<div class="warning-banner" style="display: none;" id="warningBanner">
  <span class="warning-icon">⚠️</span>
  <span class="ml-2">Alt tabs and exiting fullscreen are not allowed! Your quiz will be automatically submitted if you switch tabs or exit fullscreen!</span>
  <span class="warning-icon ml-2">⚠️</span>
</div>

<!-- Quiz Content -->
<div id="quizContent" class="quiz-content">
  <div class="quiz-container">
    
    <!-- Sidebar -->
    <aside class="quiz-sidebar">
      <div class="h-full flex flex-col p-8 pb-6">
        <div class="space-y-6 flex-1 overflow-y-auto pr-2">
          <div class="space-y-6">
            <!-- Quiz Title -->
            <div>
              <h1 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($quiz['title']) ?></h1>
              <p class="text-gray-500 text-sm"><?= htmlspecialchars($quiz['description']) ?></p>
              <p class="text-gray-600 text-sm mt-2">Attempt #<?= $attemptNumber ?></p>
            </div>

            <!-- Timer -->
            <?php if ($timeLimit > 0): ?>
            <div class="p-4 bg-red-50 rounded-lg border border-red-100 shadow-sm text-center">
              <p class="text-red-600 font-semibold mb-1 text-sm">⏱ Time Remaining</p>
              <div class="text-3xl font-bold text-red-600" id="timer">--:--</div>
            </div>
            <?php else: ?>
            <p class="text-green-600 font-medium text-center">✅ No time limit</p>
            <?php endif; ?>

            <!-- Progress -->
            <div>
              <p class="text-gray-500 text-sm mb-2 text-center">Progress</p>
              <div class="progress-bg rounded-full h-2 overflow-hidden mb-2">
                <div id="progressBar" class="accent h-2 rounded-full w-0 transition-all duration-300"></div>
              </div>
              <p class="text-sm text-gray-500 text-center">
                <span id="answeredCount">0</span> / <?= count($questions) ?> answered
              </p>
            </div>

            <!-- Question Navigator -->
            <div class="question-nav">
              <p class="text-gray-500 text-sm mb-2 text-center">Questions</p>
              <div class="grid grid-cols-5 gap-2">
                <?php foreach ($questions as $index => $q): ?>
                  <button type="button" data-qid="q<?= $q['id'] ?>" class="w-10 h-10 rounded-full border border-gray-300 text-gray-600 font-medium">
                    <?= $index + 1 ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="pt-6 mt-auto">
            <button type="submit"
                    form="quizForm"
                    id="submitBtn"
                    disabled
                    class="w-full accent text-white font-medium py-3 rounded-lg shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
              🚀 Submit Quiz
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="quiz-main">
      <div class="max-w-3xl mx-auto space-y-8">
        <form id="quizForm" action="../actions/attempt_quiz.php" method="POST" class="space-y-8">
          <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
          <input type="hidden" name="attempt_number" value="<?= $attemptNumber ?>">
          <input type="hidden" name="auto_submitted" value="0" id="autoSubmitFlag">

          <?php foreach ($questions as $index => $q): ?>
          <div id="q<?= $q['id'] ?>" class="question-card bg-white rounded-xl p-6 transition-colors">
            
            <!-- Question Type Badge and Points -->
            <div class="flex items-center flex-wrap gap-2 mb-3">
              <?php 
                $questionType = $q['question_type'];
                $badgeClass = 'badge-multiple-choice';
                $badgeText = 'Multiple Choice';
                
                if ($questionType === 'short_answer') {
                  $badgeClass = 'badge-short-answer';
                  $badgeText = 'Short Answer';
                } elseif ($questionType === 'essay') {
                  $badgeClass = 'badge-essay';
                  $badgeText = 'Essay';
                } elseif ($questionType === 'true_false') {
                  $badgeClass = 'badge-true-false';
                  $badgeText = 'True / False';
                }
                
                $points = isset($q['points']) && $q['points'] > 0 ? intval($q['points']) : 1;
              ?>
              <span class="question-type-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
              <span class="points-badge">🎯 <?= $points ?> point<?= $points != 1 ? 's' : '' ?></span>
            </div>
            
            <!-- Question Text -->
            <p class="font-semibold text-lg text-gray-900 mb-4 no-copy"><?= ($index + 1) . ". " . htmlspecialchars($q['question_text']) ?></p>
            
            <?php if (in_array($questionType, ['multiple_choice', 'true_false'])): ?>
              <!-- Multiple Choice / True-False Options -->
              <div class="grid answer-grid grid-cols-1 gap-3 sm:grid-cols-2 no-copy">
                <?php foreach ($answers[$q['id']] as $a): ?>
                <label class="radio-option flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                  <input type="radio"
                        name="answers[<?= $q['id'] ?>]"
                        value="<?= htmlspecialchars($a['id']) ?>"
                        class="w-4 h-4 text-blue-600 focus:ring-blue-500 answer-radio"
                        data-question-id="<?= $q['id'] ?>"
                        required>
                  <span class="text-gray-700"><?= htmlspecialchars($a['answer_text']) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
              
            <?php elseif ($questionType === 'short_answer'): ?>
              <!-- Short Answer -->
              <div>
                <textarea name="text_answers[<?= $q['id'] ?>]"
                          class="text-answer-input short-answer answer-text"
                          data-question-id="<?= $q['id'] ?>"
                          placeholder="Type your answer here..."
                          maxlength="500"
                          required></textarea>
                <div class="char-counter">
                  <span class="char-count">0</span> / 500 characters
                </div>
              </div>
              
            <?php elseif ($questionType === 'essay'): ?>
              <!-- Essay -->
              <div>
                <textarea name="text_answers[<?= $q['id'] ?>]"
                          class="text-answer-input essay answer-text"
                          data-question-id="<?= $q['id'] ?>"
                          placeholder="Write your essay here... Take your time to explain your answer thoroughly."
                          maxlength="5000"
                          required></textarea>
                <div class="char-counter">
                  <span class="char-count">0</span> / 5000 characters
                </div>
              </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </form>
      </div>
    </main>
    
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const totalQuestions = <?= count($questions) ?>;
  const submitBtn = document.getElementById("submitBtn");
  const answeredCountSpan = document.getElementById("answeredCount");
  const progressBar = document.getElementById("progressBar");
  const radios = document.querySelectorAll(".answer-radio");
  const textInputs = document.querySelectorAll(".answer-text");
  const navButtons = document.querySelectorAll(".question-nav button");
  const quizForm = document.getElementById("quizForm");
  const autoSubmitFlag = document.getElementById("autoSubmitFlag");
  const fullscreenModal = document.getElementById("fullscreenModal");
  const enterFullscreenBtn = document.getElementById("enterFullscreenBtn");
  const quizContent = document.getElementById("quizContent");
  const warningBanner = document.getElementById("warningBanner");
  
  let hasLeftPage = false;
  let fullscreenInitialized = false;
  let quizStarted = false;

  // Character counter
  textInputs.forEach(input => {
    const counter = input.closest('.question-card').querySelector('.char-count');
    const counterContainer = input.closest('.question-card').querySelector('.char-counter');
    
    input.addEventListener('input', () => {
      const length = input.value.length;
      const maxLength = input.getAttribute('maxlength');
      counter.textContent = length;
      
      if (length > maxLength * 0.9) {
        counterContainer.classList.add('error');
        counterContainer.classList.remove('warning');
      } else if (length > maxLength * 0.75) {
        counterContainer.classList.add('warning');
        counterContainer.classList.remove('error');
      } else {
        counterContainer.classList.remove('warning', 'error');
      }
    });
  });

  // Fullscreen
  function enterFullscreen() {
    const elem = document.documentElement;
    if (elem.requestFullscreen) {
      return elem.requestFullscreen();
    } else if (elem.webkitRequestFullscreen) {
      return elem.webkitRequestFullscreen();
    } else if (elem.msRequestFullscreen) {
      return elem.msRequestFullscreen();
    } else if (elem.mozRequestFullScreen) {
      return elem.mozRequestFullScreen();
    }
    return Promise.reject(new Error('Fullscreen not supported'));
  }

  enterFullscreenBtn.addEventListener('click', () => {
    enterFullscreen()
      .then(() => {
        setTimeout(() => {
          const isFullscreen = !!(document.fullscreenElement || 
                                  document.webkitFullscreenElement || 
                                  document.mozFullScreenElement || 
                                  document.msFullscreenElement);
          
          if (isFullscreen) {
            fullscreenModal.style.display = 'none';
            quizContent.classList.add('active');
            warningBanner.style.display = 'block';
            quizStarted = true;
            fullscreenInitialized = true;
            
            <?php if ($timeLimit > 0): ?>
            startTimer();
            <?php endif; ?>
          } else {
            alert('⚠️ Please allow fullscreen to start the quiz.');
          }
        }, 100);
      })
      .catch((error) => {
        console.error('Fullscreen error:', error);
        alert('⚠️ Unable to enter fullscreen. Please allow fullscreen permissions to take the quiz.');
      });
  });

  document.addEventListener("fullscreenchange", handleFullscreenChange);
  document.addEventListener("webkitfullscreenchange", handleFullscreenChange);
  document.addEventListener("mozfullscreenchange", handleFullscreenChange);
  document.addEventListener("MSFullscreenChange", handleFullscreenChange);

  function handleFullscreenChange() {
    const isFullscreen = !!(document.fullscreenElement || 
                            document.webkitFullscreenElement || 
                            document.mozFullScreenElement || 
                            document.msFullscreenElement);
    
    if (isFullscreen && !fullscreenInitialized) {
      fullscreenInitialized = true;
    } else if (!isFullscreen && fullscreenInitialized && quizStarted && !hasLeftPage) {
      hasLeftPage = true;
      autoSubmitFlag.value = "1";
      alert("⚠️ You exited fullscreen mode! Your quiz is being automatically submitted.");
      quizForm.submit();
    }
  }

  document.addEventListener("keydown", (e) => {
    if (!quizStarted) return;
    
    if (e.key === "F11") {
      e.preventDefault();
      alert("⚠️ You cannot exit fullscreen during the quiz!");
    }
    if (e.key === "Escape" || e.key === "Esc") {
      e.preventDefault();
      alert("⚠️ Escape key is disabled during the quiz!");
    }
    if ((e.altKey && e.key === "Tab") || (e.ctrlKey && e.key === "Tab")) {
      e.preventDefault();
      alert("⚠️ Tab switching is not allowed during the quiz!");
    }

    // Allow copy/paste/cut in text inputs, but nowhere else
    const isTextInput = e.target.classList.contains('text-answer-input');
    
    if (!isTextInput) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C')) {
        e.preventDefault();
      }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'x' || e.key === 'X')) {
        e.preventDefault();
      }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'a' || e.key === 'A')) {
        e.preventDefault();
      }
    }
  });

  // Progress tracking
  function updateProgress() {
    const answered = new Set();
    
    radios.forEach(r => {
      if (r.checked) {
        const qid = r.dataset.questionId;
        answered.add(qid);
        const btn = document.querySelector(`.question-nav button[data-qid='q${qid}']`);
        if(btn) btn.classList.add("answered");
      }
    });
    
    textInputs.forEach(input => {
      if (input.value.trim().length > 0) {
        const qid = input.dataset.questionId;
        answered.add(qid);
        const btn = document.querySelector(`.question-nav button[data-qid='q${qid}']`);
        if(btn) btn.classList.add("answered");
      }
    });
    
    const count = answered.size;
    answeredCountSpan.textContent = count;
    progressBar.style.width = (count / totalQuestions * 100) + "%";
    submitBtn.disabled = count < totalQuestions;
    
    // Update progress label for mobile
    const progressLabel = document.querySelector('.quiz-sidebar > div > div:first-child > div:nth-child(3) > p:first-child');
    if (progressLabel && window.innerWidth <= 1024) {
      progressLabel.textContent = `Questions ${count} of ${totalQuestions}`;
    } else if (progressLabel) {
      progressLabel.textContent = 'Progress';
    }
  }

  radios.forEach(radio => radio.addEventListener("change", updateProgress));
  textInputs.forEach(input => input.addEventListener("input", updateProgress));
  updateProgress();

  // Move submit button to end of form on mobile
  function repositionSubmitButton() {
    if (window.innerWidth <= 1024) {
      const form = document.getElementById('quizForm');
      const submitBtnContainer = document.querySelector('.quiz-sidebar > div > div:last-child');
      if (form && submitBtnContainer && submitBtn) {
        form.appendChild(submitBtn);
      }
    }
  }
  
  repositionSubmitButton();
  window.addEventListener('resize', repositionSubmitButton);

  // Question navigation
  navButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const target = document.getElementById(btn.dataset.qid);
      if(target) target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  // Alt-tab detection
  document.addEventListener("visibilitychange", () => {
    if (!quizStarted) return;
    
    if (document.hidden && !hasLeftPage) {
      hasLeftPage = true;
      autoSubmitFlag.value = "1";
      
      setTimeout(() => {
        alert("⚠️ You switched tabs! Your quiz is being automatically submitted.");
        quizForm.submit();
      }, 100);
    }
  });

  window.addEventListener("blur", () => {
    if (!quizStarted || hasLeftPage) return;
    
    hasLeftPage = true;
    autoSubmitFlag.value = "1";
    
    setTimeout(() => {
      alert("⚠️ You switched away from the quiz! Your quiz is being automatically submitted.");
      quizForm.submit();
    }, 100);
  });

  // Security - removed context menu prevention, only prevent copy/cut/select on non-text elements
  document.addEventListener("copy", (e) => {
    if (quizStarted && !e.target.classList.contains('text-answer-input')) {
      e.preventDefault();
    }
  });

  document.addEventListener("cut", (e) => {
    if (quizStarted && !e.target.classList.contains('text-answer-input')) {
      e.preventDefault();
    }
  });

  document.addEventListener("selectstart", (e) => {
    if (quizStarted && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
    }
  });

  const devToolsCheck = () => {
    if (!quizStarted) return;
    
    const threshold = 160;
    if (window.outerWidth - window.innerWidth > threshold || 
        window.outerHeight - window.innerHeight > threshold) {
      if (!hasLeftPage) {
        hasLeftPage = true;
        autoSubmitFlag.value = "1";
        alert("⚠️ Developer tools detected! Your quiz is being automatically submitted.");
        quizForm.submit();
      }
    }
  };
  
  setInterval(devToolsCheck, 1000);

  // Timer
  <?php if ($timeLimit > 0): ?>
  let remaining = 0;
  const timerDisplay = document.getElementById("timer");
  let timerInterval = null;
  let timerStarted = false;

  function updateTimer() {
    if (remaining <= 0) {
      timerDisplay.textContent = "00:00";
      if (timerInterval) {
        clearInterval(timerInterval);
      }
      alert("⏰ Time's up! Submitting your quiz...");
      quizForm.submit();
      return;
    }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerDisplay.textContent = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    remaining--;
  }

  async function startTimer() {
    if (timerStarted) return;
    
    try {
      const response = await fetch('start_quiz_timer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          quiz_id: <?= $quizId ?>,
          time_limit: <?= $timeLimit ?>
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        remaining = data.remaining;
        timerStarted = true;
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
        console.log('Timer started:', data);
      } else {
        console.error('Failed to start timer:', data.error);
        alert('Failed to start timer. Please refresh and try again.');
      }
    } catch (error) {
      console.error('Timer start error:', error);
      remaining = <?= $remaining ?>;
      timerStarted = true;
      updateTimer();
      timerInterval = setInterval(updateTimer, 1000);
    }
  }
  <?php endif; ?>
});
</script>
</body>
</html>