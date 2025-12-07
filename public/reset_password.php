<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['reset_user_id'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($password !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php");
        exit();
    }
    
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: reset_password.php");
        exit();
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expiry = NULL WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);

    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_email']);

    $_SESSION['success'] = "Password reset successful! Please log in.";
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#14b8a6">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title>MedAce - Reset Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
          },
        }
      }
    }
  </script>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }

    html {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      scroll-behavior: smooth;
    }

    body {
      min-height: 100vh;
      min-height: 100dvh;
      overflow-x: hidden;
    }

    .float-animation {
      animation: float 6s ease-in-out infinite;
    }

    .float-animation-delayed {
      animation: float 6s ease-in-out infinite;
      animation-delay: -2s;
    }

    .float-animation-delayed-2 {
      animation: float 6s ease-in-out infinite;
      animation-delay: -4s;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) scale(1); }
      50% { transform: translateY(-20px) scale(1.02); }
    }

    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 20px rgba(20, 184, 166, 0.3); }
      50% { box-shadow: 0 0 40px rgba(20, 184, 166, 0.5); }
    }

    .logo-glow {
      animation: pulse-glow 3s ease-in-out infinite;
    }

    .input-focus-effect {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .input-focus-effect:focus {
      transform: translateY(-1px);
    }

    .btn-press:active {
      transform: scale(0.98) translateY(1px);
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
      animation: fadeInUp 0.6s ease-out forwards;
    }

    .animate-delay-100 { animation-delay: 0.1s; opacity: 0; }
    .animate-delay-200 { animation-delay: 0.2s; opacity: 0; }
    .animate-delay-300 { animation-delay: 0.3s; opacity: 0; }

    .password-toggle {
      transition: all 0.2s ease;
    }

    .password-toggle:hover {
      color: #14b8a6;
    }

    /* Password strength indicator */
    .strength-bar {
      height: 4px;
      border-radius: 2px;
      transition: all 0.3s ease;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(20, 184, 166, 0.3);
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: rgba(20, 184, 166, 0.5);
    }

    @media screen and (max-width: 359px) {
      .reset-card {
        width: 95%;
        padding: 1.25rem;
        margin: 0.5rem;
        border-radius: 1rem;
      }
    }

    @media screen and (min-width: 360px) and (max-width: 479px) {
      .reset-card {
        width: 92%;
        padding: 1.5rem;
        margin: 1rem;
        border-radius: 1.25rem;
      }
    }

    @media screen and (min-width: 480px) and (max-width: 639px) {
      .reset-card {
        width: 88%;
        max-width: 400px;
        padding: 1.75rem;
        border-radius: 1.5rem;
      }
    }

    @media screen and (min-width: 640px) and (max-width: 767px) {
      .reset-card {
        width: 420px;
        padding: 2rem;
        border-radius: 1.5rem;
      }
    }

    @media screen and (min-width: 768px) and (max-width: 1023px) {
      .reset-card {
        width: 440px;
        padding: 2.25rem;
        border-radius: 1.5rem;
      }
    }

    @media screen and (min-width: 1024px) {
      .reset-card {
        width: 480px;
        padding: 2.5rem;
        border-radius: 2rem;
      }
    }

    @supports (padding: max(0px)) {
      body {
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
        padding-bottom: env(safe-area-inset-bottom);
      }
    }
  </style>
</head>

<body class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 overflow-x-hidden">

  <!-- Background Blobs -->
  <div class="absolute w-64 h-64 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 float-animation" 
       style="top: -8rem; left: -8rem;"></div>
  <div class="absolute w-80 h-80 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-30 float-animation-delayed" 
       style="top: 10rem; right: -8rem;"></div>
  <div class="absolute w-64 h-64 bg-indigo-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 float-animation-delayed-2" 
       style="bottom: -8rem; left: 5rem;"></div>

  <!-- Pattern Overlay -->
  <svg class="absolute inset-0 w-full h-full opacity-[0.07] pointer-events-none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <pattern id="crossPattern" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M20 0v40M0 20h40" stroke="#14b8a6" stroke-width="1" />
      </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#crossPattern)" />
  </svg>

  <!-- Reset Card -->
  <div class="reset-card relative z-10 bg-white/80 backdrop-blur-xl shadow-2xl border border-gray-200/50 animate-fade-in-up">

    <!-- Logo + Header -->
    <div class="text-center mb-6 sm:mb-8 animate-fade-in-up animate-delay-100">
      <div class="w-14 h-14 mx-auto flex items-center justify-center bg-gradient-to-br from-teal-500 to-blue-500 rounded-full shadow-lg mb-3 sm:mb-4 logo-glow">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 tracking-tight">Create New Password</h1>
      <p class="text-sm text-gray-500 mt-2">
        Your new password must be different from previously used passwords.
      </p>
    </div>

    <!-- Form -->
    <form method="POST" id="resetForm" class="space-y-5 animate-fade-in-up animate-delay-200">

      <!-- New Password Input -->
      <div class="relative group">
        <input type="password" id="password" name="password" required autocomplete="new-password"
          class="input-focus-effect peer w-full px-4 pt-5 pb-2 pr-12 border border-gray-300 rounded-xl shadow-sm 
                 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent 
                 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white"
          placeholder="New Password">
        <label for="password"
          class="absolute left-4 top-2 text-gray-500 text-sm transition-all duration-300
                 peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 
                 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          New Password
        </label>
        <button type="button" onclick="togglePassword('password')" 
                class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 focus:outline-none p-1"
                aria-label="Toggle password visibility">
          <svg id="eyeIcon1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          <svg id="eyeOffIcon1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
          </svg>
        </button>
        <div class="absolute bottom-0 left-1/2 w-0 h-0.5 bg-gradient-to-r from-teal-500 to-blue-500 
                    transition-all duration-300 peer-focus:w-full peer-focus:left-0 rounded-full"></div>
      </div>

      <!-- Password Strength Indicator -->
      <div id="strengthIndicator" class="hidden">
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs text-gray-600">Password strength:</span>
          <span id="strengthText" class="text-xs font-semibold"></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-1 overflow-hidden">
          <div id="strengthBar" class="strength-bar bg-gray-400 w-0"></div>
        </div>
      </div>

      <!-- Confirm Password Input -->
      <div class="relative group">
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password"
          class="input-focus-effect peer w-full px-4 pt-5 pb-2 pr-12 border border-gray-300 rounded-xl shadow-sm 
                 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent 
                 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white"
          placeholder="Confirm Password">
        <label for="confirm_password"
          class="absolute left-4 top-2 text-gray-500 text-sm transition-all duration-300
                 peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 
                 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Confirm Password
        </label>
        <button type="button" onclick="togglePassword('confirm_password')" 
                class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 focus:outline-none p-1"
                aria-label="Toggle password visibility">
          <svg id="eyeIcon2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          <svg id="eyeOffIcon2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
          </svg>
        </button>
        <div class="absolute bottom-0 left-1/2 w-0 h-0.5 bg-gradient-to-r from-teal-500 to-blue-500 
                    transition-all duration-300 peer-focus:w-full peer-focus:left-0 rounded-full"></div>
      </div>

      <!-- Password Requirements -->
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 sm:p-4">
        <p class="text-xs font-semibold text-blue-800 mb-2">Password must contain:</p>
        <ul class="space-y-1 text-xs text-blue-700">
          <li class="flex items-center gap-2">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            At least 8 characters
          </li>
          <li class="flex items-center gap-2">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Both passwords must match
          </li>
        </ul>
      </div>

      <!-- Submit Button -->
      <button type="submit"
        class="btn-press w-full bg-gradient-to-r from-teal-600 to-blue-600 hover:from-teal-700 hover:to-blue-700 
               text-white font-semibold py-3 rounded-xl shadow-lg hover:shadow-xl transform 
               hover:-translate-y-0.5 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-teal-500/50">
        <span class="flex items-center justify-center gap-2">
          <span>Reset Password</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </span>
      </button>

      <!-- Error Message -->
      <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-3 sm:p-4 animate-fade-in-up">
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-red-600 text-sm">
              <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </p>
          </div>
        </div>
      <?php endif; ?>

    </form>

  </div>

  <!-- Footer -->
  <div class="hidden sm:block absolute bottom-4 left-0 right-0 text-center">
    <p class="text-xs text-gray-400">
      © 2024 MedAce. All rights reserved.
    </p>
  </div>

  <script>
    function togglePassword(fieldId) {
      const field = document.getElementById(fieldId);
      const num = fieldId === 'password' ? '1' : '2';
      const eyeIcon = document.getElementById('eyeIcon' + num);
      const eyeOffIcon = document.getElementById('eyeOffIcon' + num);

      if (field.type === 'password') {
        field.type = 'text';
        eyeIcon.classList.add('hidden');
        eyeOffIcon.classList.remove('hidden');
      } else {
        field.type = 'password';
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
      }
    }

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('strengthIndicator');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      
      if (password.length === 0) {
        strengthIndicator.classList.add('hidden');
        return;
      }
      
      strengthIndicator.classList.remove('hidden');
      
      let strength = 0;
      if (password.length >= 8) strength++;
      if (password.length >= 12) strength++;
      if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
      if (/\d/.test(password)) strength++;
      if (/[^a-zA-Z0-9]/.test(password)) strength++;
      
      const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-lime-500', 'bg-green-500'];
      const texts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
      const widths = ['20%', '40%', '60%', '80%', '100%'];
      
      strengthBar.className = 'strength-bar ' + colors[strength - 1];
      strengthBar.style.width = widths[strength - 1];
      strengthText.textContent = texts[strength - 1];
      strengthText.className = 'text-xs font-semibold ' + colors[strength - 1].replace('bg-', 'text-');
    });

    // Form validation
    document.getElementById('resetForm').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const submitBtn = this.querySelector('button[type="submit"]');
      
      if (submitBtn.disabled) {
        e.preventDefault();
        return;
      }
      
      if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return;
      }
      
      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return;
      }
      
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <span class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Resetting...</span>
        </span>
      `;
    });
  </script>

</body>
</html>