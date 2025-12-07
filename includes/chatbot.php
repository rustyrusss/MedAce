    <?php
    /**
     * Enhanced Responsive Chatbot Component with Tabs
     * Fully responsive design that works on all devices
     */

    // Get student name for personalization
    $chatStudentName = '';
    if (isset($student) && !empty($student['firstname'])) {
        $chatStudentName = htmlspecialchars($student['firstname']);
    } elseif (isset($_SESSION['firstname'])) {
        $chatStudentName = htmlspecialchars($_SESSION['firstname']);
    }

    // Get daily tip if available
    $chatDailyTip = '';
    if (isset($conn)) {
        try {
            $chatDailyTip = $conn->query("SELECT tip_text FROM nursing_tips ORDER BY RAND() LIMIT 1")->fetchColumn();
        } catch (Exception $e) {
            $chatDailyTip = "Stay consistent with your studies!";
        }
    }
    ?>

    <style>
    /* Enhanced Responsive Chatbot Styles */
    #chatbotContainer { 
        position: fixed; 
        bottom: 1.5rem; 
        right: 1.5rem; 
        z-index: 35;
    }

    #chatbotWindow { 
        position: fixed; 
        bottom: 6rem; 
        right: 1.5rem; 
        width: 420px; 
        max-width: calc(100vw - 3rem); 
        height: 600px;
        max-height: calc(100vh - 8rem);
        z-index: 34;
    }

    #quickActions { 
        position: fixed; 
        bottom: 6rem; 
        right: 1.5rem; 
        z-index: 33;
    }

    /* Tablet responsive */
    @media (max-width: 768px) {
        #chatbotContainer { 
            right: 1rem; 
            bottom: 1rem; 
        }
        
        #chatbotWindow { 
            right: 1rem;
            bottom: 5.5rem;
            width: 380px;
            max-width: calc(100vw - 2rem);
            height: 550px;
            max-height: calc(100vh - 7rem);
        }
        
        #quickActions { 
            right: 1rem; 
            bottom: 5.5rem; 
            max-width: calc(100vw - 2rem);
        }
    }

    /* Mobile responsive */
    @media (max-width: 640px) {
        #chatbotContainer { 
            right: 1rem; 
            bottom: 1rem;
            z-index: 35;
        }
        
        #chatbotWindow { 
            left: 0; 
            bottom: 0; 
            right: 0; 
            top: 0;
            width: 100%; 
            max-width: 100%; 
            height: 100vh;
            max-height: 100vh;
            border-radius: 0;
            margin: 0;
            z-index: 60;
        }
        
        #chatbotWindow.hidden {
            z-index: -1;
        }
        
        #quickActions { 
            right: 1rem; 
            bottom: 5rem; 
            max-width: calc(100vw - 2rem);
            z-index: 33;
        }
        
        #chatbotContainer.chat-open { 
            z-index: 61;
        }
        
        .chat-tab {
            font-size: 0.75rem;
            padding: 0.625rem 0.75rem;
        }
        
        .chat-tab i {
            display: none;
        }
    }

    /* Extra small devices */
    @media (max-width: 380px) {
        .chat-tab {
            font-size: 0.7rem;
            padding: 0.5rem 0.5rem;
        }
    }

    /* Tab Styles */
    .chat-tab {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #64748b;
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        white-space: nowrap;
    }

    .chat-tab.active { 
        color: #0ea5e9; 
    }

    .chat-tab.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: #0ea5e9;
    }

    .chat-tab:hover:not(.active) { 
        color: #475569; 
        background: #f8fafc; 
    }

    /* Flashcard Styles */
    .flashcard { 
        perspective: 1000px; 
        cursor: pointer; 
    }

    .flashcard-inner {
        position: relative;
        width: 100%;
        height: 200px;
        transition: transform 0.6s;
        transform-style: preserve-3d;
    }

    .flashcard.flipped .flashcard-inner { 
        transform: rotateY(180deg); 
    }

    .flashcard-front, .flashcard-back {
        position: absolute;
        width: 100%;
        height: 100%;
        backface-visibility: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .flashcard-front { 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        color: white; 
    }

    .flashcard-back { 
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
        color: white; 
        transform: rotateY(180deg); 
    }

    @keyframes slideInRight { 
        from { 
            opacity: 0; 
            transform: translateX(20px); 
        } 
        to { 
            opacity: 1; 
            transform: translateX(0); 
        } 
    }

    .message-slide-in { 
        animation: slideInRight 0.3s ease-out; 
    }

    @media (max-width: 380px) {
        #quickActions .quick-action-button {
            padding: 0.375rem 0.625rem;
            font-size: 0.7rem;
        }
        
        #quickActions .quick-action-button i {
            font-size: 0.7rem;
        }
    }
    </style>

    <!-- Quick Actions -->
    <div id="quickActions" class="hidden">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-3 mb-3 max-w-xs">
            <p class="text-xs text-gray-500 mb-2 font-medium">Quick Actions:</p>
            <div class="flex flex-wrap gap-2">
                <button onclick="chatbot_quickQuestion('Show my progress')" class="quick-action-button px-3 py-1.5 bg-primary-50 text-primary-700 rounded-full text-xs font-medium hover:bg-primary-100 transition-colors">
                    <i class="fas fa-chart-line text-xs"></i> My Progress
                </button>
                <button onclick="chatbot_quickQuestion('Create flashcards for nursing')" class="quick-action-button px-3 py-1.5 bg-green-50 text-green-700 rounded-full text-xs font-medium hover:bg-green-100 transition-colors">
                    <i class="fas fa-layer-group text-xs"></i> Flashcards
                </button>
                <button onclick="chatbot_quickQuestion('Give me study tips')" class="quick-action-button px-3 py-1.5 bg-amber-50 text-amber-700 rounded-full text-xs font-medium hover:bg-amber-100 transition-colors">
                    <i class="fas fa-lightbulb text-xs"></i> Study Tips
                </button>
            </div>
        </div>
    </div>

    <!-- Chatbot Toggle Button -->
    <div id="chatbotContainer">
        <button onclick="chatbot_toggle()" class="w-14 h-14 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center group">
            <i id="chatbotIcon" class="fas fa-robot text-xl group-hover:scale-110 transition-transform"></i>
        </button>
    </div>

    <!-- Enhanced Chatbot Window with Tabs -->
    <div id="chatbotWindow" class="hidden bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="chatbot-header bg-gradient-to-r from-primary-600 to-primary-700 text-white px-4 py-3 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-robot text-lg"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-sm">MedAce Assistant</h3>
                    <p class="text-xs text-primary-100 flex items-center">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-1.5 animate-pulse"></span>Online
                    </p>
                </div>
            </div>
            <button onclick="chatbot_toggle()" class="w-8 h-8 hover:bg-white/20 rounded-full flex items-center justify-center transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Tabs -->
        <div class="flex border-b border-gray-200 bg-gray-50 flex-shrink-0 overflow-x-auto">
            <button onclick="chatbot_switchTab('chat')" id="chatTab" class="chat-tab active flex-1">
                <i class="fas fa-comments mr-1"></i><span>Chat</span>
            </button>
            <button onclick="chatbot_switchTab('progress')" id="progressTab" class="chat-tab flex-1">
                <i class="fas fa-chart-line mr-1"></i><span>Progress</span>
            </button>
            <button onclick="chatbot_switchTab('flashcards')" id="flashcardsTab" class="chat-tab flex-1">
                <i class="fas fa-layer-group mr-1"></i><span>Flashcards</span>
            </button>
            <button onclick="chatbot_switchTab('tips')" id="tipsTab" class="chat-tab flex-1">
                <i class="fas fa-lightbulb mr-1"></i><span>Tips</span>
            </button>
        </div>
        
        <!-- Tab Content -->
        <div class="flex-1 overflow-hidden" style="min-height: 0;">
            <!-- Chat Tab -->
            <div id="chatContent" class="h-full flex flex-col">
                <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div class="flex items-start space-x-2 message-slide-in">
                        <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-robot text-primary-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <div class="bg-gray-100 rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                                <p class="text-gray-800 text-sm">Hi <?= $chatStudentName ?: "there" ?>! 👋 I can help you with progress tracking, create flashcards, or provide study tips. What would you like to do?</p>
                            </div>
                            <span class="text-xs text-gray-500 mt-1 block">Just now</span>
                        </div>
                    </div>
                </div>
                
                <div id="typingIndicator" class="hidden px-4 pb-2">
                    <div class="flex items-start space-x-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-robot text-primary-600 text-sm"></i>
                        </div>
                        <div class="bg-gray-100 rounded-2xl rounded-tl-none px-4 py-3">
                            <div class="flex space-x-1">
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms;"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms;"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="p-3 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                    <form onsubmit="chatbot_sendMessage(event)" class="flex items-end gap-2">
                        <textarea id="chatInput" placeholder="Type your message..." class="flex-1 px-4 py-2.5 bg-white border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none text-sm" rows="1" style="max-height: 120px;" onkeydown="chatbot_handleInputKeydown(event)"></textarea>
                        <button type="submit" class="w-10 h-10 bg-primary-600 hover:bg-primary-700 text-white rounded-xl flex items-center justify-center transition-colors flex-shrink-0">
                            <i class="fas fa-paper-plane text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Progress Tab -->
            <div id="progressContent" class="hidden h-full overflow-y-auto p-4">
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 rounded-full mb-3">
                            <i class="fas fa-chart-line text-3xl text-primary-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Your Progress</h3>
                        <p class="text-sm text-gray-600 mb-4">Track your learning journey</p>
                    </div>
                    
                    <div id="progressDataContainer">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <i class="fas fa-spinner fa-spin text-2xl text-blue-600 mb-2"></i>
                            <p class="text-sm text-gray-700">Loading your progress...</p>
                        </div>
                    </div>
                    
                    <button onclick="chatbot_generateProgressReport()" class="w-full bg-primary-600 hover:bg-primary-700 text-white py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Ask AI for Analysis
                    </button>
                </div>
            </div>
            
            <!-- Flashcards Tab -->
            <div id="flashcardsContent" class="hidden h-full overflow-y-auto p-4">
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-3">
                            <i class="fas fa-layer-group text-3xl text-green-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Flashcard Quiz</h3>
                        <p class="text-sm text-gray-600 mb-4">Select a module to start your flashcard quiz</p>
                    </div>
                    
                    <div id="flashcardModuleList" class="space-y-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <i class="fas fa-magic text-3xl text-blue-600 mb-2"></i>
                            <p class="text-sm text-gray-700">Loading modules...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tips Tab -->
            <div id="tipsContent" class="hidden h-full overflow-y-auto p-4">
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-full mb-3">
                            <i class="fas fa-lightbulb text-3xl text-amber-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Study Tips</h3>
                        <p class="text-sm text-gray-600 mb-4">Get personalized study recommendations</p>
                    </div>
                    
                    <div id="studyTipsContainer" class="space-y-3">
                        <?php if ($chatDailyTip): ?>
                        <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg p-4 border border-purple-200">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-star text-amber-500 text-lg mt-1 flex-shrink-0"></i>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-1">💡 Daily Tip</h4>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($chatDailyTip) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button onclick="chatbot_generateStudyTips()" class="w-full bg-amber-600 hover:bg-amber-700 text-white py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-brain mr-2"></i>Get Personalized Tips
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Chatbot namespace to avoid conflicts
    var ChatbotApp = {
        isOpen: false,
        currentTab: 'chat',
        availableModules: [],
        API_URL: '../config/chatbot_endpoint.php'
    };

    function chatbot_toggle() {
        const windowEl = document.getElementById('chatbotWindow');
        const icon = document.getElementById('chatbotIcon');
        const quickActions = document.getElementById('quickActions');
        const container = document.getElementById('chatbotContainer');
        
        ChatbotApp.isOpen = !ChatbotApp.isOpen;
        
        if (ChatbotApp.isOpen) {
            windowEl.classList.remove('hidden');
            icon.classList.remove('fa-robot');
            icon.classList.add('fa-times');
            if (quickActions) quickActions.classList.add('hidden');
            container.classList.add('chat-open');
            
            if (window.innerWidth <= 640) {
                document.body.style.overflow = 'hidden';
            }
            
            if (ChatbotApp.currentTab === 'flashcards') {
                chatbot_loadFlashcardModules();
            }
        } else {
            windowEl.classList.add('hidden');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-robot');
            container.classList.remove('chat-open');
            document.body.style.overflow = '';
        }
    }

    function chatbot_switchTab(tabName) {
        ['chatContent', 'progressContent', 'flashcardsContent', 'tipsContent'].forEach(id => {
            document.getElementById(id)?.classList.add('hidden');
        });
        ['chatTab', 'progressTab', 'flashcardsTab', 'tipsTab'].forEach(id => {
            document.getElementById(id)?.classList.remove('active');
        });
        
        document.getElementById(tabName + 'Content')?.classList.remove('hidden');
        document.getElementById(tabName + 'Tab')?.classList.add('active');
        ChatbotApp.currentTab = tabName;
        
        if (tabName === 'flashcards') {
            chatbot_loadFlashcardModules();
        } else if (tabName === 'progress') {
            chatbot_loadProgressData();
        }
    }

    async function chatbot_loadFlashcardModules() {
        const container = document.getElementById('flashcardModuleList');
        
        try {
            const response = await fetch(ChatbotApp.API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_modules' })
            });
            
            const data = await response.json();
            
            if (data.success && data.modules && data.modules.length > 0) {
                ChatbotApp.availableModules = data.modules;
                
                let modulesHTML = '';
                data.modules.forEach(module => {
                    const status = (module.status || 'Pending').toLowerCase();
                    const gradient = status === 'completed' ? 'from-green-50 to-emerald-50' : 
                                    status === 'in progress' ? 'from-yellow-50 to-orange-50' : 
                                    'from-blue-50 to-indigo-50';
                    const icon = status === 'completed' ? '✅' : 
                                status === 'in progress' ? '📖' : '📘';
                    
                    modulesHTML += `
                        <button onclick="chatbot_startFlashcardQuiz(${module.id}, '${chatbot_escapeHtml(module.title)}')" 
                                class="w-full flex items-center gap-3 p-3 bg-gradient-to-r ${gradient} hover:shadow-md rounded-lg transition-all text-left border border-gray-200">
                            <span class="text-2xl flex-shrink-0">${icon}</span>
                            <div class="flex-1 min-w-0">
                                <span class="font-medium text-gray-900 text-sm block truncate">${chatbot_escapeHtml(module.title)}</span>
                                <span class="text-xs text-gray-600 block truncate">${chatbot_escapeHtml(module.description || 'Start flashcard quiz')}</span>
                            </div>
                            <i class="fas fa-play-circle text-green-600 text-xl flex-shrink-0"></i>
                        </button>
                    `;
                });
                
                container.innerHTML = modulesHTML;
            } else {
                container.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                        <i class="fas fa-exclamation-circle text-2xl text-red-600 mb-2"></i>
                        <p class="text-sm text-gray-700">No modules available</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading modules:', error);
            container.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <i class="fas fa-exclamation-circle text-2xl text-red-600 mb-2"></i>
                    <p class="text-sm text-gray-700">Error loading modules</p>
                </div>
            `;
        }
    }

    function chatbot_startFlashcardQuiz(moduleId, moduleTitle) {
        window.location.href = `flashcard_quiz.php?module_id=${moduleId}`;
    }

    async function chatbot_loadProgressData() {
        const container = document.getElementById('progressDataContainer');
        
        try {
            const response = await fetch(ChatbotApp.API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_progress' })
            });
            
            const data = await response.json();
            
            if (data.success && data.progress) {
                const prog = data.progress;
                const modules = prog.modules || {};
                const quizzes = prog.quizzes || {};
                
                const totalModules = parseInt(modules.total_available) || 0;
                const completedModules = parseInt(modules.completed) || 0;
                const passedQuizzes = parseInt(quizzes.passed) || 0;
                const failedQuizzes = parseInt(quizzes.failed) || 0;
                const pendingModules = parseInt(modules.pending) || 0;
                
                const totalItems = totalModules + (parseInt(quizzes.total_available) || 0);
                const completedItems = completedModules + passedQuizzes;
                const completionRate = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
                
                container.innerHTML = `
                    <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-4 border border-primary-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                            <span class="text-2xl font-bold text-primary-600">${completionRate}%</span>
                        </div>
                        <div class="w-full bg-white rounded-full h-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-primary-600 to-primary-500 h-full transition-all duration-500" 
                                style="width: ${completionRate}%"></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div class="bg-emerald-50 rounded-lg p-3 border border-emerald-200 text-center">
                            <p class="text-2xl font-bold text-emerald-700">${completedModules}</p>
                            <p class="text-xs text-emerald-600 font-medium">Completed</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 border border-green-200 text-center">
                            <p class="text-2xl font-bold text-green-700">${passedQuizzes}</p>
                            <p class="text-xs text-green-600 font-medium">Passed</p>
                        </div>
                        <div class="bg-red-50 rounded-lg p-3 border border-red-200 text-center">
                            <p class="text-2xl font-bold text-red-700">${failedQuizzes}</p>
                            <p class="text-xs text-red-600 font-medium">Failed</p>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-3 border border-amber-200 text-center">
                            <p class="text-2xl font-bold text-amber-700">${pendingModules}</p>
                            <p class="text-xs text-amber-600 font-medium">Pending</p>
                        </div>
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                        <i class="fas fa-exclamation-circle text-2xl text-red-600 mb-2"></i>
                        <p class="text-sm text-gray-700">Unable to load progress data</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading progress:', error);
            container.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <i class="fas fa-exclamation-circle text-2xl text-red-600 mb-2"></i>
                    <p class="text-sm text-gray-700">Error loading progress</p>
                </div>
            `;
        }
    }

    async function chatbot_generateProgressReport() {
        chatbot_switchTab('chat');
        document.getElementById('chatInput').value = 'Analyze my learning progress and give me personalized recommendations.';
        await chatbot_sendMessage(new Event('submit'));
    }

    async function chatbot_generateStudyTips() {
        chatbot_switchTab('chat');
        document.getElementById('chatInput').value = 'Give me 5 personalized study tips for nursing based on my progress.';
        await chatbot_sendMessage(new Event('submit'));
    }

    function chatbot_quickQuestion(question) {
        if (!ChatbotApp.isOpen) chatbot_toggle();
        setTimeout(() => {
            document.getElementById('chatInput').value = question;
            chatbot_sendMessage(new Event('submit'));
        }, 300);
    }

    async function chatbot_sendMessage(event) {
        event.preventDefault();
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        if (!message) return;
        
        chatbot_addMessage(message, 'user');
        input.value = '';
        input.style.height = 'auto';
        chatbot_showTypingIndicator(true);
        
        try {
            const response = await fetch(ChatbotApp.API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'chat',
                    message: message,
                    task: 'chat'
                })
            });
            
            const data = await response.json();
            chatbot_showTypingIndicator(false);
            
            if (data.error) {
                chatbot_addMessage('Sorry, I encountered an error: ' + data.error, 'bot', true);
            } else if (data.reply) {
                chatbot_addMessage(data.reply, 'bot');
            } else {
                chatbot_addMessage('Sorry, I couldn\'t process your request.', 'bot', true);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            chatbot_showTypingIndicator(false);
            chatbot_addMessage('Sorry, I encountered an error. Please try again.', 'bot', true);
        }
    }

    function chatbot_addMessage(text, sender, isError = false) {
        const container = document.getElementById('chatMessages');
        const timestamp = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        const messageDiv = document.createElement('div');
        
        if (sender === 'user') {
            messageDiv.className = 'flex items-start space-x-2 justify-end message-slide-in';
            messageDiv.innerHTML = `
                <div class="flex-1 flex flex-col items-end">
                    <div class="bg-primary-600 text-white rounded-2xl rounded-tr-none px-4 py-3 shadow-sm max-w-[85%]">
                        <p class="text-sm break-words">${chatbot_escapeHtml(text)}</p>
                    </div>
                    <span class="text-xs text-gray-500 mt-1">${timestamp}</span>
                </div>
                <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
            `;
        } else {
            messageDiv.className = 'flex items-start space-x-2 message-slide-in';
            messageDiv.innerHTML = `
                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-robot text-primary-600 text-sm"></i>
                </div>
                <div class="flex-1">
                    <div class="bg-gray-100 ${isError ? 'border-2 border-red-300' : ''} rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                        <p class="text-gray-800 text-sm break-words">${chatbot_formatBotMessage(text)}</p>
                    </div>
                    <span class="text-xs text-gray-500 mt-1 block">${timestamp}</span>
                </div>
            `;
        }
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
    }

    function chatbot_showTypingIndicator(show) {
        const indicator = document.getElementById('typingIndicator');
        if (show) {
            indicator?.classList.remove('hidden');
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
        } else {
            indicator?.classList.add('hidden');
        }
    }

    function chatbot_formatBotMessage(text) {
        return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/^\d+\.\s+(.+)$/gm, '<div class="ml-2 mb-1">• $1</div>')
                .replace(/\n/g, '<br>');
    }

    function chatbot_escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function chatbot_handleInputKeydown(event) {
        const textarea = event.target;
        
        // Auto-resize textarea
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        
        // Send on Enter (without Shift)
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            chatbot_sendMessage(event);
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Show quick actions after 3 seconds
        setTimeout(() => {
            if (!ChatbotApp.isOpen) {
                document.getElementById('quickActions')?.classList.remove('hidden');
            }
        }, 3000);
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                // Adjust chatbot position on resize
                if (ChatbotApp.isOpen) {
                    const chatWindow = document.getElementById('chatbotWindow');
                    if (window.innerWidth <= 640) {
                        chatWindow.style.height = '100vh';
                    } else {
                        chatWindow.style.height = '600px';
                    }
                }
            }, 250);
        });
        
        // Close chatbot on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && ChatbotApp.isOpen) {
                chatbot_toggle();
            }
        });
        
        // Prevent body scroll when chatbot is open on mobile
        const chatbotWindow = document.getElementById('chatbotWindow');
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    const isHidden = chatbotWindow.classList.contains('hidden');
                    if (window.innerWidth <= 640) {
                        if (!isHidden) {
                            document.body.style.overflow = 'hidden';
                        } else {
                            document.body.style.overflow = '';
                        }
                    }
                }
            });
        });
        
        observer.observe(chatbotWindow, { attributes: true });
    });
    </script>