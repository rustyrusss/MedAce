<?php
session_start();
require_once '../config/db_conn.php';

// === Access Control: only professors ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

// Fetch lessons for dropdown
$lessons = $conn->query("SELECT id, title FROM lessons ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// Flash message
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Quiz</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">

  <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-lg">
    <h1 class="text-2xl font-bold mb-6 text-gray-800">➕ Add New Quiz</h1>

    <?php if (!empty($message)): ?>
      <div class="mb-4 p-3 rounded-lg 
                  <?= strpos($message, 'success') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form action="../actions/add_quiz_action.php" method="POST" class="space-y-5">
      
      <!-- Title -->
      <div>
        <label for="title" class="block font-medium text-gray-700 mb-1">Quiz Title <span class="text-red-500">*</span></label>
        <input type="text" id="title" name="title" required
               class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="block font-medium text-gray-700 mb-1">Description</label>
        <textarea id="description" name="description" rows="3"
                  class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
      </div>

      <!-- Lesson Dropdown -->
      <div>
        <label for="lesson_id" class="block font-medium text-gray-700 mb-1">Select Lesson <span class="text-red-500">*</span></label>
        <select id="lesson_id" name="lesson_id" required
                class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
          <option value="" disabled selected>-- Choose a lesson --</option>
          <?php foreach ($lessons as $lesson): ?>
            <option value="<?= $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Content -->
      <div>
        <label for="content" class="block font-medium text-gray-700 mb-1">Instructions / Content</label>
        <textarea id="content" name="content" rows="3"
                  class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
      </div>

      <!-- Status -->
      <div>
        <label for="status" class="block font-medium text-gray-700 mb-1">Status</label>
        <select id="status" name="status"
                class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <!-- Actions -->
      <div class="flex justify-between pt-4">
        <a href="professor.php" class="px-4 py-2 bg-gray-300 rounded-xl hover:bg-gray-400 transition">⬅ Back</a>
        <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition">Save Quiz</button>
      </div>
    </form>
  </div>

</body>
</html>
