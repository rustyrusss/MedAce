<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/avatar_helper.php'; 

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professorId = $_SESSION['user_id'];

// Fetch professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";

// Avatar
$profilePic = getProfilePicture($prof, "../");

// Dashboard counts
$totalModules = $conn->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$totalQuizzes = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalStudents = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// Recent modules & quizzes
$modules = $conn->query("SELECT id, title, created_at FROM modules ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$quizzes = $conn->query("SELECT id, title, publish_time, deadline_time FROM quizzes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        body { background-color: #cce7ea; }
        .hover-row:hover { background-color: #e0f2fe; transition: background-color 0.3s ease; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.5); border-radius: 3px; }
        @keyframes fadeIn { from {opacity:0; transform:translateY(10px);} to {opacity:1; transform:translateY(0);} }
        .animate-fadeIn { animation: fadeIn 0.6s ease forwards; }
        .counter { transition: all 0.5s ease; }
    </style>
</head>
<body class="font-sans text-gray-800">

<div x-data="{ sidebarOpen: false }" class="flex min-h-screen">

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" 
           class="bg-white shadow-sm border-r border-gray-200 transition-all duration-300 relative flex flex-col z-30">
        <div class="flex items-center justify-between p-3 border-b">
            <div class="flex items-center gap-2">
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar" class="w-8 h-8 rounded-full object-cover border" />
                <span x-show="sidebarOpen" class="text-sm font-semibold text-sky-700"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></span>
            </div>
            <button @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar" class="p-1 rounded-md text-gray-600 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <nav class="flex-1 mt-3 px-1 space-y-1">
            <a href="dashboard.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 flex items-center justify-center text-xl">🏠</div>
                <span x-show="sidebarOpen" class="font-medium">Dashboard</span>
            </a>
            <a href="manage_modules.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 flex items-center justify-center text-xl">📘</div>
                <span x-show="sidebarOpen" class="font-medium">Modules</span>
            </a>
            <a href="manage_quizzes.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 flex items-center justify-center text-xl">📝</div>
                <span x-show="sidebarOpen" class="font-medium">Quizzes</span>
            </a>
            <a href="student_progress.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-gray-700 hover:bg-sky-50 transition">
                <div class="w-8 flex items-center justify-center text-xl">👨‍🎓</div>
                <span x-show="sidebarOpen" class="font-medium">Student Progress</span>
            </a>
        </nav>

        <div class="px-2 py-4 border-t">
            <a href="../actions/logout_action.php" class="group flex items-center gap-3 px-2 py-2 rounded-lg text-red-600 hover:bg-red-50 transition">
                <div class="w-8 flex items-center justify-center text-xl">🚪</div>
                <span x-show="sidebarOpen" class="font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 md:p-10">
        <!-- Welcome -->
        <div class="card p-6 mb-6 bg-white rounded-2xl shadow animate-fadeIn">
            <h1 class="text-2xl font-bold">Welcome, <?= htmlspecialchars(ucwords(strtolower($profName))) ?> 👋</h1>
            <p class="text-gray-500 mt-1">Here’s a quick overview of your teaching resources.</p>
        </div>

        <!-- Overview Cards with Animated Counters -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="card p-6 bg-white rounded-2xl shadow hover:shadow-lg transition transform hover:-translate-y-1">
                <p class="text-sm text-gray-500">📘 Total Modules</p>
                <p class="text-3xl font-bold text-sky-600 counter" x-data="{value: 0}" x-init="let end=<?= (int)$totalModules ?>; let duration=1000; let startTime = null; function animate(time){if(!startTime) startTime=time; let progress=time-startTime; let val=Math.min(Math.floor(progress/duration*end),end); value=val; if(val<end) requestAnimationFrame(animate);} requestAnimationFrame(animate);"><?= (int)$totalModules ?></p>
            </div>
            <div class="card p-6 bg-white rounded-2xl shadow hover:shadow-lg transition transform hover:-translate-y-1">
                <p class="text-sm text-gray-500">📝 Total Quizzes</p>
                <p class="text-3xl font-bold text-blue-600 counter" x-data="{value:0}" x-init="let end=<?= (int)$totalQuizzes ?>; let duration=1000; let startTime=null; function animate(t){if(!startTime) startTime=t; let progress=t-startTime; let val=Math.min(Math.floor(progress/duration*end),end); value=val; if(val<end) requestAnimationFrame(animate);} requestAnimationFrame(animate);"><?= (int)$totalQuizzes ?></p>
            </div>
            <div class="card p-6 bg-white rounded-2xl shadow hover:shadow-lg transition transform hover:-translate-y-1">
                <p class="text-sm text-gray-500">👨‍🎓 Total Students</p>
                <p class="text-3xl font-bold text-indigo-600 counter" x-data="{value:0}" x-init="let end=<?= (int)$totalStudents ?>; let duration=1000; let startTime=null; function animate(t){if(!startTime) startTime=t; let progress=t-startTime; let val=Math.min(Math.floor(progress/duration*end),end); value=val; if(val<end) requestAnimationFrame(animate);} requestAnimationFrame(animate);"><?= (int)$totalStudents ?></p>
            </div>
        </div>

        <!-- Recent Modules -->
        <div class="card p-6 mt-6 bg-white rounded-2xl shadow animate-fadeIn">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">📘 Recent Modules</h2>
                <a href="manage_modules.php" class="text-sky-600 font-medium">View All →</a>
            </div>
            <?php if(empty($modules)): ?>
                <p class="text-gray-500">No modules created yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto scrollbar-thin">
                    <table class="w-full text-left text-gray-700 border-collapse">
                        <thead>
                            <tr class="bg-sky-100 text-sm text-gray-600">
                                <th class="p-3">Title</th>
                                <th class="p-3">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($modules as $m): ?>
                                <tr class="hover-row border-b">
                                    <td class="p-3"><?= htmlspecialchars($m['title']) ?></td>
                                    <td class="p-3"><?= date("F j, Y", strtotime($m['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Quizzes -->
        <div class="card p-6 mt-6 mb-10 bg-white rounded-2xl shadow animate-fadeIn">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">📝 Recent Quizzes</h2>
                <a href="manage_quizzes.php" class="text-sky-600 font-medium">View All →</a>
            </div>
            <?php if(empty($quizzes)): ?>
                <p class="text-gray-500">No quizzes available yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto scrollbar-thin">
                    <table class="w-full text-left text-gray-700 border-collapse">
                        <thead>
                            <tr class="bg-sky-100 text-sm text-gray-600">
                                <th class="p-3">Title</th>
                                <th class="p-3">Publish</th>
                                <th class="p-3">Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($quizzes as $q): ?>
                                <tr class="hover-row border-b">
                                    <td class="p-3"><?= htmlspecialchars($q['title']) ?></td>
                                    <td class="p-3"><?= $q['publish_time'] ? date("M j, Y g:i A", strtotime($q['publish_time'])) : '-' ?></td>
                                    <td class="p-3"><?= $q['deadline_time'] ? date("M j, Y g:i A", strtotime($q['deadline_time'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
