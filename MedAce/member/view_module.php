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
    WHERE m.id = ? AND m.status = 'active'
");
$stmt->execute([$studentId, $moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    header("Location: resources.php");
    exit();
}

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
    if (strpos($module['content'], 'uploads/') !== false || 
        preg_match('/\.(html|htm|txt|pdf|pptx|ppt|docx|doc)$/i', $module['content'])) {
        
        $filePath = "../" . $module['content'];
        
        if (file_exists($filePath)) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if (in_array($extension, ['pptx', 'ppt'])) {
                $pdfPath = convertPPTXtoPDF($filePath);
                
                if ($pdfPath && file_exists($pdfPath)) {
                    $pdfFileUrl = $pdfPath;
                    $moduleContent = '
                    <div class="space-y-4" x-data="{ fullscreen: false }">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-xl flex items-center justify-between shadow-lg">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div>
                                    <p class="font-semibold">PowerPoint Presentation (PDF)</p>
                                    <p class="text-sm text-blue-100">' . htmlspecialchars(basename($filePath)) . '</p>
                                </div>
                            </div>
                            <a href="' . htmlspecialchars($filePath) . '" download class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition font-medium text-sm flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download
                            </a>
                        </div>
                        
                        <div class="bg-white rounded-2xl overflow-hidden shadow-2xl border-2 border-gray-200" style="height:750px;">
                            <iframe src="' . htmlspecialchars($pdfFileUrl) . '#toolbar=1&navpanes=1&scrollbar=1" 
                                    class="w-full h-full border-0"
                                    type="application/pdf"
                                    title="PowerPoint Presentation">
                            </iframe>
                        </div>
                        
                        <div class="flex gap-3 justify-center flex-wrap">
                            <button @click="fullscreen = true" class="bg-purple-600 text-white px-6 py-3 rounded-xl hover:bg-purple-700 transition font-semibold inline-flex items-center gap-2 shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                View Fullscreen
                            </button>
                            <a href="' . htmlspecialchars($pdfFileUrl) . '" target="_blank" class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition font-semibold inline-flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                Open in New Tab
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
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Close
                                </button>
                                <iframe src="' . htmlspecialchars($pdfFileUrl) . '#toolbar=0&navpanes=0&scrollbar=1&view=FitH" 
                                        class="w-full h-full border-0"
                                        type="application/pdf">
                                </iframe>
                            </div>
                        </div>
                    </div>';
                } else {
                    $moduleContent = '
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-8 text-center mb-6">
                            <div class="text-5xl mb-4">⚠️</div>
                            <h3 class="text-xl font-bold text-yellow-800 mb-2">PDF Conversion Unavailable</h3>
                            <p class="text-yellow-700 mb-4">LibreOffice is not installed. Please download the presentation.</p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl p-12 text-white text-center shadow-2xl">
                            <div class="mb-8">
                                <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h2 class="text-3xl font-bold mb-3">PowerPoint Presentation</h2>
                                <p class="text-blue-100">' . htmlspecialchars(basename($filePath)) . '</p>
                            </div>
                            <div class="space-y-4 max-w-md mx-auto">
                                <a href="' . htmlspecialchars($filePath) . '" target="_blank" class="block bg-white text-blue-700 px-8 py-4 rounded-2xl shadow-xl hover:bg-blue-50 font-bold text-lg">📺 View</a>
                                <a href="' . htmlspecialchars($filePath) . '" download class="block bg-blue-800 text-white px-8 py-4 rounded-2xl hover:bg-blue-900 font-bold text-lg">⬇️ Download</a>
                            </div>
                        </div>
                    </div>';
                }
                
            } elseif ($extension === 'pdf') {
                $pdfFileUrl = $filePath;
                $moduleContent = '
                <div class="space-y-4" x-data="{ fullscreen: false }">
                    <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-4 rounded-xl flex items-center justify-between shadow-lg">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <p class="font-semibold">PDF Document</p>
                                <p class="text-sm text-red-100">' . htmlspecialchars(basename($filePath)) . '</p>
                            </div>
                        </div>
                        <a href="' . htmlspecialchars($filePath) . '" download class="bg-white text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 transition font-medium text-sm flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                    </div>
                    
                    <div class="bg-white rounded-2xl overflow-hidden shadow-2xl border-2 border-gray-200" style="height:750px;">
                        <iframe src="' . htmlspecialchars($filePath) . '#toolbar=1&navpanes=1&scrollbar=1" 
                                class="w-full h-full border-0" 
                                type="application/pdf"
                                title="PDF Document">
                        </iframe>
                    </div>
                    
                    <div class="flex gap-3 justify-center flex-wrap">
                        <button @click="fullscreen = true" class="bg-purple-600 text-white px-6 py-3 rounded-xl hover:bg-purple-700 transition font-semibold inline-flex items-center gap-2 shadow-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                            View Fullscreen
                        </button>
                        <a href="' . htmlspecialchars($filePath) . '" target="_blank" class="bg-red-600 text-white px-6 py-3 rounded-xl hover:bg-red-700 transition font-semibold inline-flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Open in New Tab
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
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Close
                            </button>
                            <iframe src="' . htmlspecialchars($filePath) . '#toolbar=0&navpanes=0&scrollbar=1&view=FitH" 
                                    class="w-full h-full border-0"
                                    type="application/pdf">
                            </iframe>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 text-center">
                        <p class="text-sm text-green-800">
                            <strong>✓ Viewing PDF directly in browser</strong> - Click "View Fullscreen" for best mobile experience
                        </p>
                    </div>
                </div>';
                
            } elseif (in_array($extension, ['docx', 'doc'])) {
                $moduleContent = '
                <div class="bg-gradient-to-br from-green-600 to-emerald-700 rounded-3xl p-12 text-white text-center shadow-2xl max-w-2xl mx-auto">
                    <div class="mb-8">
                        <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold mb-3">Word Document</h2>
                        <p class="text-green-100">' . htmlspecialchars(basename($filePath)) . '</p>
                    </div>
                    <div class="space-y-3">
                        <a href="' . htmlspecialchars($filePath) . '" target="_blank" class="block bg-white text-green-700 px-8 py-4 rounded-2xl shadow-xl hover:bg-green-50 font-bold text-lg">📄 Open</a>
                        <a href="' . htmlspecialchars($filePath) . '" download class="block bg-green-800 text-white px-8 py-4 rounded-2xl hover:bg-green-900 font-bold text-lg">⬇️ Download</a>
                    </div>
                </div>';
                
            } elseif (in_array($extension, ['html', 'htm'])) {
                $moduleContent = file_get_contents($filePath);
                
            } elseif ($extension === 'txt') {
                $moduleContent = '<div class="bg-white p-8 rounded-2xl shadow-lg border-2 max-w-4xl mx-auto"><pre class="whitespace-pre-wrap font-mono text-sm">' . htmlspecialchars(file_get_contents($filePath)) . '</pre></div>';
            }
        }
    } else {
        $moduleContent = '<div class="bg-white p-8 rounded-2xl shadow-lg border-2 max-w-4xl mx-auto">' . nl2br(htmlspecialchars($module['content'])) . '</div>';
    }
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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($module['title']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/alpinejs" defer></script>
<style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-[#D1EBEC]" x-data="{sidebarOpen:false,collapsed:true,showCompleteModal:false}">
<div class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden" x-show="sidebarOpen" @click="sidebarOpen=false" x-cloak></div>
<aside class="fixed inset-y-0 left-0 z-30 bg-white/90 shadow-lg border-r p-5 flex flex-col transition-all duration-300" :class="{'w-64':!collapsed,'w-20':collapsed,'-translate-x-full md:translate-x-0':!sidebarOpen}" x-show="sidebarOpen || window.innerWidth>=768">
<div class="flex items-center mb-10" :class="collapsed?'justify-center':'space-x-4'">
<img src="<?= htmlspecialchars($profilePic) ?>" class="w-12 h-12 rounded-full border-2 border-teal-400">
<div x-show="!collapsed" x-cloak><p class="text-xl font-bold"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></p><p class="text-sm text-gray-500">Nursing Student</p></div>
</div>
<nav class="flex-1 space-y-6">
<a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100"><span class="text-xl">🏠</span><span x-show="!collapsed" class="ml-3" x-cloak>Dashboard</span></a>
<a href="progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100"><span class="text-xl">📊</span><span x-show="!collapsed" class="ml-3" x-cloak>Progress</span></a>
<a href="quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100"><span class="text-xl">📝</span><span x-show="!collapsed" class="ml-3" x-cloak>Quizzes</span></a>
<a href="resources.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100"><span class="text-xl">📚</span><span x-show="!collapsed" class="ml-3" x-cloak>Resources</span></a>
</nav>
<button class="mt-5 p-2 rounded-lg bg-gray-100 hover:bg-gray-200 hidden md:flex justify-center" @click="collapsed=!collapsed"><svg class="h-6 w-6 transform transition-transform" :class="collapsed?'rotate-180':''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></button>
<div class="mt-auto"><a href="../actions/logout_action.php" class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100"><span class="text-xl">🚪</span><span x-show="!collapsed" class="ml-3" x-cloak>Logout</span></a></div>
</aside>
<div class="transition-all" :class="{'md:ml-64':!collapsed,'md:ml-20':collapsed}">
<header class="flex items-center justify-between p-4 bg-white/60 border-b md:hidden sticky top-0 z-20">
<button @click="sidebarOpen=true"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
<h1 class="text-lg font-semibold">Module</h1>
</header>
<main class="p-6 space-y-6 max-w-7xl mx-auto">
<a href="resources.php" class="inline-flex items-center text-teal-600 hover:text-teal-700 font-medium"><svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>Back to Resources</a>
<div class="bg-white/90 rounded-2xl shadow-lg p-8 border">
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
<div><h1 class="text-3xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($module['title']) ?></h1><?php if($module['description']):?><p class="text-gray-600"><?= htmlspecialchars($module['description']) ?></p><?php endif;?></div>
<span class="px-4 py-2 rounded-full text-sm font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords($module['status'])) ?></span>
</div>
<div><?= $moduleContent ?: '<p class="text-gray-500 italic text-center py-12">No content available.</p>' ?></div>
</div>
<div class="bg-white/90 rounded-2xl shadow-lg p-6 border flex flex-col sm:flex-row gap-4 justify-between items-center">
<p class="text-sm text-gray-600"><strong>Created:</strong> <?= date('F j, Y', strtotime($module['created_at'])) ?></p>
<div class="flex gap-3">
<?php if(strtolower($module['status'])!=='completed'):?>
<button @click="showCompleteModal=true" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-medium">✓ Complete</button>
<?php else:?>
<span class="text-green-600 font-medium">✓ Completed!</span>
<?php endif;?>
<a href="resources.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 font-medium">Back</a>
</div>
</div>
</main>
</div>
<div x-show="showCompleteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.away="showCompleteModal=false" x-cloak>
<div class="bg-white rounded-2xl max-w-md w-full p-8">
<div class="text-center"><div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4"><svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
<h3 class="text-2xl font-bold mb-2">Mark Complete?</h3><p class="text-gray-600 mb-6">Mark this module as completed?</p>
<form action="../actions/complete_module.php" method="POST" class="space-y-3">
<input type="hidden" name="module_id" value="<?= $moduleId ?>">
<input type="hidden" name="student_id" value="<?= $studentId ?>">
<button type="submit" class="w-full bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-medium">Yes</button>
<button type="button" @click="showCompleteModal=false" class="w-full bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 font-medium">Cancel</button>
</form></div>
</div>
</div>
</body>
</html>