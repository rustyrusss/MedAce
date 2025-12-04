<?php
session_start();
require_once '../config/db_conn.php';
require_once '../includes/journey_fetch.php';

// ✅ Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// ✅ Fetch user info safely
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_pic, gender, section, student_id FROM users WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$studentName = $student ? $student['firstname'] . " " . $student['lastname'] : "Student";

// ✅ Default avatar logic
$defaultAvatar = "../assets/img/avatar_neutral.png";
if (!empty($student['gender'])) {
    $g = strtolower($student['gender']);
    if ($g === "male") {
        $defaultAvatar = "../assets/img/avatar_male.png";
    } elseif ($g === "female") {
        $defaultAvatar = "../assets/img/avatar_female.png";
    }
}
$profilePic = !empty($student['profile_pic']) ? "../" . $student['profile_pic'] : $defaultAvatar;

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $uploadDir = '../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    
    if (in_array($file['type'], $allowedTypes) && $file['error'] === 0) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $studentId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old profile picture if exists and is not default
            if (!empty($student['profile_pic']) && file_exists('../' . $student['profile_pic'])) {
                unlink('../' . $student['profile_pic']);
            }
            
            // Update database
            $relativePath = 'uploads/profiles/' . $filename;
            $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $updateStmt->execute([$relativePath, $studentId]);
            
            // Refresh page to show new picture
            header("Location: dashboard.php?upload=success");
            exit();
        }
    }
}

// ✅ Fetch journey data
$journeyData = getStudentJourney($conn, $studentId);
$steps = $journeyData['steps'] ?? [];
$stats = $journeyData['stats'] ?? ['completed'=>0, 'total'=>1, 'current'=>0, 'pending'=>0, 'progress'=>0];

// Also fetch quizzes separately if dashboard wants distinct quiz section
$quizzes = $journeyData['quizzes'] ?? [];

// Fetch a daily tip
$dailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    html { scroll-behavior: smooth; }
  </style>
