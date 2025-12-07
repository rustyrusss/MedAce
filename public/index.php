<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#14b8a6">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title>MedAce - LogIn</title>
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
    /* Base Styles */
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
      min-height: 100dvh; /* Dynamic viewport height for mobile */
      overflow-x: hidden;
    }

    /* Smooth floating animation for background blobs */
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

    /* Pulse animation for logo */
    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 20px rgba(20, 184, 166, 0.3); }
      50% { box-shadow: 0 0 40px rgba(20, 184, 166, 0.5); }
    }

    .logo-glow {
      animation: pulse-glow 3s ease-in-out infinite;
    }

    /* Form input focus animation */
    .input-focus-effect {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .input-focus-effect:focus {
      transform: translateY(-1px);
    }

    /* Button press effect */
    .btn-press:active {
      transform: scale(0.98) translateY(1px);
    }

    /* Fade in animation */
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

    /* Custom scrollbar */
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

    /* Password toggle button */
    .password-toggle {
      transition: all 0.2s ease;
    }

    .password-toggle:hover {
      color: #14b8a6;
    }

    /* ============================================
       RESPONSIVE BREAKPOINTS
       ============================================ */

    /* Extra small devices (phones, less than 360px) */
    @media screen and (max-width: 359px) {
      .login-card {
        width: 95%;
        padding: 1.25rem;
        margin: 0.5rem;
        border-radius: 1rem;
      }

      .logo-container {
        width: 3rem;
        height: 3rem;
      }

      .logo-icon {
        width: 1.5rem;
        height: 1.5rem;
      }

      .title-text {
        font-size: 1.5rem;
      }

      .subtitle-text {
        font-size: 0.75rem;
      }

      .form-input {
        padding: 1rem 0.875rem 0.5rem;
        font-size: 16px; /* Prevents zoom on iOS */
      }

      .form-label {
        font-size: 0.75rem;
      }

      .submit-btn {
        padding: 0.75rem;
        font-size: 0.9375rem;
      }

      .blob-1 {
        width: 10rem;
        height: 10rem;
        top: -4rem;
        left: -4rem;
      }

      .blob-2 {
        width: 12rem;
        height: 12rem;
        top: 6rem;
        right: -5rem;
      }

      .blob-3 {
        width: 10rem;
        height: 10rem;
        bottom: -4rem;
        left: 2rem;
      }
    }

    /* Small phones (360px - 479px) */
    @media screen and (min-width: 360px) and (max-width: 479px) {
      .login-card {
        width: 92%;
        padding: 1.5rem;
        margin: 1rem;
        border-radius: 1.25rem;
      }

      .logo-container {
        width: 3.25rem;
        height: 3.25rem;
      }

      .logo-icon {
        width: 1.625rem;
        height: 1.625rem;
      }

      .title-text {
        font-size: 1.625rem;
      }

      .subtitle-text {
        font-size: 0.8125rem;
      }

      .form-input {
        padding: 1.125rem 1rem 0.5rem;
        font-size: 16px;
      }

      .submit-btn {
        padding: 0.875rem;
        font-size: 1rem;
      }

      .blob-1 {
        width: 12rem;
        height: 12rem;
        top: -5rem;
        left: -5rem;
      }

      .blob-2 {
        width: 14rem;
        height: 14rem;
        top: 8rem;
        right: -6rem;
      }

      .blob-3 {
        width: 12rem;
        height: 12rem;
        bottom: -5rem;
        left: 3rem;
      }
    }

    /* Large phones (480px - 639px) */
    @media screen and (min-width: 480px) and (max-width: 639px) {
      .login-card {
        width: 88%;
        max-width: 400px;
        padding: 1.75rem;
        border-radius: 1.5rem;
      }

      .logo-container {
        width: 3.5rem;
        height: 3.5rem;
      }

      .logo-icon {
        width: 1.75rem;
        height: 1.75rem;
      }

      .title-text {
        font-size: 1.75rem;
      }

      .subtitle-text {
        font-size: 0.875rem;
      }

      .form-input {
        padding: 1.25rem 1rem 0.5rem;
        font-size: 16px;
      }

      .submit-btn {
        padding: 0.875rem;
        font-size: 1.0625rem;
      }

      .blob-1 {
        width: 14rem;
        height: 14rem;
      }

      .blob-2 {
        width: 16rem;
        height: 16rem;
      }

      .blob-3 {
        width: 14rem;
        height: 14rem;
      }
    }

    /* Small tablets (640px - 767px) */
    @media screen and (min-width: 640px) and (max-width: 767px) {
      .login-card {
        width: 420px;
        padding: 2rem;
        border-radius: 1.5rem;
      }

      .logo-container {
        width: 3.75rem;
        height: 3.75rem;
      }

      .logo-icon {
        width: 1.875rem;
        height: 1.875rem;
      }

      .title-text {
        font-size: 1.875rem;
      }

      .submit-btn {
        padding: 0.9375rem;
        font-size: 1.0625rem;
      }

      .blob-1 {
        width: 16rem;
        height: 16rem;
      }

      .blob-2 {
        width: 18rem;
        height: 18rem;
      }

      .blob-3 {
        width: 16rem;
        height: 16rem;
      }
    }

    /* Tablets (768px - 1023px) */
    @media screen and (min-width: 768px) and (max-width: 1023px) {
      .login-card {
        width: 440px;
        padding: 2.25rem;
        border-radius: 1.5rem;
      }

      .logo-container {
        width: 4rem;
        height: 4rem;
      }

      .logo-icon {
        width: 2rem;
        height: 2rem;
      }

      .title-text {
        font-size: 2rem;
      }

      .submit-btn {
        padding: 1rem;
        font-size: 1.125rem;
      }

      .blob-1 {
        width: 18rem;
        height: 18rem;
        top: -8rem;
        left: -8rem;
      }

      .blob-2 {
        width: 20rem;
        height: 20rem;
        top: 10rem;
        right: -8rem;
      }

      .blob-3 {
        width: 18rem;
        height: 18rem;
        bottom: -8rem;
        left: 5rem;
      }
    }

    /* Small desktops (1024px - 1279px) */
    @media screen and (min-width: 1024px) and (max-width: 1279px) {
      .login-card {
        width: 460px;
        padding: 2.5rem;
        border-radius: 1.75rem;
      }

      .logo-container {
        width: 4rem;
        height: 4rem;
      }

      .title-text {
        font-size: 2rem;
      }

      .blob-1 {
        width: 20rem;
        height: 20rem;
      }

      .blob-2 {
        width: 22rem;
        height: 22rem;
      }

      .blob-3 {
        width: 20rem;
        height: 20rem;
      }
    }

    /* Large desktops (1280px+) */
    @media screen and (min-width: 1280px) {
      .login-card {
        width: 480px;
        padding: 2.5rem;
        border-radius: 2rem;
      }

      .logo-container {
        width: 4.5rem;
        height: 4.5rem;
      }

      .logo-icon {
        width: 2.25rem;
        height: 2.25rem;
      }

      .title-text {
        font-size: 2.25rem;
      }

      .submit-btn {
        padding: 1rem;
        font-size: 1.125rem;
      }

      .blob-1 {
        width: 22rem;
        height: 22rem;
        top: -10rem;
        left: -10rem;
      }

      .blob-2 {
        width: 24rem;
        height: 24rem;
        top: 12rem;
        right: -10rem;
      }

      .blob-3 {
        width: 22rem;
        height: 22rem;
        bottom: -10rem;
        left: 6rem;
      }
    }

    /* Landscape mode for phones */
    @media screen and (max-height: 500px) and (orientation: landscape) {
      body {
        padding: 1rem 0;
      }

      .login-card {
        margin: 1rem auto;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
      }

      .header-section {
        margin-bottom: 1rem;
      }

      .logo-container {
        width: 2.5rem;
        height: 2.5rem;
        margin-bottom: 0.5rem;
      }

      .title-text {
        font-size: 1.25rem;
      }

      .subtitle-text {
        font-size: 0.75rem;
        margin-top: 0.25rem;
      }

      .form-section {
        gap: 0.75rem;
      }

      .form-input {
        padding: 0.875rem 0.75rem 0.375rem;
      }

      .submit-btn {
        padding: 0.625rem;
        margin-top: 0.5rem;
      }

      .links-section {
        margin-top: 0.75rem;
      }
    }

    /* High DPI screens */
    @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
      .login-card {
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      }
    }

    /* Reduced motion preference */
    @media (prefers-reduced-motion: reduce) {
      .float-animation,
      .float-animation-delayed,
      .float-animation-delayed-2 {
        animation: none;
      }

      .animate-fade-in-up {
        animation: none;
        opacity: 1;
      }

      .logo-glow {
        animation: none;
      }

      * {
        transition-duration: 0.01ms !important;
      }
    }

    /* Dark mode support (optional) */
    @media (prefers-color-scheme: dark) {
      /* Uncomment below for dark mode support */
      /*
      body {
        background: linear-gradient(to bottom right, #0f172a, #1e293b, #0f172a);
      }

      .login-card {
        background: rgba(30, 41, 59, 0.9);
        border-color: rgba(71, 85, 105, 0.5);
      }

      .title-text {
        color: #f1f5f9;
      }

      .subtitle-text {
        color: #94a3b8;
      }

      .form-input {
        background: rgba(51, 65, 85, 0.5);
        border-color: #475569;
        color: #f1f5f9;
      }

      .form-input:focus {
        background: rgba(51, 65, 85, 0.8);
        border-color: #14b8a6;
      }
      */
    }

    /* Safe area support for notched devices */
    @supports (padding: max(0px)) {
      body {
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
        padding-bottom: env(safe-area-inset-bottom);
      }
    }

    /* Touch device optimizations */
    @media (hover: none) and (pointer: coarse) {
      .submit-btn:hover {
        transform: none;
      }

      .form-input {
        font-size: 16px; /* Prevents iOS zoom */
      }
    }
  </style>
