<?php
session_start();

// Retrieve old inputs
$old = $_SESSION['old'] ?? [];

function old($key, $default = '') {
    global $old;
    return isset($old[$key]) ? htmlspecialchars($old[$key]) : htmlspecialchars($default);
}

// Retrieve error/success
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;

// Sections map for JS
$sections_map = [
    1 => ["1A","1B","1C","1D","1E"],
    2 => ["2A","2B","2C","2D","2E"],
    3 => ["3A","3B","3C","3D","3E"],
    4 => ["4A","4B","4C","4D","4E"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MedAce - Register</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    html, body {
      height: 100%;
      overflow: hidden;
    }
  </style>
</head>

<body class="relative h-screen w-screen flex items-center justify-center bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100">

  <!-- Background Blobs -->
  <div class="absolute -top-32 -left-32 w-80 h-80 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30"></div>
  <div class="absolute top-20 -right-32 w-96 h-96 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-30"></div>
  <div class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-96 h-96 bg-indigo-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30"></div>

  <!-- Pattern -->
  <svg class="absolute inset-0 w-full h-full opacity-10 pointer-events-none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <pattern id="crossPattern" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M20 0v40M0 20h40" stroke="#14b8a6" stroke-width="1.2" />
      </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#crossPattern)" />
  </svg>

  <!-- Width Wrapper -->
  <div class="w-full px-4 max-w-[500px] sm:max-w-[560px] lg:max-w-[650px] xl:max-w-[700px]">

    <!-- Card -->
    <div class="bg-white/80 backdrop-blur-xl border border-gray-200 rounded-2xl shadow-xl 
                p-5 sm:p-6 lg:p-7">

      <!-- Header -->
      <div class="text-center mb-4">
        <div class="mx-auto w-12 h-12 flex items-center justify-center bg-gradient-to-br from-teal-500 to-blue-500 rounded-full shadow-md mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800">Create Account</h2>
        <p class="text-xs text-gray-500">Join <span class="font-semibold text-teal-600">MedAce</span> 🚑</p>
      </div>

      <!-- Alerts -->
      <?php if ($error): ?>
        <div class="mb-3 p-2 text-sm text-red-700 bg-red-100 rounded text-center">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-3 p-2 text-sm text-green-700 bg-green-100 rounded text-center">
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <!-- FORM -->
      <form action="../actions/register_action.php" method="POST" class="space-y-3">

        <!-- First + Last -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <input name="firstname" type="text" placeholder="First Name" required 
                 value="<?= old('firstname') ?>"
                 class="px-3 py-2 border rounded-xl text-sm">

          <input name="lastname" type="text" placeholder="Last Name" required 
                 value="<?= old('lastname') ?>"
                 class="px-3 py-2 border rounded-xl text-sm">
        </div>

        <!-- Student ID -->
        <div id="studentIdField" class="<?= (old('role') === 'student') ? '' : 'hidden' ?>">
          <input name="student_id" type="text" placeholder="Student ID"
                 value="<?= old('student_id') ?>"
                 class="w-full px-3 py-2 border rounded-xl text-sm">
        </div>

        <!-- Role -->
        <select id="role" name="role" required class="w-full px-3 py-2 border rounded-xl text-sm">
          <option value="">Select Role</option>
          <option value="student" <?= old('role') === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="professor" <?= old('role') === 'professor' ? 'selected' : '' ?>>Professor</option>
        </select>

        <!-- Year + Section -->
        <div id="studentFields"
             class="grid grid-cols-1 sm:grid-cols-2 gap-3 <?= (old('role') === 'student') ? '' : 'hidden' ?>">

          <select id="year" name="year" class="px-3 py-2 border rounded-xl text-sm">
            <option value="">Select Year</option>
            <option value="1" <?= old('year') === '1' ? 'selected' : '' ?>>1st Year</option>
            <option value="2" <?= old('year') === '2' ? 'selected' : '' ?>>2nd Year</option>
            <option value="3" <?= old('year') === '3' ? 'selected' : '' ?>>3rd Year</option>
            <option value="4" <?= old('year') === '4' ? 'selected' : '' ?>>4th Year</option>
          </select>

          <select id="section" name="section" class="px-3 py-2 border rounded-xl text-sm">
            <option value=""><?= old('section') ?: "Select Section" ?></option>
          </select>
        </div>

        <!-- Email -->
        <input type="email" name="email" required placeholder="Email"
               value="<?= old('email') ?>"
               class="w-full px-3 py-2 border rounded-xl text-sm">

        <!-- Username -->
        <input type="text" name="username" required placeholder="Username"
               value="<?= old('username') ?>"
               class="w-full px-3 py-2 border rounded-xl text-sm">

        <!-- Password -->
        <input type="password" name="password" required placeholder="Password"
               class="w-full px-3 py-2 border rounded-xl text-sm">

        <input type="password" name="confirm" required placeholder="Confirm Password"
               class="w-full px-3 py-2 border rounded-xl text-sm">

        <!-- Submit -->
        <button class="w-full bg-gradient-to-r from-teal-600 to-blue-600 text-white font-semibold py-2 rounded-xl text-sm">
          Sign Up
        </button>
      </form>

      <!-- Footer -->
      <p class="text-center text-xs text-gray-500 mt-3">
        Already have an account?
        <a href="index.php" class="text-teal-600 font-medium">Log In</a>
      </p>

    </div>
  </div>

  <!-- JS -->
  <script>
    const sectionsMap = <?= json_encode($sections_map) ?>;

    const role = document.getElementById("role");
    const studentFields = document.getElementById("studentFields");
    const studentIdField = document.getElementById("studentIdField");
    const year = document.getElementById("year");
    const section = document.getElementById("section");

    // Role toggle
    function toggleStudentFields() {
      const isStudent = role.value === "student";
      studentFields.classList.toggle("hidden", !isStudent);
      studentIdField.classList.toggle("hidden", !isStudent);
    }

    role.addEventListener("change", toggleStudentFields);

    // Populate section dropdown
    function populateSections(selectedYear, selectedSection = "") {
      section.innerHTML = "<option value=''>Select Section</option>";
      if (sectionsMap[selectedYear]) {
        sectionsMap[selectedYear].forEach(sec => {
          const opt = document.createElement("option");
          opt.value = sec;
          opt.textContent = sec;
          if (sec === selectedSection) opt.selected = true;
          section.appendChild(opt);
        });
      }
    }

    year.addEventListener("change", () => populateSections(year.value));

    // Restore previous fields on load
    document.addEventListener("DOMContentLoaded", () => {
      toggleStudentFields();

      const oldYear = "<?= old('year') ?>";
      const oldSection = "<?= old('section') ?>";

      if (oldYear) {
        populateSections(oldYear, oldSection);
      }
    });
  </script>

</body>
</html>

<?php
unset($_SESSION['old'], $_SESSION['error'], $_SESSION['success']);
?>
