<?php
/**
 * Sidebar Component
 * 
 * Required variables:
 * - $profilePic: Path to profile picture
 * - $studentName: Student's full name
 */
?>
<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 sidebar-transition sidebar-collapsed">
    <div class="flex flex-col h-full">
        <!-- Sidebar Header -->
        <div class="flex items-center justify-between px-3 lg:px-4 py-4 lg:py-5 border-b border-gray-200">
            <div class="flex items-center space-x-2 lg:space-x-3 min-w-0 flex-1">
                <div class="relative flex-shrink-0">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full object-cover ring-2 ring-primary-500 cursor-pointer" onclick="openUploadModal()">
                    <span class="absolute bottom-0 right-0 w-3 h-3 lg:w-3.5 lg:h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    <div class="profile-upload-btn" onclick="openUploadModal()">
                        <i class="fas fa-camera text-white text-xs"></i>
                    </div>
                </div>
                <div class="profile-info sidebar-transition min-w-0 flex-1">
                    <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                    <p class="text-xs text-gray-500">Student</p>
                </div>
                <!-- Settings Icon Button -->
                <button onclick="openProfileSettingsModal()" class="settings-btn sidebar-setting-btn flex-shrink-0 w-9 h-9 lg:w-10 lg:h-10 flex items-center justify-center rounded-lg bg-transparent text-gray-600 sidebar-transition" title="Profile Settings">
                    <i class="fas fa-cog text-base lg:text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Toggle Button -->
        <div class="px-3 lg:px-4 py-2 lg:py-3 border-b border-gray-200 hidden lg:block">
            <button onclick="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-600">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-2 lg:px-3 py-4 lg:py-6 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-3 text-gray-700 bg-primary-50 border-l-4 border-primary-500 rounded-r-lg font-medium transition-all">
                <i class="fas fa-home text-primary-600 w-5 text-center flex-shrink-0"></i>
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
            <a href="resources.php" class="flex items-center space-x-3 px-3 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition-all">
                <i class="fas fa-book text-gray-400 w-5 text-center flex-shrink-0"></i>
                <span class="nav-text sidebar-transition whitespace-nowrap">Resources</span>
            </a>
        </nav>

        <!-- Logout Button -->
        <div class="px-2 lg:px-3 py-3 lg:py-4 border-t border-gray-200 safe-bottom">
            <a href="../actions/logout_action.php" class="flex items-center space-x-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-all">
                <i class="fas fa-sign-out-alt w-5 text-center flex-shrink-0"></i>
                <span class="nav-text sidebar-transition whitespace-nowrap">Logout</span>
            </a>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>