</head>
<body class="relative min-h-screen bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100">

  <!-- Overlay for small screens -->
  <div class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden"
       x-show="sidebarOpen"
       x-transition.opacity
       @click="sidebarOpen = false"></div>

  <!-- Sidebar -->
  <aside class="fixed inset-y-0 left-0 z-30 bg-white/90 backdrop-blur-xl shadow-lg border-r border-gray-200 p-5 flex flex-col transition-all duration-300"
         :class="{
           'w-64': !collapsed,
           'w-20': collapsed,
           '-translate-x-full md:translate-x-0': !sidebarOpen && window.innerWidth < 768
         }"
         x-show="sidebarOpen || window.innerWidth >= 768"
         x-transition>
    <!-- Profile in sidebar -->
    <div class="flex items-center mb-10 transition-all"
         :class="collapsed ? 'justify-center' : 'space-x-4'">
      <img src="<?= htmlspecialchars($profilePic) ?>" alt="avatar"
           class="w-12 h-12 rounded-full border-2 border-teal-400 shadow-md object-cover bg-gray-100" />
      <div x-show="!collapsed" class="flex flex-col overflow-hidden">
        <p class="text-xl font-bold mb-1"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></p>
        <p class="text-sm text-gray-500">Nursing Student</p>
        <a href="profile_edit.php" class="text-xs mt-1 text-teal-600 hover:underline">Edit Profile Picture</a>
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

  <!-- Main content area -->
  <div class="relative z-10 transition-all"
       :class="{
         'md:ml-64': !collapsed && window.innerWidth >= 768,
         'md:ml-20': collapsed && window.innerWidth >= 768
       }">
    <header class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-xl border-b border-gray-200 shadow-md md:hidden sticky top-0 z-20">
      <button @click="sidebarOpen = true" class="text-gray-700 focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-7 w-7" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">Student Dashboard</h1>
    </header>

    <main class="p-4 sm:p-6 lg:p-8 space-y-8">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">
        Welcome, <?= htmlspecialchars(ucwords(strtolower($studentName))) ?> 👋
      </h1>

      <!-- Progress Tracker block -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-gray-800">📊 Progress Tracker</h2>
          <span class="text-sm font-medium text-gray-600">
            <?= $stats['completed'] ?>/<?= $stats['total'] ?> completed — <?= $stats['progress'] ?>%
          </span>
        </div>
        <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden mb-8">
          <div class="h-full bg-teal-600" style="width: <?= $stats['progress'] ?>%"></div>
        </div>
        <div class="flex flex-col sm:flex-row sm:space-x-4 gap-6">
          <?php foreach ($steps as $index => $step): ?>
            <?php
              $st = strtolower($step['status']);
              $isCompleted = ($st === 'completed');
              $isCurrent = ($st === 'current');
            ?>
            <div class="flex sm:flex-col items-center text-center flex-1 relative">
              <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center font-semibold
                  <?= $isCompleted
                       ? 'bg-green-500 border-green-600 text-white'
                       : ($isCurrent
                          ? 'bg-blue-500 border-blue-600 text-white ring-4 ring-blue-200'
                          : 'bg-gray-200 border-gray-400 text-gray-600') ?>">
                <?= $step['type'] === 'module' ? '📘' : '📝' ?>
              </div>
              <?php if ($index < count($steps) - 1): ?>
                <div class="hidden sm:block absolute top-6 left-full w-full h-1
                    <?= $isCompleted ? 'bg-green-500' : 'bg-gray-300' ?>"></div>
              <?php endif; ?>
              <span class="mt-3 text-sm font-medium text-gray-700"><?= htmlspecialchars($step['title']) ?></span>
              <span class="text-xs text-gray-500"><?= ucfirst($step['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quizzes Section -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">📝 Quizzes</h2>
        <?php if (empty($quizzes)): ?>
          <p class="text-gray-500">No quizzes available yet.</p>
        <?php else: ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($quizzes as $quiz): ?>
              <?php
                $st = strtolower($quiz['status']);
              ?>
              <div class="flex flex-col justify-between p-5 bg-white rounded-xl shadow hover:shadow-lg transition border border-gray-100">
                <h4 class="font-semibold text-gray-800 mb-2 text-lg"><?= htmlspecialchars($quiz['title']) ?></h4>

                <?php if (!empty($quiz['publish_time'])): ?>
                  <p class="text-sm text-gray-600 mb-1">
                    📅 Available: <?= date("F j, Y - g:i A", strtotime($quiz['publish_time'])) ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($quiz['deadline_time'])): ?>
                  <p class="text-sm text-red-600 font-medium mb-3">
                    ⏰ Deadline: <?= date("F j, Y - g:i A", strtotime($quiz['deadline_time'])) ?>
                  </p>
                <?php endif; ?>

                <span class="inline-block self-start px-3 py-1 rounded-full text-sm font-medium
                  <?= $st === 'completed'
                        ? 'bg-green-100 text-green-700'
                        : ($st === 'pending'
                          ? 'bg-yellow-100 text-yellow-700'
                          : 'bg-gray-100 text-gray-700') ?>">
                  <?= ucfirst($st) ?>
                </span>

                <div class="mt-4">
                  <a href="../member/take_quiz.php?id=<?= $quiz['id'] ?>"
                     class="block w-full text-center bg-gradient-to-r from-teal-600 to-blue-600 text-white px-4 py-2 rounded-lg shadow hover:from-teal-700 hover:to-blue-700 transition">
                    <?php
                      if ($st === 'completed') echo "Retake Quiz";
                      elseif ($st === 'failed') echo "Retry Quiz";
                      else echo "Start Quiz";
                    ?>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Daily Nursing Tip -->
      <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-lg border border-gray-200 text-center max-w-xl mx-auto">
        <h3 class="text-lg font-semibold mb-3 text-teal-700">🌟 Daily Nursing Tip</h3>
        <p class="text-gray-700 text-lg italic">"<?= htmlspecialchars($dailyTip ?: 'Stay hydrated and keep learning!') ?>"</p>
      </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/chatbot.php'; ?>

<script>
    let sidebarExpanded = false;
    let chatbotOpen = false;
    let messageHistory = [];

    // Your API configuration
    const API_CONFIG = {
        url: '../config/chatbot_integration.php', // Correct path to chatbot in config folder
        model: 'gpt-4o-nano',
        maxTokens: 1024
    };

    // Sidebar Toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebarExpanded = !sidebarExpanded;
        
        if (window.innerWidth < 1024) {
            // Mobile behavior
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            overlay.classList.toggle('hidden');
        } else {
            // Desktop behavior
            sidebar.classList.toggle('sidebar-expanded');
            sidebar.classList.toggle('sidebar-collapsed');
            
            if (sidebarExpanded) {
                mainContent.classList.remove('lg:ml-20');
                mainContent.classList.add('lg:ml-72');
            } else {
                mainContent.classList.remove('lg:ml-72');
                mainContent.classList.add('lg:ml-20');
            }
        }
    }

    function closeSidebar() {
        if (window.innerWidth < 1024 && sidebarExpanded) {
            toggleSidebar();
        }
    }

    // Upload Modal Functions
    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function previewProfilePicture(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    }

    // Profile Settings Modal Functions
    function openProfileSettingsModal() {
        document.getElementById('profileSettingsModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeProfileSettingsModal() {
        document.getElementById('profileSettingsModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        resetPasswordForm();
    }

    function switchTab(event, tabName) {
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        event.target.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    }

    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = event.currentTarget.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Password strength checker
    document.getElementById('newPassword')?.addEventListener('input', function() {
        const password = this.value;
        const strengthContainer = document.getElementById('passwordStrength');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (password.length === 0) {
            strengthContainer.classList.add('hidden');
            return;
        }
        
        strengthContainer.classList.remove('hidden');
        
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        const strengthLevels = [
            { width: '20%', color: 'bg-red-500', text: 'Very Weak', textColor: 'text-red-600' },
            { width: '40%', color: 'bg-orange-500', text: 'Weak', textColor: 'text-orange-600' },
            { width: '60%', color: 'bg-yellow-500', text: 'Fair', textColor: 'text-yellow-600' },
            { width: '80%', color: 'bg-blue-500', text: 'Good', textColor: 'text-blue-600' },
            { width: '100%', color: 'bg-green-500', text: 'Strong', textColor: 'text-green-600' }
        ];
        
        const level = strengthLevels[strength - 1] || strengthLevels[0];
        strengthBar.style.width = level.width;
        strengthBar.className = 'h-full transition-all duration-300 ' + level.color;
        strengthText.textContent = level.text;
        strengthText.className = 'text-xs lg:text-sm font-medium ' + level.textColor;
    });

    // Password match checker
    document.getElementById('confirmPassword')?.addEventListener('input', function() {
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = this.value;
        const matchIndicator = document.getElementById('passwordMatch');
        
        if (confirmPassword.length === 0) {
            matchIndicator.classList.add('hidden');
            return;
        }
        
        matchIndicator.classList.remove('hidden');
        
        if (newPassword === confirmPassword) {
            matchIndicator.textContent = '✓ Passwords match';
            matchIndicator.className = 'mt-2 text-xs lg:text-sm text-green-600 font-medium';
        } else {
            matchIndicator.textContent = '✗ Passwords do not match';
            matchIndicator.className = 'mt-2 text-xs lg:text-sm text-red-600 font-medium';
        }
    });

    // Change password form submission
    document.getElementById('changePasswordForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        const errorDiv = document.getElementById('passwordError');
        const errorText = document.getElementById('passwordErrorText');
        const successDiv = document.getElementById('passwordSuccess');
        
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        
        if (newPassword !== confirmPassword) {
            errorText.textContent = 'New passwords do not match!';
            errorDiv.classList.remove('hidden');
            return;
        }
        
        if (newPassword.length < 8) {
            errorText.textContent = 'Password must be at least 8 characters long!';
            errorDiv.classList.remove('hidden');
            return;
        }
        
        if (newPassword === currentPassword) {
            errorText.textContent = 'New password must be different from current password!';
            errorDiv.classList.remove('hidden');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('currentPassword', currentPassword);
            formData.append('newPassword', newPassword);
            
            const response = await fetch('../actions/change_password_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                successDiv.classList.remove('hidden');
                setTimeout(() => {
                    resetPasswordForm();
                    successDiv.classList.add('hidden');
                }, 3000);
            } else {
                errorText.textContent = result.message || 'Failed to change password. Please try again.';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            errorText.textContent = 'An error occurred. Please try again.';
            errorDiv.classList.remove('hidden');
        }
    });

    function resetPasswordForm() {
        document.getElementById('changePasswordForm')?.reset();
        document.getElementById('passwordStrength').classList.add('hidden');
        document.getElementById('passwordMatch').classList.add('hidden');
        document.getElementById('passwordError').classList.add('hidden');
        document.getElementById('passwordSuccess').classList.add('hidden');
    }

    // Display current date
    function displayCurrentDate() {
        const dateElement = document.getElementById('currentDate');
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        const today = new Date();
        dateElement.textContent = today.toLocaleDateString('en-US', options);
    }

   // Toggle chatbot window
function toggleChatbot() {
    const window = document.getElementById('chatbotWindow');
    const icon = document.getElementById('chatbotIcon');
    const quickActions = document.getElementById('quickActions');
    
    if (!window || !icon) {
        console.error('Chatbot elements not found!');
        return;
    }
    
    chatbotOpen = !chatbotOpen;
    
    console.log('Chatbot toggled:', chatbotOpen);
    
    if (chatbotOpen) {
        window.classList.remove('hidden');
        window.classList.add('animate-scale-in');
        icon.classList.remove('fa-robot');
        icon.classList.add('fa-times');
        if (quickActions) {
            quickActions.classList.add('hidden');
        }
        
        // Focus input after animation
        setTimeout(() => {
            const input = document.getElementById('chatInput');
            if (input) {
                input.focus();
            }
        }, 300);
    } else {
        window.classList.add('hidden');
        window.classList.remove('animate-scale-in');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-robot');
    }
}

  function handleInputKeydown(event) {
    const textarea = event.target;
    
    // Auto-resize
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    
    // Send on Enter (without Shift)
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage(event);
    }
}

    // Send message
    
async function sendMessage(event) {
    // Prevent form submission if it's an event
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    const input = document.getElementById('chatInput');
    if (!input) {
        console.error('Chat input not found!');
        return;
    }
    
    const message = input.value.trim();
    
    if (!message) {
        console.log('Empty message, not sending');
        return;
    }
    
    console.log('Sending message:', message); // Debug log
    
    // Add user message
    addMessage(message, 'user');
    input.value = '';
    input.style.height = 'auto';
    
    // Show typing indicator
    showTypingIndicator(true);
    
    // Send to API
    try {
        const response = await callChatAPI(message);
        showTypingIndicator(false);
        addMessage(response, 'bot');
    } catch (error) {
        showTypingIndicator(false);
        console.error('Chat API error:', error);
        addMessage('Sorry, I encountered an error: ' + error.message, 'bot', true);
    }
}

    // Add message to chat
    function addMessage(text, sender, isError = false) {
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) {
        console.error('Messages container not found!');
        return;
    }
    
    const messageDiv = document.createElement('div');
    
    const timestamp = new Date().toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit' 
    });
    
    if (sender === 'user') {
        messageDiv.className = 'flex items-start space-x-2 justify-end message-slide-in mb-4';
        messageDiv.innerHTML = `
            <div class="flex-1 flex flex-col items-end">
                <div class="bg-primary-600 text-white rounded-2xl rounded-tr-none px-4 py-3 shadow-sm max-w-[85%]">
                    <p class="text-sm break-words">${escapeHtml(text)}</p>
                </div>
                <span class="text-xs text-gray-500 mt-1">${timestamp}</span>
            </div>
            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
        `;
    } else {
        messageDiv.className = 'flex items-start space-x-2 message-slide-in mb-4';
        messageDiv.innerHTML = `
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-primary-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <div class="bg-white ${isError ? 'border-2 border-red-300' : ''} rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                    <p class="text-gray-800 text-sm break-words whitespace-pre-wrap">${isError ? escapeHtml(text) : formatBotMessage(text)}</p>
                </div>
                <span class="text-xs text-gray-500 mt-1 block">${timestamp}</span>
            </div>
        `;
    }
    
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    // Store in history
    messageHistory.push({ role: sender === 'user' ? 'user' : 'assistant', content: text });
}

    // Show/hide typing indicator
   function showTypingIndicator(show) {
    const indicator = document.getElementById('typingIndicator');
    const messagesContainer = document.getElementById('chatMessages');
    
    if (!indicator) {
        console.error('Typing indicator not found!');
        return;
    }
    
    if (show) {
        indicator.classList.remove('hidden');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    } else {
        indicator.classList.add('hidden');
    }
}

