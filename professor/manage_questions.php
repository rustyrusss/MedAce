<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if (!$quiz_id) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: dashboard.php");
    exit();
}

$prof_stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$prof_stmt->execute([$professor_id]);
$prof = $prof_stmt->fetch(PDO::FETCH_ASSOC);

$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";
$profilePic = getProfilePicture($prof, "../");

$stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - <?= htmlspecialchars($quiz['title']) ?> - MedAce</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }

        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-collapsed {
            width: 5rem;
        }

        .sidebar-collapsed .nav-text,
        .sidebar-collapsed .profile-info {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-expanded {
            width: 18rem;
        }

        .sidebar-expanded .nav-text,
        .sidebar-expanded .profile-info {
            opacity: 1;
            width: auto;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 800px;
            width: 90%;
            animation: slideUp 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        @media (max-width: 1024px) {
            .sidebar-collapsed {
                width: 0;
                transform: translateX(-100%);
            }
            
            .sidebar-expanded {
                width: 18rem;
                transform: translateX(0);
            }
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 p-6">
  <div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow-lg">
    <h1 class="text-2xl font-bold mb-4">Manage Questions for Quiz: <?= htmlspecialchars($quiz['title']) ?></h1>

    <div class="mb-6">
      <a href="../professor/add_question.php?quiz_id=<?= $quiz_id ?>" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700">➕ Add Question</a>
      <a href="../professor/dashboard.php" class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">⬅ Back</a>
    </div>

    <h2 class="text-xl font-semibold mb-2">Existing Questions</h2>
    <?php if (!empty($questions)): ?>
      <ul class="space-y-4">
        <?php foreach ($questions as $q): ?>
          <li class="p-4 border rounded-lg bg-gray-50">
            <p class="font-medium"><?= htmlspecialchars($q['question_text']) ?></p>
            <ul class="list-disc pl-6 mt-2 text-gray-700">
              <?php
                $stmt = $conn->prepare("SELECT * FROM answers WHERE question_id = ?");
                $stmt->execute([$q['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($answers as $a): ?>
                  <li class="<?= $a['is_correct'] ? 'text-green-600 font-semibold' : '' ?>">
                    <?= htmlspecialchars($a['answer_text']) ?> <?= $a['is_correct'] ? '(Correct)' : '' ?>
                  </li>
              <?php endforeach; ?>
            </ul>
            <div class="mt-3 flex gap-3">
              <a href="edit_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">✏️ Edit</a>
              <a href="delete_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>" onclick="return confirm('Are you sure you want to delete this question?')" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">🗑 Delete</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-gray-500">No questions added yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>