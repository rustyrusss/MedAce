<!-- 
====================================================
COMPLETE WORKING CHATBOT COMPONENT
Location: /medace/includes/chatbot.php
This version includes detailed console logging for debugging
====================================================
-->

<!-- Chatbot Container -->
<div id="chatbotContainer" class="fixed bottom-4 right-4 z-[9999]" style="pointer-events: auto;">
    <!-- Chat Toggle Button -->
    <button id="chatbotToggle" onclick="toggleChatbot()" class="w-14 h-14 lg:w-16 lg:h-16 bg-gradient-to-br from-primary-600 to-primary-500 text-white rounded-full shadow-2xl hover:shadow-primary-500/50 transition-all duration-300 hover:scale-110 flex items-center justify-center group" style="pointer-events: auto;">
        <i id="chatbotIcon" class="fas fa-robot text-xl lg:text-2xl group-hover:animate-bounce"></i>
    </button>

    <!-- Chat Window -->
    <div id="chatbotWindow" class="hidden absolute bottom-20 right-0 w-screen max-w-[400px] h-[600px] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200" style="pointer-events: auto;">
        <!-- Chat Header -->
        <div class="gradient-bg px-5 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-robot text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-semibold">MedAce AI Assistant</h3>
                    <p class="text-blue-100 text-xs">
                        <span class="inline-flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>
                            Online
                        </span>
                    </p>
                </div>
            </div>
            <button onclick="toggleChatbot()" class="text-white hover:bg-white/20 rounded-lg p-2 transition-colors">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Chat Messages -->
        <div id="chatMessages" class="flex-1 overflow-y-auto p-4 bg-gray-50" style="scroll-behavior: smooth;">
            <!-- Welcome Message -->
            <div class="flex items-start space-x-2 mb-4">
                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-robot text-primary-600 text-sm"></i>
                </div>
                <div class="flex-1">
                    <div class="bg-white rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                        <p class="text-gray-800 text-sm">Hi <?php echo isset($student['firstname']) ? htmlspecialchars($student['firstname']) : 'there'; ?>! 👋</p>
                        <p class="text-gray-800 text-sm mt-2">I'm your AI study assistant. I can help you with:</p>
                        <ul class="text-gray-600 text-xs mt-2 space-y-1">
                            <li>• Generate interactive flashcard quizzes</li>
                            <li>• Track your learning progress</li>
                            <li>• Answer nursing questions</li>
                            <li>• Provide study tips</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons Row -->
        <div class="flex gap-2 px-4 py-3 bg-white border-t border-gray-200">
            <button onclick="requestProgress()" class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold rounded-lg transition-colors border border-blue-200">
                <i class="fas fa-chart-line"></i>
                <span class="text-sm">Progress</span>
            </button>
            <button onclick="requestFlashcards()" class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold rounded-lg transition-colors border border-purple-200">
                <i class="fas fa-layer-group"></i>
                <span class="text-sm">Flashcards</span>
            </button>
        </div>

        <!-- Chat Input -->
        <div class="border-t border-gray-200 p-4 bg-white">
            <form id="chatForm" onsubmit="return false;">
                <div class="flex items-end space-x-2">
                    <textarea 
                        id="chatInput" 
                        rows="1"
                        placeholder="Ask me anything..."
                        class="flex-1 px-4 py-3 bg-gray-100 border-0 rounded-xl resize-none focus:ring-2 focus:ring-primary-500 focus:bg-white transition-all text-sm"
                        style="max-height: 120px;"
                        onkeydown="handleInputKeydown(event)"
                    ></textarea>
                    <button 
                        type="button"
                        onclick="sendChatMessage()"
                        class="w-12 h-12 bg-primary-600 hover:bg-primary-700 text-white rounded-xl flex items-center justify-center transition-all hover:scale-105 active:scale-95 flex-shrink-0"
                    >
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.gradient-bg {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}

#chatbotContainer,
#chatbotContainer *,
#chatbotWindow,
#chatbotWindow * {
    pointer-events: auto !important;
}

#chatbotContainer {
    z-index: 9999 !important;
}

@media (max-width: 640px) {
    #chatbotWindow {
        position: fixed;
        bottom: 0;
        right: 0;
        left: 0;
        max-width: 100%;
        height: calc(100vh - 80px);
        border-radius: 1rem 1rem 0 0;
    }
    
    #chatbotContainer {
        bottom: 1rem;
        right: 1rem;
    }
}
</style>

