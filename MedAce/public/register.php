<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MedAce - Register</title>
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

  <!-- Register Card -->
  <div class="relative z-10 w-full max-w-lg bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl p-10 border border-gray-200">

    <!-- Header -->
    <div class="text-center mb-8">
      <div class="mx-auto w-16 h-16 flex items-center justify-center bg-gradient-to-br from-teal-500 to-blue-500 rounded-full shadow-lg mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
      </div>
      <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Create Account</h2>
      <p class="text-sm text-gray-500">Join <span class="font-semibold text-teal-600">MedAce</span> and start your journey 🚑</p>
    </div>

    <!-- Notifications -->
    <?php if (isset($_SESSION['error'])): ?>
      <div class="mb-4 p-3 text-sm text-red-700 bg-red-100 rounded-lg text-center">
        <?= $_SESSION['error']; ?>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="mb-4 p-3 text-sm text-green-700 bg-green-100 rounded-lg text-center">
        <?= $_SESSION['success']; ?>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Registration Form -->
    <form action="../actions/register_action.php" method="POST" class="space-y-6">

      <!-- First Name + Last Name -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="relative">
          <input type="text" id="firstname" name="firstname" required
            class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent"
            placeholder="First Name">
          <label for="firstname" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
            First Name
          </label>
        </div>
        <div class="relative">
          <input type="text" id="lastname" name="lastname" required
            class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent"
            placeholder="Last Name">
          <label for="lastname" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
            Last Name
          </label>
        </div>
      </div>

      <!-- Role Selection -->
      <div class="relative">
        <select id="role" name="role" required
          class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800">
          <option value="">Select Role</option>
          <option value="student">Student</option>
          <option value="professor">Professor</option>
        </select>
        <label for="role" class="absolute left-4 top-2 text-gray-500 text-sm peer-focus:text-teal-600">Role</label>
      </div>

      <!-- Year + Section (Hidden when professor is selected) -->
      <div id="studentFields" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="relative">
          <select id="year" name="year"
            class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800">
            <option value="">Select Year</option>
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3">3rd Year</option>
            <option value="4">4th Year</option>
          </select>
          <label for="year" class="absolute left-4 top-2 text-gray-500 text-sm peer-focus:text-teal-600">Year</label>
        </div>
        <div class="relative">
          <select id="section" name="section"
            class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800">
            <option value="">Select Section</option>
          </select>
          <label for="section" class="absolute left-4 top-2 text-gray-500 text-sm peer-focus:text-teal-600">Section</label>
        </div>
      </div>

      <!-- Email -->
      <div class="relative">
        <input type="email" id="email" name="email" required
          class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent"
          placeholder="Email">
        <label for="email" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Email
        </label>
      </div>

      <!-- Username -->
      <div class="relative">
        <input type="text" id="username" name="username" required
          class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent"
          placeholder="Username">
        <label for="username" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Username
        </label>
      </div>

      <!-- Password -->
      <div class="relative">
        <input type="password" id="password" name="password" required
          class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent"
          placeholder="Password">
        <label for="password" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Password
        </label>
      </div>

      <!-- Confirm Password -->
      <div class="relative">
        <input type="password" id="confirm" name="confirm" required
          class="peer w-full px-4 pt-5 pb-2 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-gray-800 placeholder-transparent"
          placeholder="Confirm Password">
        <label for="confirm" class="absolute left-4 top-2 text-gray-500 text-sm transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-sm peer-focus:text-teal-600">
          Confirm Password
        </label>
      </div>

      <button type="submit"
        class="w-full bg-gradient-to-r from-teal-600 to-blue-600 hover:from-teal-700 hover:to-blue-700 text-white font-semibold py-3 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition duration-200 text-lg">
        Sign Up
      </button>
    </form>

    <!-- Extra Links -->
    <div class="mt-6 text-center space-y-2">
      <p class="text-sm text-gray-500">Already have an account? 
        <a href="index.php" class="text-teal-600 hover:text-teal-700 font-medium">Log In</a>
      </p>
    </div>
  </div>

  <!-- Script for Dynamic Section + Role -->
  <script>
    const roleSelect = document.getElementById("role");
    const studentFields = document.getElementById("studentFields");
    const yearSelect = document.getElementById("year");
    const section = document.getElementById("section");

    const sections = {
      1: ["1A", "1B", "1C", "1D", "1E"],
      2: ["2A", "2B", "2C", "2D", "2E"],
      3: ["3A", "3B", "3C", "3D", "3E"],
      4: ["4A", "4B", "4C", "4D", "4E"],
    };

    // populate sections when year changes
    yearSelect.addEventListener("change", function () {
      const selectedYear = this.value;
      section.innerHTML = '<option value="">Select Section</option>'; // reset

      if (sections[selectedYear]) {
        sections[selectedYear].forEach(sec => {
          let option = document.createElement("option");
          option.value = sec;
          option.textContent = sec;
          section.appendChild(option);
        });
      }
    });

    // hide/show student fields depending on role
    roleSelect.addEventListener("change", function () {
      if (this.value === "professor") {
        studentFields.style.display = "none";
        yearSelect.value = "";
        section.value = "";
      } else {
        studentFields.style.display = "grid";
      }
    });
  </script>
</body>
</html>
