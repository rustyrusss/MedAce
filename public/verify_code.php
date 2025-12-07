<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['reset_email'];
    $code = trim($_POST['code']);

    $stmt = $conn->prepare("SELECT id, reset_code, reset_expiry FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "Something went wrong.";
        header("Location: verify_code.php");
        exit();
    }

    // Check code + expiration
    if ($user['reset_code'] == $code && strtotime($user['reset_expiry']) > time()) {
        $_SESSION['reset_user_id'] = $user['id'];
        header("Location: reset_password.php");
        exit();
    }

    $_SESSION['error'] = "Invalid or expired code.";
    header("Location: verify_code.php");
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
  <title>MedAce - Verify Code</title>
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

    /* Code Input Styling */
    .code-input {
      width: 3rem;
      height: 3.5rem;
      font-size: 1.5rem;
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.1em;
    }

    @media screen and (max-width: 479px) {
      .code-input {
        width: 2.5rem;
        height: 3rem;
        font-size: 1.25rem;
      }
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
      .verify-card {
        width: 95%;
        padding: 1.25rem;
        margin: 0.5rem;
        border-radius: 1rem;
      }
    }

    @media screen and (min-width: 360px) and (max-width: 479px) {
      .verify-card {
        width: 92%;
        padding: 1.5rem;
        margin: 1rem;
        border-radius: 1.25rem;
      }
    }

    @media screen and (min-width: 480px) and (max-width: 639px) {
      .verify-card {
        width: 88%;
        max-width: 400px;
        padding: 1.75rem;
        border-radius: 1.5rem;
      }
    }

    @media screen and (min-width: 640px) and (max-width: 767px) {
      .verify-card {
        width: 420px;
        padding: 2rem;
        border-radius: 1.5rem;
      }
    }

    @media screen and (min-width: 768px) and (max-width: 1023px) {
      .verify-card {
        width: 440px;
        padding: 2.25rem;
        border-radius: 1.5rem;
      }
    }

    @media screen and (min-width: 1024px) {
      .verify-card {
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

  <!-- Verify Card -->
  <div class="verify-card relative z-10 bg-white/80 backdrop-blur-xl shadow-2xl border border-gray-200/50 animate-fade-in-up">

    <!-- Back Button -->
    <a href="forgot_password.php" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-teal-600 mb-4 transition-colors group">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back
    </a>

    <!-- Logo + Header -->
    <div class="text-center mb-6 sm:mb-8 animate-fade-in-up animate-delay-100">
      <div class="w-14 h-14 mx-auto flex items-center justify-center bg-gradient-to-br from-teal-500 to-blue-500 rounded-full shadow-lg mb-3 sm:mb-4 logo-glow">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </div>
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 tracking-tight">Check Your Email</h1>
      <p class="text-sm text-gray-500 mt-2">
        We sent a 6-digit code to <span class="font-semibold text-teal-600"><?= htmlspecialchars($_SESSION['reset_email']) ?></span>
      </p>
    </div>

    <!-- Form -->
    <form method="POST" id="verifyForm" class="space-y-5 animate-fade-in-up animate-delay-200">

      <!-- Code Input Container -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-3 text-center">Enter Verification Code</label>
        <div class="flex justify-center gap-2 sm:gap-3">
          <input type="text" maxlength="1" class="code-input input-focus-effect border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white" data-index="0" autocomplete="off">
          <input type="text" maxlength="1" class="code-input input-focus-effect border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white" data-index="1" autocomplete="off">
          <input type="text" maxlength="1" class="code-input input-focus-effect border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white" data-index="2" autocomplete="off">
          <input type="text" maxlength="1" class="code-input input-focus-effect border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white" data-index="3" autocomplete="off">
          <input type="text" maxlength="1" class="code-input input-focus-effect border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white" data-index="4" autocomplete="off">
          <input type="text" maxlength="1" class="code-input input-focus-effect border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 transition-all duration-300 bg-white/50 hover:bg-white/80 focus:bg-white" data-index="5" autocomplete="off">
        </div>
        <input type="hidden" name="code" id="codeValue">
      </div>

      <!-- Timer -->
      <div class="text-center">
        <p class="text-sm text-gray-500">
          Code expires in <span id="timer" class="font-semibold text-teal-600">10:00</span>
        </p>
      </div>

      <!-- Submit Button -->
      <button type="submit" id="submitBtn"
        class="btn-press w-full bg-gradient-to-r from-teal-600 to-blue-600 hover:from-teal-700 hover:to-blue-700 
               text-white font-semibold py-3 rounded-xl shadow-lg hover:shadow-xl transform 
               hover:-translate-y-0.5 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-teal-500/50">
        <span class="flex items-center justify-center gap-2">
          <span>Verify Code</span>
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

    <!-- Resend Link -->
    <div class="mt-6 text-center animate-fade-in-up animate-delay-300">
      <p class="text-sm text-gray-500">
        Didn't receive the code?
        <a href="forgot_password.php" class="text-teal-600 hover:text-teal-700 font-semibold transition hover:underline ml-1">
          Resend
        </a>
      </p>
    </div>

  </div>

  <!-- Footer -->
  <div class="hidden sm:block absolute bottom-4 left-0 right-0 text-center">
    <p class="text-xs text-gray-400">
      © 2024 MedAce. All rights reserved.
    </p>
  </div>

  <script>
    // Code input functionality
    const inputs = document.querySelectorAll('.code-input');
    const form = document.getElementById('verifyForm');
    const codeValue = document.getElementById('codeValue');
    
    inputs.forEach((input, index) => {
      input.addEventListener('input', (e) => {
        const value = e.target.value;
        
        if (value.length === 1 && index < inputs.length - 1) {
          inputs[index + 1].focus();
        }
        
        updateCodeValue();
      });
      
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
          inputs[index - 1].focus();
        }
      });
      
      input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').slice(0, 6);
        
        pastedData.split('').forEach((char, i) => {
          if (inputs[i]) {
            inputs[i].value = char;
          }
        });
        
        const lastFilledIndex = Math.min(pastedData.length - 1, inputs.length - 1);
        inputs[lastFilledIndex].focus();
        updateCodeValue();
      });
    });
    
    function updateCodeValue() {
      const code = Array.from(inputs).map(input => input.value).join('');
      codeValue.value = code;
    }
    
    // Timer
    let timeLeft = 600; // 10 minutes
    const timerElement = document.getElementById('timer');
    
    const countdown = setInterval(() => {
      timeLeft--;
      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
      
      if (timeLeft <= 0) {
        clearInterval(countdown);
        timerElement.textContent = 'Expired';
        timerElement.classList.add('text-red-600');
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').classList.add('opacity-50', 'cursor-not-allowed');
      }
    }, 1000);
    
    // Form submission
    form.addEventListener('submit', function(e) {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn.disabled) {
        e.preventDefault();
        return;
      }
      
      if (codeValue.value.length !== 6) {
        e.preventDefault();
        alert('Please enter all 6 digits');
        return;
      }
      
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <span class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Verifying...</span>
        </span>
      `;
    });
    
    // Auto-focus first input
    inputs[0].focus();
  </script>

</body>
</html>