</head>

<body class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 overflow-x-hidden">

  <!-- Background Blobs -->
  <div class="blob-1 absolute w-64 h-64 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 float-animation" 
       style="top: -8rem; left: -8rem;"></div>

  <div class="blob-2 absolute w-80 h-80 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-30 float-animation-delayed" 
       style="top: 10rem; right: -8rem;"></div>

  <div class="blob-3 absolute w-64 h-64 bg-indigo-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 float-animation-delayed-2" 
       style="bottom: -8rem; left: 5rem;"></div>

  <!-- Additional decorative blob for larger screens -->
  <div class="hidden lg:block absolute w-48 h-48 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 float-animation" 
       style="top: 50%; right: 15%; transform: translateY(-50%);"></div>

  <!-- Pattern Overlay -->
  <svg class="absolute inset-0 w-full h-full opacity-[0.07] pointer-events-none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <pattern id="crossPattern" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M20 0v40M0 20h40" stroke="#14b8a6" stroke-width="1" />
      </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#crossPattern)" />
  </svg>

  <!-- Login Card -->
  <div class="login-card relative z-10 bg-white/80 backdrop-blur-xl shadow-2xl border border-gray-200/50 animate-fade-in-up">

    <!-- Logo + Header -->
    <div class="header-section text-center mb-6 sm:mb-8 animate-fade-in-up animate-delay-100">
      <div class="logo-container mx-auto flex items-center justify-center bg-gradient-to-br from-teal-500 to-blue-500 rounded-full shadow-lg mb-3 sm:mb-4 logo-glow">
        <svg xmlns="http://www.w3.org/2000/svg" class="logo-icon text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
      </div>
      <h1 class="title-text font-bold text-gray-800 tracking-tight">MedAce</h1>
      <p class="subtitle-text text-gray-500 mt-1">
        Log in to continue to <span class="font-semibold text-teal-600">MedAce</span>
      </p>
    </div>

    <!-- Login Form -->
    <form action="../actions/login_action.php" method="POST" class="form-section space-y-4 sm:space-y-5 animate-fade-in-up animate-delay-200">

      <!-- Email Input -->
      <div class="relative group">
        <input type="text" id="login" name="email" required autocomplete="email"
          class="form-input input-focus-effect peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm 
                 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent 
                 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white"
          placeholder="Email or Username">
        <label for="login"
          class="form-label absolute left-4 top-2 text-gray-500 text-sm transition-all duration-300
                 peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 
                 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Email or Username
        </label>
        <!-- Decorative focus indicator -->
        <div class="absolute bottom-0 left-1/2 w-0 h-0.5 bg-gradient-to-r from-teal-500 to-blue-500 
                    transition-all duration-300 peer-focus:w-full peer-focus:left-0 rounded-full"></div>
      </div>

      <!-- Password Input -->
      <div class="relative group">
        <input type="password" id="password" name="password" required autocomplete="current-password"
          class="form-input input-focus-effect peer w-full px-4 pt-5 pb-2 pr-12 border border-gray-300 rounded-xl shadow-sm 
                 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent 
                 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white"
          placeholder="Password">
        <label for="password"
          class="form-label absolute left-4 top-2 text-gray-500 text-sm transition-all duration-300
                 peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 
                 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Password
        </label>
        <!-- Password toggle button -->
        <button type="button" onclick="togglePassword()" 
                class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 focus:outline-none p-1"
                aria-label="Toggle password visibility">
          <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
          </svg>
        </button>
        <!-- Decorative focus indicator -->
        <div class="absolute bottom-0 left-1/2 w-0 h-0.5 bg-gradient-to-r from-teal-500 to-blue-500 
                    transition-all duration-300 peer-focus:w-full peer-focus:left-0 rounded-full"></div>
      </div>

      <!-- Remember Me & Forgot Password Row -->
      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center cursor-pointer group">
          <input type="checkbox" name="remember" 
                 class="w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500 transition cursor-pointer">
          <span class="ml-2 text-gray-600 group-hover:text-gray-800 transition">Remember me</span>
        </label>
        <a href="forgot_password.php" class="text-teal-600 hover:text-teal-700 font-medium transition hover:underline">
  Forgot Password?
