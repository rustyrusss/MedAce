<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not logged in or not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT student_id, firstname, lastname, email, gender, profile_pic FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit();
}

// Build name
$fullName = strtoupper($user['firstname'] . ' ' . $user['lastname']);

// Default profile picture based on gender (local avatars)
function getDefaultProfilePic($gender) {
    $gender = strtolower($gender);
    if ($gender === 'male') {
        return '../assets/img/avatar_male.png';
    } elseif ($gender === 'female') {
        return '../assets/img/avatar_female.png';
    } else {
        return '../assets/img/avatar_neutral.png';
    }
}

// Use uploaded pic if exists, else fallback to avatar
$profilePic = !empty($user['profile_pic']) 
    ? "../" . htmlspecialchars($user['profile_pic']) 
    : getDefaultProfilePic($user['gender']);
?>

<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false, collapsed: false, showModal: false }">
<head>
  <meta charset="UTF-8" />
  <title>My Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    html { scroll-behavior: smooth; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen">

  <!-- Overlay for mobile -->
  <div 
    class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    style="display:none"
  ></div>

  <!-- Sidebar -->
  <aside
    class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
    :class="{
      'w-64': !collapsed,
      'w-20': collapsed,
      '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
    }"
    x-show="sidebarOpen || window.innerWidth >= 768"
    x-transition
    style="display:none"
  >
    <!-- Profile -->
    <div class="flex items-center mb-10 transition-all"
         :class="collapsed ? 'justify-center' : 'space-x-4'">
      <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
           class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
      <div x-show="!collapsed" class="flex flex-col overflow-hidden">
        <p class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($fullName) ?></p>
        <p class="text-sm text-gray-500">Nursing Student</p>
        <a href="profile_edit.php" class="text-xs mt-1 text-teal-600 hover:underline">Edit Profile</a>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6">
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Main</p>
        <a href="dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">🏠</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Dashboard</span>
        </a>
        <a href="progress.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📊</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">My Progress</span>
        </a>
      </div>
      <div>
        <p class="text-xs uppercase text-gray-400 font-semibold mb-2" x-show="!collapsed">Learning</p>
        <a href="quizzes.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📝</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Quizzes</span>
        </a>
        <a href="resources.php" class="flex items-center p-2 rounded-lg hover:bg-teal-100 transition">
          <span class="text-xl">📂</span>
          <span x-show="!collapsed" class="ml-3 font-medium text-gray-700">Resources</span>
        </a>
      </div>
    </nav>

    <!-- Collapse / Expand button -->
    <button
      class="mt-5 flex items-center justify-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition hidden md:flex"
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

  <!-- Page Content -->
  <div
    class="relative z-10 p-6 transition-all"
    :class="{
      'md:ml-64': !collapsed && window.innerWidth >= 768,
      'md:ml-20': collapsed && window.innerWidth >= 768
    }"
  >
    <!-- Top bar (Mobile) -->
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-7 w-7" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">My Profile</h1>
    </header>

    <!-- Main Profile Content -->
    <div class="max-w-6xl mx-auto mt-10 flex flex-col md:flex-row space-y-8 md:space-y-0 md:space-x-10">
      <!-- Left profile card -->
      <div class="bg-white shadow-lg rounded-xl p-8 w-full md:w-1/3 text-center">
        <div class="w-32 h-32 rounded-full mx-auto mb-4 overflow-hidden border-4 border-teal-400 bg-gray-100">
          <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="w-full h-full object-cover" />
        </div>
        <h2 class="text-xl font-bold mb-1"><?= $fullName ?></h2>
        <p class="text-gray-500 mb-6"><?= $_SESSION['role'] ?></p>

        <!-- Change Profile Picture Button -->
        <button
          @click="showModal = true"
          class="mb-4 w-full inline-block px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          Change Profile Picture
        </button>

        <a href="change_password.php" class="inline-block px-5 py-2 bg-green-500 text-white rounded hover:bg-green-600 w-full">
          Change Password
        </a>
      </div>

      <!-- Right info card -->
      <div class="bg-white shadow-lg rounded-xl p-8 w-full md:w-2/3">
        <h3 class="text-xl font-semibold mb-6">Personal Information</h3>
        <div class="space-y-5 text-gray-800">
          <div>
            <p class="text-sm text-gray-500">Student ID</p>
            <p class="font-bold"><?= htmlspecialchars($user['student_id']) ?></p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Student Name</p>
            <p class="font-bold"><?= $fullName ?></p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Email Address</p>
            <p class="font-bold"><?= htmlspecialchars($user['email']) ?></p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Gender</p>
            <p class="font-bold"><?= strtoupper(htmlspecialchars($user['gender'])) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for Upload / Remove Profile Picture -->
  <div
    class="fixed inset-0 flex items-center justify-center z-50"
    x-show="showModal"
    x-transition
    style="display:none"
  >
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>

    <!-- Modal Box -->
    <div class="bg-white rounded-2xl shadow-xl p-6 w-11/12 max-w-md z-10 relative" @click.away="showModal = false">
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold text-gray-800">Manage Profile Picture</h2>
        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">×</button>
      </div>

      <!-- Upload Section -->
      <form action="../actions/upload_profile_pic_action.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-2">Choose New Picture</label>
          <input type="file" name="profile_pic" accept="image/png, image/jpeg"
            class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:ring-2 focus:ring-blue-400 focus:outline-none" required />
        </div>

        <div class="flex justify-center space-x-4">
          <button type="button" class="px-5 py-2 bg-gray-200 rounded-lg hover:bg-gray-300" @click="showModal = false">Cancel</button>
          <button type="submit" name="upload" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Upload</button>
        </div>
      </form>

      <!-- Divider + Remove option -->
      <?php if (!empty($user['profile_pic'])): ?>
      <div class="flex items-center my-6">
        <hr class="flex-grow border-gray-300">
        <span class="mx-3 text-sm text-gray-400">OR</span>
        <hr class="flex-grow border-gray-300">
      </div>

      <!-- Remove Section -->
      <form action="../actions/remove_profile_pic_action.php" method="POST"
            onsubmit="return confirm('Are you sure you want to remove your profile picture?');">
        <div class="flex justify-center">
          <button type="submit" class="px-5 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 w-full">
            Remove Profile Picture
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
