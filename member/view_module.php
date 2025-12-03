<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: resources.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$moduleId = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT firstname, lastname, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

if (!empty($student['gender'])) {
    $defaultAvatar = strtolower($student['gender']) === "male" ? "../assets/img/avatar_male.png" : 
                     (strtolower($student['gender']) === "female" ? "../assets/img/avatar_female.png" : "../assets/img/avatar_neutral.png");
} else {
    $defaultAvatar = "../assets/img/avatar_neutral.png";
}

$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

$stmt = $conn->prepare("
    SELECT m.id, m.title, m.description, m.content, m.created_at,
           COALESCE(sp.status, 'Pending') AS status
    FROM modules m
    LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
    WHERE m.id = ? AND m.status IN ('active', 'published')
");
$stmt->execute([$studentId, $moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    header("Location: resources.php");
    exit();
}

// Check for assigned quiz based on prerequisite module
$stmt = $conn->prepare("
    SELECT q.id, q.title 
    FROM quizzes q
    WHERE q.prerequisite_module_id = ? AND q.status = 'active'
    LIMIT 1
");
$stmt->execute([$moduleId]);
$assignedQuiz = $stmt->fetch(PDO::FETCH_ASSOC);

function convertPPTXtoPDF($pptxPath) {
    $pdfPath = str_replace(['.pptx', '.ppt'], '.pdf', $pptxPath);
    if (file_exists($pdfPath)) {
        return $pdfPath;
    }
    $outputDir = dirname($pptxPath);
    $command = "soffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($pptxPath) . " 2>&1";
    exec($command, $output, $returnCode);
    if (file_exists($pdfPath)) {
        return $pdfPath;
    }
    return false;
}

$moduleContent = '';
$pdfFileUrl = '';

if (!empty($module['content'])) {
    if (strpos($module['content'], '../') === 0) {
        $filePath = $module['content'];
    } else {
        $filePath = "../" . $module['content'];
    }
    
    if (file_exists($filePath)) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['pptx', 'ppt'])) {
            $pdfPath = convertPPTXtoPDF($filePath);
            
            if ($pdfPath && file_exists($pdfPath)) {
                $pdfFileUrl = $pdfPath;
                $moduleContent = '
                <div class="space-y-3" x-data="{ fullscreen: false }">
                    <!-- PDF Viewer Container -->
                    <div class="bg-white rounded-xl overflow-hidden shadow-lg border border-gray-200">
                        <iframe 
                            src="' . htmlspecialchars($pdfFileUrl) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" 
                            class="w-full border-0"
                            style="height: calc(100vh - 200px); min-height: 500px;"
                            type="application/pdf"
                            title="PowerPoint Presentation">
                        </iframe>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 justify-center">
                        <button @click="fullscreen = true" class="bg-purple-600 text-white px-6 py-2.5 rounded-lg hover:bg-purple-700 transition font-semibold inline-flex items-center gap-2 shadow-sm text-sm">
                            <i class="fas fa-expand"></i>
                            Fullscreen
                        </button>
                        <a href="' . htmlspecialchars($pdfFileUrl) . '" target="_blank" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition font-semibold inline-flex items-center gap-2 text-sm">
                            <i class="fas fa-external-link-alt"></i>
                            New Tab
                        </a>
                    </div>
                    
                    <!-- Fullscreen Modal -->
                    <div x-show="fullscreen" 
                         class="fixed inset-0 z-[100] bg-black" 
                         x-transition 
                         @keydown.escape.window="fullscreen = false" 
                         x-cloak>
                        <div class="relative w-full h-full">
                            <button @click="fullscreen = false" 
                                    class="absolute top-4 right-4 z-10 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium flex items-center gap-2 shadow-lg">
                                <i class="fas fa-times"></i>
                                Close
                            </button>
                            <iframe 
                                src="' . htmlspecialchars($pdfFileUrl) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" 
                                class="w-full h-full border-0" 
                                type="application/pdf">
                            </iframe>
                        </div>
                    </div>
                </div>';
            } else {
                $moduleContent = '
                <div class="max-w-2xl mx-auto space-y-6">
                    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-8 text-center">
                        <div class="text-5xl mb-4">⚠️</div>
                        <h3 class="text-xl font-bold text-yellow-800 mb-2">PDF Conversion Unavailable</h3>
                        <p class="text-base text-yellow-700 mb-4">Unable to convert PowerPoint to PDF. Please download the file.</p>
                    </div>
                    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl p-12 text-white text-center shadow-2xl">
                        <div class="mb-8">
                            <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                                <i class="fas fa-file-powerpoint text-5xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold mb-3">PowerPoint Presentation</h2>
                            <p class="text-base text-blue-100 break-all px-2">' . htmlspecialchars(basename($filePath)) . '</p>
                        </div>
                        <div class="space-y-4 max-w-md mx-auto">
                            <a href="' . htmlspecialchars($filePath) . '" target="_blank" class="block bg-white text-blue-700 px-8 py-4 rounded-2xl shadow-xl hover:bg-blue-50 font-bold text-lg">
                                <i class="fas fa-eye mr-2"></i>View
                            </a>
                            <a href="' . htmlspecialchars($filePath) . '" download class="block bg-blue-800 text-white px-8 py-4 rounded-2xl hover:bg-blue-900 font-bold text-lg">
                                <i class="fas fa-download mr-2"></i>Download
                            </a>
                        </div>
                    </div>
                </div>';
            }
            
        } elseif ($extension === 'pdf') {
            $pdfFileUrl = $filePath;
            $moduleContent = '
            <div class="space-y-3" x-data="{ fullscreen: false }">
                <!-- PDF Viewer Container -->
                <div class="bg-white rounded-xl overflow-hidden shadow-lg border border-gray-200">
                    <iframe 
                        src="' . htmlspecialchars($filePath) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" 
                        class="w-full border-0" 
                        style="height: calc(100vh - 200px); min-height: 500px;"
                        type="application/pdf" 
                        title="PDF Document">
                    </iframe>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-3 justify-center">
                    <button @click="fullscreen = true" class="bg-purple-600 text-white px-6 py-2.5 rounded-lg hover:bg-purple-700 transition font-semibold inline-flex items-center gap-2 shadow-sm text-sm">
                        <i class="fas fa-expand"></i>
                        Fullscreen
                    </button>
                    <a href="' . htmlspecialchars($filePath) . '" target="_blank" class="bg-red-600 text-white px-6 py-2.5 rounded-lg hover:bg-red-700 transition font-semibold inline-flex items-center gap-2 text-sm">
                        <i class="fas fa-external-link-alt"></i>
                        New Tab
                    </a>
                </div>
                
                <!-- Fullscreen Modal -->
                <div x-show="fullscreen" 
                     class="fixed inset-0 z-[100] bg-black" 
                     x-transition 
                     @keydown.escape.window="fullscreen = false" 
                     x-cloak>
                    <div class="relative w-full h-full">
                        <button @click="fullscreen = false" 
                                class="absolute top-4 right-4 z-10 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium flex items-center gap-2 shadow-lg">
                            <i class="fas fa-times"></i>
                            Close
                        </button>
                        <iframe 
                            src="' . htmlspecialchars($filePath) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" 
                            class="w-full h-full border-0" 
                            type="application/pdf">
                        </iframe>
                    </div>
                </div>
            </div>';
            
        } elseif (in_array($extension, ['docx', 'doc'])) {
            $moduleContent = '
            <div class="bg-gradient-to-br from-green-600 to-emerald-700 rounded-3xl p-12 text-white text-center shadow-2xl max-w-2xl mx-auto">
                <div class="mb-8">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                        <i class="fas fa-file-word text-5xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold mb-3">Word Document</h2>
                    <p class="text-base text-green-100 break-all px-2">' . htmlspecialchars(basename($filePath)) . '</p>
                </div>
                <div class="space-y-4 max-w-md mx-auto">
                    <a href="' . htmlspecialchars($filePath) . '" target="_blank" class="block bg-white text-green-700 px-8 py-4 rounded-2xl shadow-xl hover:bg-green-50 font-bold text-lg">
                        <i class="fas fa-eye mr-2"></i>Open
                    </a>
                    <a href="' . htmlspecialchars($filePath) . '" download class="block bg-green-800 text-white px-8 py-4 rounded-2xl hover:bg-green-900 font-bold text-lg">
                        <i class="fas fa-download mr-2"></i>Download
                    </a>
                </div>
            </div>';
            
        } elseif (in_array($extension, ['html', 'htm'])) {
            $moduleContent = file_get_contents($filePath);
            
        } elseif ($extension === 'txt') {
            $moduleContent = '<div class="bg-white p-8 rounded-2xl shadow-lg border-2 max-w-4xl mx-auto"><pre class="whitespace-pre-wrap font-mono text-sm overflow-x-auto">' . htmlspecialchars(file_get_contents($filePath)) . '</pre></div>';
        }
    } else {
        $moduleContent = '<div class="bg-red-50 border-2 border-red-200 rounded-2xl p-8 text-center max-w-2xl mx-auto">
            <div class="text-5xl mb-4">❌</div>
            <h3 class="text-xl font-bold text-red-800 mb-2">File Not Found</h3>
            <p class="text-base text-red-700 mb-4">The module file could not be found at:</p>
            <p class="text-sm font-mono bg-red-100 p-3 rounded text-red-900 break-all">' . htmlspecialchars($filePath) . '</p>
            <p class="text-sm text-red-600 mt-4">Please contact your instructor.</p>
        </div>';
    }
} else {
    $moduleContent = '<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center"><i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i><p class="text-gray-500 text-lg">No content available for this module.</p></div>';
}