</a>

      </div>

      <!-- Login Button -->
      <button type="submit"
        class="submit-btn btn-press w-full bg-gradient-to-r from-teal-600 to-blue-600 hover:from-teal-700 hover:to-blue-700 
               text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform 
               hover:-translate-y-0.5 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-teal-500/50
               active:from-teal-800 active:to-blue-800">
        <span class="flex items-center justify-center gap-2">
          <span>Log In</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </span>
      </button>

      <!-- Error Message -->
      <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-3 sm:p-4 animate-fade-in-up">
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-red-600 text-sm">
              <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </p>
          </div>
        </div>
      <?php endif; ?>

    </form>

    <!-- Divider -->
    <div class="relative my-6 sm:my-8 animate-fade-in-up animate-delay-300">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-4 bg-white/80 text-gray-500">or continue with</span>
      </div>
    </div>

    <!-- Social Login Buttons (Optional) 
    <div class="grid grid-cols-2 gap-3 sm:gap-4 animate-fade-in-up animate-delay-300">
      <button type="button" 
              class="flex items-center justify-center gap-2 px-4 py-2.5 sm:py-3 border border-gray-300 rounded-xl 
                     hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 group">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <span class="text-gray-700 text-sm font-medium group-hover:text-gray-900">Google</span>
      </button>
      <button type="button" 
              class="flex items-center justify-center gap-2 px-4 py-2.5 sm:py-3 border border-gray-300 rounded-xl 
                     hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 group">
        <svg class="w-5 h-5" fill="#1877F2" viewBox="0 0 24 24">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
        <span class="text-gray-700 text-sm font-medium group-hover:text-gray-900">Facebook</span>
      </button>
    </div>
