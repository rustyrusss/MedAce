<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MedAce - LogIn</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 overflow-hidden">

  <!-- Background Blobs -->
  <div class="absolute -top-20 -left-20 w-72 h-72 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
  <div class="absolute top-40 -right-20 w-80 h-80 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
  <div class="absolute -bottom-20 left-40 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>

  <!-- Medical Cross Pattern Overlay -->
  <svg class="absolute inset-0 w-full h-full opacity-10" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <pattern id="crossPattern" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M20 0v40M0 20h40" stroke="#14b8a6" stroke-width="1.2" />
      </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#crossPattern)" />
  </svg>

  <!-- Login Card -->
  <div class="relative z-10 w-full max-w-md bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl p-8 border border-gray-200">

    <!-- Logo / Header -->
    <div class="text-center mb-8">
      <div class="mx-auto w-16 h-16 flex items-center justify-center bg-gradient-to-br from-teal-500 to-blue-500 rounded-full shadow-lg mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4v5a6 6 0 0012 0V4m-6 14v2m0 0h-2m2 0h2" />
        </svg>
      </div>
      <h2 class="text-3xl font-bold text-gray-800 tracking-tight">MedAce</h2>
      <p class="text-sm text-gray-500">Log in to continue to <span class="font-semibold text-teal-600">MedAce</span></p>
    </div>

    <!-- Login Form -->
    <form action="../actions/login_action.php" method="POST" class="space-y-6">

      <!-- Email -->
    <!-- Email/Username -->
<div class="relative">
  <input type="text" id="login" name="email" required
    class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent transition" placeholder="Email or Username">
  <label for="login" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
    Email or Username
  </label>
</div>


      <!-- Password -->
      <div class="relative">
        <input type="password" id="password" name="password" required
          class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent transition" placeholder="Password">
        <label for="password" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Password
        </label>
      </div>

      <!-- Button -->
      <button type="submit"
        class="w-full bg-gradient-to-r from-teal-600 to-blue-600 hover:from-teal-700 hover:to-blue-700 text-white font-semibold py-3 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition duration-200 text-lg">
        Log In
      </button>

      <!-- Error -->
      <?php if (!empty($_SESSION['error'])): ?>
        <p class="text-red-600 text-center mt-2 text-sm">
          <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </p>
      <?php endif; ?>
    </form>

    <!-- Links -->
    <div class="mt-6 text-center space-y-2">
      <a href="#" class="text-sm text-teal-600 hover:text-teal-700 transition">Forgot Password?</a>
      <p class="text-sm text-gray-500">Don’t have an account? 
        <a href="register.php" class="text-teal-600 hover:text-teal-700 font-medium transition">Sign Up</a>
      </p>
    </div>
  </div>
</body>
</html>