<script>
console.log('🤖 Chatbot component initializing...');

let chatbotOpen = false;
let availableModules = [];

// API endpoints
const CHAT_API = '../config/chatbot_integration.php';

// Toggle chatbot window
function toggleChatbot() {
    console.log('toggleChatbot called');
    const chatWindow = document.getElementById('chatbotWindow');
    const icon = document.getElementById('chatbotIcon');
    
    if (!chatWindow || !icon) {
        console.error('❌ Chatbot elements not found');
        return;
    }
    
    chatbotOpen = !chatbotOpen;
    console.log('Chatbot open:', chatbotOpen);
    
    if (chatbotOpen) {
        chatWindow.classList.remove('hidden');
        icon.classList.remove('fa-robot');
        icon.classList.add('fa-times');
        
        const input = document.getElementById('chatInput');
        if (input) {
            setTimeout(() => input.focus(), 100);
        }
        
        // Load modules if not loaded
        if (availableModules.length === 0) {
            console.log('📚 Loading modules on chatbot open...');
            loadModules();
        }
    } else {
        chatWindow.classList.add('hidden');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-robot');
    }
}

// Handle input keydown
function handleInputKeydown(event) {
    const textarea = event.target;
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendChatMessage();
    }
}

// Load modules
async function loadModules() {
    console.log('📚 loadModules() called');
    console.log('Fetching from:', CHAT_API);
    
    addMessage('Loading modules...', 'bot');
    
    try {
        console.log('Making fetch request...');
        const response = await fetch(CHAT_API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                action: 'get_modules' 
            })
        });

        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const text = await response.text();
        console.log('Raw response:', text);
        
        const result = JSON.parse(text);
        console.log('Parsed response:', result);

        if (result.success && result.modules) {
            availableModules = result.modules;
            console.log('✅ Successfully loaded', availableModules.length, 'modules');
            console.log('Modules:', availableModules);
            
            // Clear loading message
            const messages = document.getElementById('chatMessages');
            const lastMessage = messages.lastElementChild;
            if (lastMessage && lastMessage.textContent.includes('Loading modules')) {
                lastMessage.remove();
            }
        } else if (result.error) {
            console.error('❌ API Error:', result.error);
            addMessage('❌ Error: ' + result.error, 'bot');
        } else {
            console.error('❌ Unexpected response format:', result);
            addMessage('❌ Unexpected response format', 'bot');
        }
    } catch (error) {
        console.error('❌ Failed to load modules:', error);
        console.error('Error details:', error.message);
        console.error('Error stack:', error.stack);
        addMessage('❌ No modules available. Please try again later.', 'bot');
    }
}

// Request flashcards - Show module selection
function requestFlashcards() {
    console.log('🎴 requestFlashcards() called');
    console.log('Available modules count:', availableModules.length);
    
    if (availableModules.length === 0) {
        console.log('No modules loaded, calling loadModules()');
        addMessage('Loading modules...', 'bot');
        loadModules().then(() => {
            setTimeout(() => {
                if (availableModules.length > 0) {
                    showModuleSelection();
                }
            }, 1000);
        });
    } else {
        showModuleSelection();
    }
}

