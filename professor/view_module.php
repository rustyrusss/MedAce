<?php
session_start();
require_once __DIR__ . '/../config/db_conn.php';
require_once __DIR__ . '/../includes/avatar_helper.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_modules.php");
    exit();
}

$professorId = $_SESSION['user_id'];
$moduleId = (int)$_GET['id'];

// Get professor info
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender FROM users WHERE id = ?");
$stmt->execute([$professorId]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
$profName = $prof ? $prof['firstname'] . " " . $prof['lastname'] : "Professor";
$profilePic = getProfilePicture($prof, "../");

// Fetch the module (only if it belongs to this professor)
$stmt = $conn->prepare("SELECT m.id, m.title, m.description, m.content, m.status, m.subject, m.created_at, m.updated_at FROM modules m WHERE m.id = ? AND m.professor_id = ?");
$stmt->execute([$moduleId, $professorId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    $_SESSION['error'] = "Module not found or you don't have permission to view it.";
    header("Location: manage_modules.php");
    exit();
}

// Get student progress statistics - ONLY COMPLETED COUNT
$stmt = $conn->prepare("SELECT SUM(CASE WHEN sp.status = 'Completed' THEN 1 ELSE 0 END) as completed_count FROM student_progress sp WHERE sp.module_id = ?");
$stmt->execute([$moduleId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

function convertPPTXtoPDF($pptxPath) {
    $pdfPath = str_replace(['.pptx', '.ppt'], '.pdf', $pptxPath);
    if (file_exists($pdfPath)) return $pdfPath;
    $outputDir = dirname($pptxPath);
    exec("soffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($pptxPath) . " 2>&1");
    return file_exists($pdfPath) ? $pdfPath : false;
}

$moduleContent = '';
if (!empty($module['content'])) {
    $filePath = strpos($module['content'], '../') === 0 ? $module['content'] : "../" . $module['content'];
    if (file_exists($filePath)) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['pptx', 'ppt'])) {
            $pdfPath = convertPPTXtoPDF($filePath);
            if ($pdfPath && file_exists($pdfPath)) {
                $moduleContent = '<div class="space-y-3" x-data="{ fullscreen: false }"><div class="bg-white rounded-xl overflow-hidden shadow-lg border border-gray-200"><iframe src="' . htmlspecialchars($pdfPath) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" class="pdf-viewer w-full border-0" type="application/pdf" title="PowerPoint Presentation"></iframe></div><div class="flex gap-3 justify-center flex-wrap"><button @click="fullscreen = true" class="bg-purple-600 text-white px-6 py-2.5 rounded-lg hover:bg-purple-700 transition font-semibold inline-flex items-center gap-2 shadow-sm text-sm"><i class="fas fa-expand"></i>Fullscreen</button><a href="' . htmlspecialchars($pdfPath) . '" target="_blank" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition font-semibold inline-flex items-center gap-2 text-sm"><i class="fas fa-external-link-alt"></i>New Tab</a><a href="' . htmlspecialchars($filePath) . '" download class="bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 transition font-semibold inline-flex items-center gap-2 text-sm"><i class="fas fa-download"></i>Download Original</a></div><div x-show="fullscreen" class="fixed inset-0 z-[100] bg-black" x-transition @keydown.escape.window="fullscreen = false" x-cloak><div class="relative w-full h-full"><button @click="fullscreen = false" class="absolute top-4 right-4 z-10 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium flex items-center gap-2 shadow-lg"><i class="fas fa-times"></i>Close</button><iframe src="' . htmlspecialchars($pdfPath) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" class="w-full h-full border-0" type="application/pdf"></iframe></div></div></div>';
            } else {
                $moduleContent = '<div class="max-w-2xl mx-auto"><div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl p-12 text-white text-center shadow-2xl"><div class="mb-8"><div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6"><i class="fas fa-file-powerpoint text-5xl"></i></div><h2 class="text-3xl font-bold mb-3">PowerPoint Presentation</h2><p class="text-base text-blue-100 break-all px-2">' . htmlspecialchars(basename($filePath)) . '</p></div><div class="space-y-4 max-w-md mx-auto"><a href="' . htmlspecialchars($filePath) . '" target="_blank" class="block bg-white text-blue-700 px-8 py-4 rounded-2xl shadow-xl hover:bg-blue-50 font-bold text-lg"><i class="fas fa-eye mr-2"></i>View</a><a href="' . htmlspecialchars($filePath) . '" download class="block bg-blue-800 text-white px-8 py-4 rounded-2xl hover:bg-blue-900 font-bold text-lg"><i class="fas fa-download mr-2"></i>Download</a></div></div></div>';
            }
        } elseif ($extension === 'pdf') {
            $moduleContent = '<div class="space-y-3" x-data="{ fullscreen: false }"><div class="bg-white rounded-xl overflow-hidden shadow-lg border border-gray-200"><iframe src="' . htmlspecialchars($filePath) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" class="w-full border-0 pdf-viewer" type="application/pdf" title="PDF Document"></iframe></div><div class="flex gap-3 justify-center flex-wrap"><button @click="fullscreen = true" class="bg-purple-600 text-white px-6 py-2.5 rounded-lg hover:bg-purple-700 transition font-semibold inline-flex items-center gap-2 shadow-sm text-sm"><i class="fas fa-expand"></i>Fullscreen</button><a href="' . htmlspecialchars($filePath) . '" target="_blank" class="bg-red-600 text-white px-6 py-2.5 rounded-lg hover:bg-red-700 transition font-semibold inline-flex items-center gap-2 text-sm"><i class="fas fa-external-link-alt"></i>New Tab</a><a href="' . htmlspecialchars($filePath) . '" download class="bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 transition font-semibold inline-flex items-center gap-2 text-sm"><i class="fas fa-download"></i>Download</a></div><div x-show="fullscreen" class="fixed inset-0 z-[100] bg-black" x-transition @keydown.escape.window="fullscreen = false" x-cloak><div class="relative w-full h-full"><button @click="fullscreen = false" class="absolute top-4 right-4 z-10 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium flex items-center gap-2 shadow-lg"><i class="fas fa-times"></i>Close</button><iframe src="' . htmlspecialchars($filePath) . '#view=FitH&toolbar=1&navpanes=0&scrollbar=1" class="w-full h-full border-0" type="application/pdf"></iframe></div></div></div>';
        } elseif (in_array($extension, ['docx', 'doc'])) {
            $moduleContent = '<div class="bg-gradient-to-br from-green-600 to-emerald-700 rounded-3xl p-12 text-white text-center shadow-2xl max-w-2xl mx-auto"><div class="mb-8"><div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6"><i class="fas fa-file-word text-5xl"></i></div><h2 class="text-3xl font-bold mb-3">Word Document</h2><p class="text-base text-green-100 break-all px-2">' . htmlspecialchars(basename($filePath)) . '</p></div><div class="space-y-4 max-w-md mx-auto"><a href="' . htmlspecialchars($filePath) . '" target="_blank" class="block bg-white text-green-700 px-8 py-4 rounded-2xl shadow-xl hover:bg-green-50 font-bold text-lg"><i class="fas fa-eye mr-2"></i>Open</a><a href="' . htmlspecialchars($filePath) . '" download class="block bg-green-800 text-white px-8 py-4 rounded-2xl hover:bg-green-900 font-bold text-lg"><i class="fas fa-download mr-2"></i>Download</a></div></div>';
        } elseif (in_array($extension, ['html', 'htm'])) {
            $moduleContent = '<div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">' . file_get_contents($filePath) . '</div>';
        } elseif ($extension === 'txt') {
            $moduleContent = '<div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-200 max-w-4xl mx-auto"><pre class="whitespace-pre-wrap font-mono text-sm overflow-x-auto">' . htmlspecialchars(file_get_contents($filePath)) . '</pre></div>';
        }
    } else {
        $moduleContent = '<div class="bg-red-50 border-2 border-red-200 rounded-2xl p-8 text-center max-w-2xl mx-auto"><div class="text-5xl mb-4">❌</div><h3 class="text-xl font-bold text-red-800 mb-2">File Not Found</h3><p class="text-base text-red-700 mb-4">The module file could not be found.</p><p class="text-sm text-red-600">Expected: ' . htmlspecialchars($filePath) . '</p></div>';
    }
} else {
    $moduleContent = '<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center"><i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i><p class="text-gray-500 text-lg">No content file has been uploaded for this module.</p><a href="manage_modules.php" class="inline-flex items-center mt-4 text-primary-600 hover:text-primary-700 font-medium"><i class="fas fa-edit mr-2"></i>Edit module to add content</a></div>';
}

$statusConfig = match(strtolower($module['status'] ?? 'draft')) {
    'published', 'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'fa-check-circle', 'label' => 'Published'],
    'draft' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'icon' => 'fa-pencil', 'label' => 'Draft'],
    'archived' => ['bg' => 'bg-gray-200', 'text' => 'text-gray-700', 'icon' => 'fa-archive', 'label' => 'Archived'],
    default => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-info-circle', 'label' => ucfirst($module['status'] ?? 'Unknown')]
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
                    colors: { primary: { 50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e' } }
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
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .animate-fade-in-up { animation: fadeInUp 0.6s ease-out; }
        .animate-scale-in { animation: scaleIn 0.4s ease-out; }
        .sidebar-transition { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (min-width: 1025px) {
            #sidebar { width: 5rem; }
            #sidebar.sidebar-expanded { width: 18rem; }
            #sidebar .nav-text, #sidebar .profile-info { opacity: 0; width: 0; overflow: hidden; transition: opacity 0.2s ease; }
            #sidebar.sidebar-expanded .nav-text, #sidebar.sidebar-expanded .profile-info { opacity: 1; width: auto; transition: opacity 0.3s ease 0.1s; }
            #main-content { margin-left: 5rem; }
            #main-content.content-expanded { margin-left: 18rem; }
        }
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
        .badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.025em; }
        .pdf-viewer { height: calc(100vh - 280px); min-height: 450px; max-height: 100vh; }
        @media (max-width: 1024px) { .pdf-viewer { height: calc(100vh - 250px); min-height: 380px; } }
        @media (max-width: 820px) { .pdf-viewer { height: calc(100dvh - 220px); min-height: 320px; } }
        @media (max-width: 640px) { .pdf-viewer { height: calc(100dvh - 200px); min-height: 250px; } }
        @media (max-width: 480px) { .pdf-viewer { height: calc(100dvh - 180px); min-height: 220px; } }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
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
                        <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($profName))) ?></h3>
                        <p class="text-xs text-gray-500">Professor</p>
                    </div>
                </div>
            </div>
            <div class="px-4 py-3 border-b border-gray-200 lg:hidden">
                <button onclick="closeSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600"><i class="fas fa-times text-lg"></i></button>
            </div>
            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all"><i class="fas fa-home text-gray-400 w-5 text-center flex-shrink-0"></i><span class="nav-text sidebar-transition whitespace-nowrap">Dashboard</span></a>
                <a href="manage_modules.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all"><i class="fas fa-book text-primary-600 w-5 text-center flex-shrink-0"></i><span class="nav-text sidebar-transition whitespace-nowrap">Modules</span></a>
                <a href="manage_quizzes.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all"><i class="fas fa-clipboard-list text-gray-400 w-5 text-center flex-shrink-0"></i><span class="nav-text sidebar-transition whitespace-nowrap">Quizzes</span></a>
                <a href="student_progress.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all"><i class="fas fa-chart-line text-gray-400 w-5 text-center flex-shrink-0"></i><span class="nav-text sidebar-transition whitespace-nowrap">Student Progress</span></a>
            </nav>
            <div class="px-3 py-4 border-t border-gray-200">
                <a href="../actions/logout_action.php" class="flex items-center space-x-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-all"><i class="fas fa-sign-out-alt w-5 text-center flex-shrink-0"></i><span class="nav-text sidebar-transition whitespace-nowrap">Logout</span></a>
            </div>
        </div>
    </aside>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="closeSidebar()"></div>
    <main id="main-content" class="flex-1 transition-all duration-300 main-container">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"><i class="fas fa-bars text-gray-600 text-lg"></i></button>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-base lg:text-lg font-bold text-gray-900 truncate"><?= htmlspecialchars($module['title']) ?></h1>
                        <?php if (!empty($module['subject'])): ?><p class="text-xs text-gray-500 truncate"><i class="fas fa-book-open mr-1"></i><?= htmlspecialchars($module['subject']) ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge <?= $statusConfig['bg'] ?> <?= $statusConfig['text'] ?>"><i class="fas <?= $statusConfig['icon'] ?> mr-1"></i><?= $statusConfig['label'] ?></span>
                    <a href="manage_modules.php" class="inline-flex items-center text-primary-600 hover:text-primary-700 font-medium px-3 py-1.5 rounded-lg hover:bg-primary-50 transition text-xs lg:text-sm flex-shrink-0"><i class="fas fa-arrow-left mr-1"></i><span class="hidden sm:inline">Back</span></a>
                </div>
            </div>
        </header>
        <div class="px-4 lg:px-6 py-4 lg:py-6 max-w-full">
            <!-- ONLY COMPLETED CARD REMAINS -->
            <div class="max-w-xs mb-6 animate-fade-in-up">
                <div class="stat-card bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs text-gray-500 font-medium">Completed</p><p class="text-2xl font-bold text-green-600"><?= $stats['completed_count'] ?? 0 ?></p></div>
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center"><i class="fas fa-check-circle text-green-600"></i></div>
                    </div>
                </div>
            </div>
            
            <!-- Content Preview -->
            <div class="animate-fade-in-up">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center"><i class="fas fa-eye text-primary-500 mr-2"></i>Content Preview</h3>
                <?= $moduleContent ?>
            </div>
        </div>
    </main>
</div>
<script>
let sidebarExpanded = false;
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar'), mainContent = document.getElementById('main-content'), overlay = document.getElementById('sidebar-overlay');
    sidebarExpanded = !sidebarExpanded;
    if (window.innerWidth < 1025) {
        if (sidebarExpanded) { sidebar.classList.add('sidebar-expanded'); overlay.classList.remove('hidden'); overlay.classList.add('show'); document.body.classList.add('sidebar-open'); }
        else { sidebar.classList.remove('sidebar-expanded'); overlay.classList.remove('show'); document.body.classList.remove('sidebar-open'); setTimeout(() => overlay.classList.add('hidden'), 300); }
    } else {
        if (sidebarExpanded) { sidebar.classList.add('sidebar-expanded'); mainContent.classList.add('content-expanded'); }
        else { sidebar.classList.remove('sidebar-expanded'); mainContent.classList.remove('content-expanded'); }
    }
}
function closeSidebar() { if (sidebarExpanded) toggleSidebar(); }
window.addEventListener('resize', function() {
    clearTimeout(window.resizeTimer);
    window.resizeTimer = setTimeout(function() {
        const sidebar = document.getElementById('sidebar'), mainContent = document.getElementById('main-content'), overlay = document.getElementById('sidebar-overlay');
        if (window.innerWidth >= 1025) { overlay.classList.add('hidden'); overlay.classList.remove('show'); document.body.classList.remove('sidebar-open'); if (!sidebarExpanded) { sidebar.classList.remove('sidebar-expanded'); mainContent.classList.remove('content-expanded'); } else { sidebar.classList.add('sidebar-expanded'); mainContent.classList.add('content-expanded'); } }
        else { mainContent.classList.remove('content-expanded'); if (!sidebarExpanded) { sidebar.classList.remove('sidebar-expanded'); overlay.classList.add('hidden'); overlay.classList.remove('show'); document.body.classList.remove('sidebar-open'); } }
    }, 250);
});
window.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar'), mainContent = document.getElementById('main-content');
    if (window.innerWidth >= 1025) { sidebar.classList.remove('sidebar-expanded'); mainContent.classList.remove('content-expanded'); } else { sidebar.classList.remove('sidebar-expanded'); }
    sidebarExpanded = false;
});
document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && sidebarExpanded && window.innerWidth < 1025) closeSidebar(); });
</script>
</body>
</html>