-->
    <!-- Sign Up Link -->
    <div class="links-section mt-6 sm:mt-8 text-center animate-fade-in-up animate-delay-300">
      <p class="text-sm text-gray-500">
        Don't have an account?
        <a href="register.php" class="text-teal-600 hover:text-teal-700 font-semibold transition hover:underline ml-1">
          Sign Up
        </a>
      </p>
    </div>

  </div>

  <!-- Footer (optional, for larger screens) -->
  <div class="hidden sm:block absolute bottom-4 left-0 right-0 text-center">
    <p class="text-xs text-gray-400">
      © 2024 MedAce. All rights reserved.
    </p>
  </div>

  <script>
    // Password visibility toggle
    function togglePassword() {
      const passwordInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      const eyeOffIcon = document.getElementById('eyeOffIcon');

      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.add('hidden');
        eyeOffIcon.classList.remove('hidden');
      } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
      }
    }

    // Form validation feedback
    document.querySelectorAll('.form-input').forEach(input => {
      input.addEventListener('invalid', function(e) {
        e.preventDefault();
        this.classList.add('border-red-500', 'ring-2', 'ring-red-500/50');
      });

      input.addEventListener('input', function() {
        this.classList.remove('border-red-500', 'ring-2', 'ring-red-500/50');
      });
    });

    // Prevent double submission
    document.querySelector('form').addEventListener('submit', function(e) {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn.disabled) {
        e.preventDefault();
        return;
      }
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <span class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Logging in...</span>
        </span>
      `;
    });

    // Handle orientation change
    window.addEventListener('orientationchange', function() {
      // Force a small delay to let the browser adjust
      setTimeout(function() {
        window.scrollTo(0, 0);
      }, 100);
    });

    // Focus management for better UX
    document.addEventListener('DOMContentLoaded', function() {
      // Focus first input on desktop
      if (window.innerWidth >= 768) {
        document.getElementById('login').focus();
      }
    });
  </script>

</body>
</html>