<?php
session_start();
require_once '../config/db_conn.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow dean
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dean') {
    header("Location: ../public/index.php");
    exit();
}

// Stats
$totalProfessors = $conn->query("SELECT COUNT(*) FROM users WHERE role='professor'")->fetchColumn();
$totalStudents   = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalQuizzes    = $conn->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$pendingProfs    = $conn->query("SELECT COUNT(*) FROM users WHERE role='professor' AND status='pending'")->fetchColumn();

// Recent students
$recentStudents = $conn->query("
    SELECT firstname, lastname, email, created_at 
    FROM users 
    WHERE role='student' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Pending professors
$pendingProfessors = $conn->query("
    SELECT id, firstname, lastname, email, status 
    FROM users 
    WHERE role='professor' AND status='pending'
")->fetchAll(PDO::FETCH_ASSOC);

// Approved professors
$approvedProfessors = $conn->query("
    SELECT firstname, lastname, email, status, created_at 
    FROM users 
    WHERE role='professor' AND status='approved' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dean Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Dean Panel</h2>
    <nav class="space-y-4">
      <a href="dashboard.php" class="block text-gray-700 hover:text-blue-600 font-medium">🏠 Dashboard</a>
      <a href="professors.php" class="block text-gray-700 hover:text-blue-600 font-medium">👨‍🏫 Professors</a>
      <a href="students.php" class="block text-gray-700 hover:text-blue-600 font-medium">👩‍🎓 Students</a>
      <a href="quizzes.php" class="block text-gray-700 hover:text-blue-600 font-medium">📝 Quizzes</a>
      <a href="../actions/logout_action.php" class="block text-red-600 font-semibold mt-4">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main content -->
  <main class="flex-1 p-8">

    <h1 class="text-3xl font-bold mb-8 text-gray-800">Dashboard Overview</h1>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-2xl transition duration-300">
        <p class="text-gray-500 text-sm font-medium">Professors</p>
        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalProfessors; ?></p>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-2xl transition duration-300">
        <p class="text-gray-500 text-sm font-medium">Students</p>
        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalStudents; ?></p>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-2xl transition duration-300">
        <p class="text-gray-500 text-sm font-medium">Quizzes</p>
        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalQuizzes; ?></p>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-2xl transition duration-300">
        <p class="text-gray-500 text-sm font-medium">Pending Approvals</p>
        <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $pendingProfs; ?></p>
      </div>
    </div>

    <!-- Pending Professors -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4 text-gray-800">Pending Professors</h2>
      <?php if(count($pendingProfessors) > 0): ?>
      <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-100">
          <tr>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Name</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Email</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Status</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($pendingProfessors as $prof): ?>
          <tr class="border-b hover:bg-gray-50 transition">
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($prof['firstname'].' '.$prof['lastname']); ?></td>
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($prof['email']); ?></td>
            <td class="p-3">
              <span class="px-2 py-1 text-yellow-700 bg-yellow-100 rounded-full text-xs font-semibold"><?php echo strtoupper($prof['status']); ?></span>
            </td>
            <td class="p-3 space-x-2">
              <form action="professor_approval.php" method="POST" class="inline-block">
                <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                <button name="action" value="approve" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition">Approve</button>
              </form>
              <form action="professor_approval.php" method="POST" class="inline-block">
                <input type="hidden" name="professor_id" value="<?php echo $prof['id']; ?>">
                <button name="action" value="reject" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition">Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-gray-500">No pending professors.</p>
      <?php endif; ?>
    </div>

    <!-- Approved Professors -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4 text-gray-800">Approved Professors</h2>
      <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-100">
          <tr>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Name</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Email</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($approvedProfessors as $prof): ?>
          <tr class="border-b hover:bg-gray-50 transition">
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($prof['firstname'].' '.$prof['lastname']); ?></td>
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($prof['email']); ?></td>
            <td class="p-3">
              <span class="px-2 py-1 text-green-700 bg-green-100 rounded-full text-xs font-semibold"><?php echo strtoupper($prof['status']); ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Students -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <h2 class="text-xl font-semibold mb-4 text-gray-800">Recently Added Students</h2>
      <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-100">
          <tr>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Name</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Email</th>
            <th class="text-left p-3 text-sm font-semibold text-gray-700">Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($recentStudents as $student): ?>
          <tr class="border-b hover:bg-gray-50 transition">
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($student['firstname'].' '.$student['lastname']); ?></td>
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($student['email']); ?></td>
            <td class="p-3 text-gray-700"><?php echo htmlspecialchars($student['created_at']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>

</body>
</html>