// Call chat API
async function callChatAPI(userMessage) {
    try {
        const response = await fetch(API_CONFIG.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: userMessage
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error Response:', errorText);
            throw new Error(`API request failed: ${response.status}`);
        }

        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        if (data.reply) {
            return data.reply;
        }
        
        throw new Error('Invalid API response format');
    } catch (error) {
        console.error('Chat API Error:', error);
        throw error;
    }
}


    // Quick question shortcuts
    function quickQuestion(question) {
        document.getElementById('chatInput').value = question;
        if (!chatbotOpen) {
            toggleChatbot();
        }
        setTimeout(() => {
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }, 300);
    }

    // Utility functions
   function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

 function formatBotMessage(text) {
    // Convert **bold** to <strong>
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Convert numbered lists
    text = text.replace(/^\d+\.\s+(.+)$/gm, '<div class="ml-2 mb-1">• $1</div>');
    
    // Convert bullet points
    text = text.replace(/^[•\-]\s+(.+)$/gm, '<div class="ml-2 mb-1">• $1</div>');
    
    return text;
}


    // Initialize
   document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing dashboard...');
    
    displayCurrentDate();
    
    // Verify chatbot elements
    const chatWindow = document.getElementById('chatbotWindow');
    const chatInput = document.getElementById('chatInput');
    const chatToggle = document.getElementById('chatbotToggle');
    
    console.log('Chatbot Window:', chatWindow ? '✅ Found' : '❌ NOT FOUND');
    console.log('Chat Input:', chatInput ? '✅ Found' : '❌ NOT FOUND');
    console.log('Chat Toggle:', chatToggle ? '✅ Found' : '❌ NOT FOUND');
    
    if (!chatWindow || !chatInput || !chatToggle) {
        console.error('⚠️ Chatbot elements missing! Check if chatbot.php is included properly.');
    } else {
        console.log('✅ Chatbot initialized successfully');
    }
    
    // Show quick actions after delay
    setTimeout(() => {
        if (!chatbotOpen) {
            const quickActions = document.getElementById('quickActions');
            if (quickActions) {
                quickActions.classList.remove('hidden');
                quickActions.classList.add('animate-fade-in-up');
            }
        }
    }, 3000);
    

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUploadModal();
                closeProfileSettingsModal();
                if (chatbotOpen) toggleChatbot();
            }
        });

        // Close modals when clicking outside
        document.getElementById('uploadModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });

        document.getElementById('profileSettingsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeProfileSettingsModal();
            }
        });

        // Prevent body scroll when modal is open
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('scroll', function(e) {
                e.stopPropagation();
            });
        });

        // Show quick actions after 3 seconds
        setTimeout(() => {
            if (!chatbotOpen) {
                document.getElementById('quickActions').classList.remove('hidden');
                document.getElementById('quickActions').classList.add('animate-fade-in-up');
            }
        }, 3000);
    });

     let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (window.innerWidth >= 1024) {
                overlay.classList.add('hidden');
                if (sidebarExpanded) {
                    mainContent.classList.remove('lg:ml-20');
                    mainContent.classList.add('lg:ml-72');
                } else {
                    mainContent.classList.remove('lg:ml-72');
                    mainContent.classList.add('lg:ml-20');
                }
            } else {
                mainContent.classList.remove('lg:ml-20', 'lg:ml-72');
                if (!sidebarExpanded) {
                    sidebar.classList.add('sidebar-collapsed');
                    sidebar.classList.remove('sidebar-expanded');
                }
            }
        }, 250);
    });

    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUploadModal();
            closeProfileSettingsModal();
            if (chatbotOpen) toggleChatbot();
        }
    });

    // Close modals when clicking outside
    document.getElementById('uploadModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeUploadModal();
        }
    });

    document.getElementById('profileSettingsModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeProfileSettingsModal();
        }
    });
    
    // Prevent zoom on double tap for iOS
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = Date.now();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);

    
</script>

</body>
</html>