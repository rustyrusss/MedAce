<!-- includes/sidebar.php -->
<aside class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
       :class="{
         'w-64': !collapsed,
         'w-20': collapsed,
         '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
       }"
       x-show="sidebarOpen || window.innerWidth >= 768"
       x-transition>
       
  <!-- Profile -->
  <div class="flex items-center mb-10 transition-all"
       :class="collapsed ? 'justify-center' : 'space-x-4'">
    <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
         class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
    <div x-show="!collapsed" class="flex flex-col overflow-hidden">
      <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></p>
      <p class="text-sm text-gray-500">Nursing Student</p>
      <a href="profile_edit.php" class="text-xs mt-1 text-teal-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 space-y-6">
    <div>
      <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
      <a href="dashboard.php"
         class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-teal-100 text-teal-700 font-semibold' : '' ?>">
        <span class="text-xl">🏠</span>
        <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Dashboard</span>
      </a>
      <a href="progress.php"
         class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition <?= basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'bg-teal-100 text-teal-700 font-semibold' : '' ?>">
        <span class="text-xl">📊</span>
        <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">My Progress</span>
      </a>
    </div>
    <div>
      <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Learning</p>
      <a href="quizzes.php"
         class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition <?= basename($_SERVER['PHP_SELF']) == 'quizzes.php' ? 'bg-teal-100 text-teal-700 font-semibold' : '' ?>">
        <span class="text-xl">📝</span>
        <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Quizzes</span>
      </a>
      <a href="resources.php"
         class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition <?= basename($_SERVER['PHP_SELF']) == 'resources.php' ? 'bg-teal-100 text-teal-700 font-semibold' : '' ?>">
        <span class="text-xl">📂</span>
        <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Resources</span>
      </a>
    </div>
  </nav>

  <!-- Collapse -->
  <button class="mt-5 flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition hidden md:flex"
          @click="collapsed = !collapsed">
    <svg xmlns="http://www.w3.org/2000/svg"
         class="h-6 w-6 text-gray-700 transform transition-transform"
         :class="collapsed ? 'rotate-180' : ''"
         fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
    </svg>
  </button>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="../actions/logout_action.php"
       class="flex items-center p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
      <span class="text-xl">🚪</span>
      <span x-show="!collapsed" class="ml-3 font-medium">Logout</span>
    </a>
  </div>
</aside>