// Show module selection interface
function showModuleSelection() {
    console.log('📋 showModuleSelection() called');
    console.log('Displaying', availableModules.length, 'modules');
    
    const messagesDiv = document.getElementById('chatMessages');
    if (!messagesDiv) {
        console.error('❌ chatMessages div not found');
        return;
    }
    
    if (availableModules.length === 0) {
        addMessage('❌ No modules available to generate flashcards from.', 'bot');
        return;
    }
    
    const selectionDiv = document.createElement('div');
    selectionDiv.className = 'flex items-start space-x-2 mb-4';
    
    let modulesHTML = '';
    availableModules.forEach(module => {
        const statusLower = (module.status || 'pending').toLowerCase();
        const gradient = statusLower === 'completed' ? 'from-green-50 to-emerald-50' : 
                        statusLower === 'in progress' ? 'from-yellow-50 to-orange-50' : 
                        'from-blue-50 to-indigo-50';
        const border = statusLower === 'completed' ? 'border-green-200 hover:border-green-300' : 
                      statusLower === 'in progress' ? 'border-yellow-200 hover:border-yellow-300' : 
                      'border-blue-200 hover:border-blue-300';
        const icon = statusLower === 'completed' ? '✅' : 
                    statusLower === 'in progress' ? '📖' : '📘';
        
        const moduleTitle = escapeHtml(module.title || 'Untitled Module');
        
        modulesHTML += `
            <button 
                class="w-full flex items-center gap-3 p-3 bg-gradient-to-r ${gradient} hover:shadow-md rounded-lg transition-all text-left border-2 ${border} mb-2"
                onclick="startFlashcardQuiz(${module.id}, '${moduleTitle.replace(/'/g, "\\'")}')">
                <span class="text-2xl">${icon}</span>
                <div class="flex-1">
                    <span class="font-medium text-gray-900 text-sm block">${moduleTitle}</span>
                    <span class="text-xs text-gray-600">Click to start flashcard quiz</span>
                </div>
                <i class="fas fa-play-circle text-purple-600 text-xl"></i>
            </button>
        `;
    });
    
    selectionDiv.innerHTML = `
        <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
            <i class="fas fa-robot text-primary-600 text-sm"></i>
        </div>
        <div class="flex-1">
            <div class="bg-white rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                <p class="font-semibold text-gray-900 mb-3 text-sm">📚 Select a module to start your flashcard quiz:</p>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    ${modulesHTML}
                </div>
            </div>
        </div>
    `;
    
    messagesDiv.appendChild(selectionDiv);
    scrollToBottom();
}

// Start flashcard quiz
function startFlashcardQuiz(moduleId, moduleTitle) {
    console.log('🚀 startFlashcardQuiz called');
    console.log('Module ID:', moduleId);
    console.log('Module Title:', moduleTitle);
    
    addMessage(`🚀 Starting flashcard quiz for: ${moduleTitle}`, 'bot');
    
    setTimeout(() => {
        console.log('Redirecting to: flashcard_quiz.php?module_id=' + moduleId);
        window.location.href = `flashcard_quiz.php?module_id=${moduleId}`;
    }, 500);
}

// Request progress
function requestProgress() {
    console.log('📊 requestProgress() called');
    addMessage('📊 Show My Progress', 'user');
    addMessage('Progress tracking feature coming soon!', 'bot');
}

// Send chat message
function sendChatMessage() {
    console.log('💬 sendChatMessage() called');
    const input = document.getElementById('chatInput');
    if (!input) return;
    
    const message = input.value.trim();
    if (!message) return;
    
    addMessage(message, 'user');
    input.value = '';
    input.style.height = 'auto';
    
    addMessage('Chat functionality coming soon!', 'bot');
}

// Add message to chat
function addMessage(text, type) {
    const messagesDiv = document.getElementById('chatMessages');
    if (!messagesDiv) {
        console.error('❌ chatMessages div not found');
        return;
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start space-x-2 mb-4';
    
    if (type === 'bot') {
        messageDiv.innerHTML = `
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-primary-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <div class="bg-white rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                    <p class="text-gray-800 text-sm">${escapeHtml(text)}</p>
                </div>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="flex-1 flex justify-end">
                <div class="bg-primary-600 text-white rounded-2xl rounded-tr-none px-4 py-3 shadow-sm max-w-[85%]">
                    <p class="text-sm">${escapeHtml(text)}</p>
                </div>
            </div>
        `;
    }
    
    messagesDiv.appendChild(messageDiv);
    scrollToBottom();
}

// Helper: Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper: Scroll to bottom
function scrollToBottom() {
    const messagesDiv = document.getElementById('chatMessages');
    if (messagesDiv) {
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM loaded, chatbot ready');
    console.log('Chat API endpoint:', CHAT_API);
    
    // Verify elements exist
    const chatWindow = document.getElementById('chatbotWindow');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    
    console.log('Chatbot window:', chatWindow ? '✅ Found' : '❌ Missing');
    console.log('Chat messages:', chatMessages ? '✅ Found' : '❌ Missing');
    console.log('Chat input:', chatInput ? '✅ Found' : '❌ Missing');
    
    // Test module loading immediately
    console.log('Testing module loading on page load...');
    loadModules();
});

console.log('✅ Chatbot script loaded successfully');
</script>