if ($module['status'] === 'Pending') {
    $stmt = $conn->prepare("INSERT INTO student_progress (student_id, module_id, status, started_at) VALUES (?, ?, 'In Progress', NOW())");
    $stmt->execute([$studentId, $moduleId]);
}

$statusClass = match (strtolower($module['status'])) {
    'completed' => 'bg-green-100 text-green-700',
    'in progress' => 'bg-blue-100 text-blue-700',
    'pending' => 'bg-yellow-100 text-yellow-700',
    default => 'bg-gray-100 text-gray-700'
};
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($module['title']) ?> - MedAce</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e',
                        }
                    },
                    screens: { 'xs': '475px' }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.6s ease-out; }
        
        @keyframes slideInFromRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-slide-in { animation: slideInFromRight 0.5s ease-out; }
        
        .sidebar-transition { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* Desktop */
        @media (min-width: 1025px) {
            #sidebar { width: 5rem; }
            #sidebar.sidebar-expanded { width: 18rem; }
            #sidebar .nav-text, #sidebar .profile-info { opacity: 0; width: 0; overflow: hidden; transition: opacity 0.2s ease; }
            #sidebar.sidebar-expanded .nav-text, #sidebar.sidebar-expanded .profile-info { opacity: 1; width: auto; transition: opacity 0.3s ease 0.1s; }
            #main-content { margin-left: 5rem; }
            #main-content.content-expanded { margin-left: 18rem; }
        }
        
        /* Mobile */
        @media (max-width: 1024px) {
            #sidebar { width: 18rem; transform: translateX(-100%); }
            #sidebar.sidebar-expanded { transform: translateX(0); }
            #sidebar .nav-text, #sidebar .profile-info { opacity: 1; width: auto; }
            #main-content { margin-left: 0 !important; }
        }
        
        @media (max-width: 768px) { #sidebar { width: 16rem; } }
        @media (max-width: 640px) { #sidebar { width: 85vw; max-width: 20rem; } }
        
        [x-cloak] { display: none !important; }
        body.sidebar-open { overflow: hidden; }
        @media (min-width: 1025px) { body.sidebar-open { overflow: auto; } }
        
        #sidebar-overlay { transition: opacity 0.3s ease; opacity: 0; }
        #sidebar-overlay.show { opacity: 1; }
        .main-container { width: 100%; max-width: 100%; overflow-x: hidden; }
        
        /* Quiz button styles */
        .quiz-redirect-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 40;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        @media (max-width: 640px) {
            .quiz-redirect-btn {
                bottom: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ showCompleteModal: false, showQuizButton: <?= strtolower($module['status']) === 'completed' && $assignedQuiz ? 'true' : 'false' ?> }">

<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition">
        <div class="flex flex-col h-full">
            <div class="flex items-center justify-between px-4 py-5 border-b border-gray-200">
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover ring-2 ring-primary-500">
                        <span class="absolute bottom-0 right-0 w-3 h-3 sm:w-3.5 sm:h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="profile-info sidebar-transition min-w-0">
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                        <p class="text-xs text-gray-500">Student</p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 border-b border-gray-200 lg:hidden">
                <button onclick="closeSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span>
                </a>
                <a href="progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">My Progress</span>
                </a>
                <a href="quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                    <i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span>
                </a>
                <a href="resources.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                    <i class="fas fa-book text-primary-600 w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Resources</span>
                </a>
            </nav>

            <div class="px-3 py-4 border-t border-gray-200">
                <a href="../actions/logout_action.php" class="flex items-center space-x-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-all">
                    <i class="fas fa-sign-out-alt w-5 text-center flex-shrink-0"></i>
                    <span class="nav-text sidebar-transition whitespace-nowrap">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="closeSidebar()"></div>

    <main id="main-content" class="flex-1 transition-all duration-300 main-container">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0">
                        <i class="fas fa-bars text-gray-600 text-lg"></i>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-base lg:text-lg font-bold text-gray-900 truncate"><?= htmlspecialchars($module['title']) ?></h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                        <i class="fas <?= strtolower($module['status']) === 'completed' ? 'fa-check-circle' : (strtolower($module['status']) === 'in progress' ? 'fa-spinner' : 'fa-clock') ?> mr-1"></i>
                        <?= htmlspecialchars(ucwords($module['status'])) ?>
                    </span>
                    <?php if(strtolower($module['status']) !== 'completed'): ?>
                        <button @click="showCompleteModal = true" class="bg-green-600 hover:bg-green-700 text-white px-3 lg:px-4 py-1.5 lg:py-2 rounded-lg font-semibold transition-colors text-xs lg:text-sm flex-shrink-0">
                            <i class="fas fa-check mr-1"></i>
                            <span class="hidden sm:inline">Complete</span>
                            <span class="sm:hidden">Done</span>
                        </button>
                    <?php endif; ?>
                    <a href="resources.php" class="inline-flex items-center text-primary-600 hover:text-primary-700 font-medium px-3 py-1.5 rounded-lg hover:bg-primary-50 transition text-xs lg:text-sm flex-shrink-0">
                        <i class="fas fa-arrow-left mr-1"></i>
                        <span class="hidden sm:inline">Back</span>
                    </a>
                </div>
            </div>
        </header>

        <div class="px-3 lg:px-6 py-3 lg:py-4 max-w-full">
            <div class="animate-fade-in-up">
                <?= $moduleContent ?>
            </div>
        </div>
    </main>
</div>

<!-- Complete Module Modal -->
<div x-show="showCompleteModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.away="showCompleteModal = false" x-cloak>
    <div class="bg-white rounded-2xl max-w-md w-full p-8 animate-fade-in-up">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check text-3xl text-green-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Mark as Complete?</h3>
            <p class="text-base text-gray-600 mb-6">Are you sure you want to mark this module as completed?</p>
            
            <form action="../actions/complete_module.php" method="POST" class="space-y-3">
                <input type="hidden" name="module_id" value="<?= $moduleId ?>">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors text-base">
                    <i class="fas fa-check mr-2"></i>
                    Yes, Mark Complete
                </button>
                <button type="button" @click="showCompleteModal = false" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors text-base">
                    Cancel
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Quiz Redirect Button (appears after completion) -->
<?php if ($assignedQuiz): ?>
<div x-show="showQuizButton" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-x-full"
     x-transition:enter-end="opacity-100 transform translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-x-0"
     x-transition:leave-end="opacity-0 transform translate-x-full"
     class="quiz-redirect-btn animate-slide-in"
     x-cloak>
    <a href="take_quiz.php?id=<?= $assignedQuiz['id'] ?>" 
       class="group flex items-center gap-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-6 py-4 rounded-2xl font-bold text-base transition-all duration-300 transform hover:scale-105">
        <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-xl group-hover:bg-white/30 transition-colors">
            <i class="fas fa-clipboard-check text-2xl"></i>
        </div>
        <div class="flex flex-col items-start">
            <span class="text-xs font-medium opacity-90">Take Quiz</span>
            <span class="text-sm font-bold"><?= htmlspecialchars($assignedQuiz['title']) ?></span>
        </div>
        <i class="fas fa-arrow-right text-xl ml-2 group-hover:translate-x-1 transition-transform"></i>
    </a>
</div>
<?php endif; ?>

<script>
let sidebarExpanded = false;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const overlay = document.getElementById('sidebar-overlay');
    sidebarExpanded = !sidebarExpanded;
    
    if (window.innerWidth < 1025) {
        if (sidebarExpanded) {
            sidebar.classList.add('sidebar-expanded');
            overlay.classList.remove('hidden');
            overlay.classList.add('show');
            document.body.classList.add('sidebar-open');
        } else {
            sidebar.classList.remove('sidebar-expanded');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
            setTimeout(() => overlay.classList.add('hidden'), 300);
        }
    } else {
        if (sidebarExpanded) {
            sidebar.classList.add('sidebar-expanded');
            mainContent.classList.add('content-expanded');
        } else {
            sidebar.classList.remove('sidebar-expanded');
            mainContent.classList.remove('content-expanded');
        }
    }
}

function closeSidebar() {
    if (sidebarExpanded) toggleSidebar();
}

window.addEventListener('resize', function() {
    clearTimeout(window.resizeTimer);
    window.resizeTimer = setTimeout(function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (window.innerWidth >= 1025) {
            overlay.classList.add('hidden');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
            if (!sidebarExpanded) {
                sidebar.classList.remove('sidebar-expanded');
                mainContent.classList.remove('content-expanded');
            } else {
                sidebar.classList.add('sidebar-expanded');
                mainContent.classList.add('content-expanded');
            }
        } else {
            mainContent.classList.remove('content-expanded');
            if (!sidebarExpanded) {
                sidebar.classList.remove('sidebar-expanded');
                overlay.classList.add('hidden');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }
        }
    }, 250);
});

window.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    if (window.innerWidth >= 1025) {
        sidebar.classList.remove('sidebar-expanded');
        mainContent.classList.remove('content-expanded');
        sidebarExpanded = false;
    } else {
        sidebar.classList.remove('sidebar-expanded');
        sidebarExpanded = false;
    }
});
</script>

</body>
</html> 