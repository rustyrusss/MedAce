<?php
/**
 * Chatbot Component - Fixed Version (No Conflicts)
 * Works with dashboard.php without function/variable conflicts
 * Include this file in dashboard.php or other pages
 */

// Get student name for personalization
$chatStudentName = '';
if (isset($student) && !empty($student['firstname'])) {
    $chatStudentName = htmlspecialchars($student['firstname']);
} elseif (isset($_SESSION['firstname'])) {
    $chatStudentName = htmlspecialchars($_SESSION['firstname']);
}
?>

<!-- Chatbot Container -->
<div id="chatbotContainer" class="fixed bottom-4 right-4 z-50 sm:bottom-6 sm:right-6">
    
    <!-- Quick Action Buttons (shown when chatbot is closed) -->
    <div id="quickActions" class="hidden mb-3 space-y-2">
        <button onclick="chatQuickQuestion('Give me a study tip for nursing')" 
                class="block w-full bg-white text-gray-700 px-4 py-2 rounded-lg shadow-lg text-sm hover:bg-gray-50 transition-all border border-gray-200 text-left">
            💡 Study Tips
        </button>
        <button onclick="chatOpenProgress()" 
                class="block w-full bg-white text-gray-700 px-4 py-2 rounded-lg shadow-lg text-sm hover:bg-gray-50 transition-all border border-gray-200 text-left">
            📊 Check Progress
        </button>
    </div>
    
    <!-- Toggle Button -->
    <button id="chatbotToggle" onclick="toggleChatbot()" 
            class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-r from-primary-600 to-primary-500 rounded-full shadow-lg flex items-center justify-center hover:from-primary-700 hover:to-primary-600 transition-all transform hover:scale-105">
        <i id="chatbotIcon" class="fas fa-robot text-white text-xl sm:text-2xl"></i>
    </button>
</div>

<!-- Chatbot Window -->
<div id="chatbotWindow" class="hidden fixed bottom-20 right-4 sm:bottom-24 sm:right-6 w-[calc(100vw-2rem)] sm:w-96 max-w-md bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden z-50">
    
    <!-- Header -->
    <div class="gradient-bg px-4 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <i class="fas fa-robot text-white text-lg"></i>
            </div>
            <div>
                <h3 class="text-white font-semibold text-sm">MedAce AI Assistant</h3>
                <p class="text-blue-100 text-xs flex items-center">
                    <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>
                    Online
                </p>
            </div>
        </div>
        <button onclick="toggleChatbot()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Action Buttons -->
    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex gap-2 overflow-x-auto">
        <button onclick="chatOpenProgress()" class="flex-shrink-0 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium hover:bg-blue-200 transition-colors flex items-center gap-1">
            <i class="fas fa-chart-line"></i>
            My Progress
        </button>
        <button onclick="chatRequestFlashcards()" class="flex-shrink-0 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-full text-xs font-medium hover:bg-purple-200 transition-colors flex items-center gap-1">
            <i class="fas fa-layer-group"></i>
            Flashcards
        </button>
        <button onclick="chatQuickQuestion('Give me a nursing study tip')" class="flex-shrink-0 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-medium hover:bg-green-200 transition-colors flex items-center gap-1">
            <i class="fas fa-lightbulb"></i>
            Study Tips
        </button>
    </div>
    
    <!-- Messages Container -->
    <div id="chatMessages" class="h-72 sm:h-80 overflow-y-auto p-4 space-y-4 bg-gray-50">
        <!-- Welcome Message -->
        <div class="flex items-start space-x-2">
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-primary-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <div class="bg-white rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                    <p class="text-gray-800 text-sm">
                        Hi <?= $chatStudentName ? $chatStudentName : 'there' ?>! 👋
                    </p>
                    <p class="text-gray-600 text-sm mt-2">
                        I'm your AI study assistant. I can help you with:
                    </p>
                    <ul class="text-gray-600 text-sm mt-2 space-y-1">
                        <li>• Generate interactive flashcard quizzes</li>
                        <li>• Track your learning progress</li>
                        <li>• Answer nursing questions</li>
                        <li>• Provide study tips</li>
                    </ul>
                </div>
                <span class="text-xs text-gray-400 mt-1 block">Just now</span>
            </div>
        </div>
    </div>
    
    <!-- Typing Indicator -->
    <div id="typingIndicator" class="hidden px-4 pb-2">
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                <i class="fas fa-robot text-primary-600 text-sm"></i>
            </div>
            <div class="bg-white rounded-2xl px-4 py-2 shadow-sm">
                <div class="flex space-x-1">
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms;"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms;"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Input Area -->
    <div class="p-3 bg-white border-t border-gray-200">
        <form id="chatForm" onsubmit="chatSendMessage(event)" class="flex items-end gap-2">
            <div class="flex-1 relative">
                <textarea id="chatInput" 
                          placeholder="Type your message..." 
                          rows="1"
                          class="w-full px-4 py-3 bg-gray-100 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white transition-all"
                          onkeydown="chatHandleKeydown(event)"
                          oninput="chatAutoResize(this)"></textarea>
            </div>
            <button type="submit" 
                    class="w-11 h-11 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition-colors flex items-center justify-center flex-shrink-0 shadow-sm">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
// ============================================
// CHATBOT-SPECIFIC STATE (Separate from dashboard)
// ============================================
let chatIsProcessing = false;
let chatAvailableModules = [];
let chatMessageHistory = [];

// API Endpoint
const CHATBOT_API_URL = '../config/chatbot_endpoint.php';

// ============================================
// NOTE: toggleChatbot() is defined in dashboard.php
// We use the same function from the main dashboard
// ============================================

// Auto-resize textarea
function chatAutoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

// Handle input keydown
function chatHandleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        chatSendMessage(event);
    }
}

// Load modules for flashcard selection
async function chatLoadModules() {
    try {
        const response = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_modules' })
        });
        
        const data = await response.json();
        
        if (data.success && data.modules) {
            chatAvailableModules = data.modules;
            console.log('✅ Loaded', chatAvailableModules.length, 'modules');
        }
    } catch (error) {
        console.error('Error loading modules:', error);
    }
}

// Send chat message
async function chatSendMessage(event) {
    event?.preventDefault();
    
    if (chatIsProcessing) return;
    
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Clear input and add user message
    input.value = '';
    input.style.height = 'auto';
    chatAddMessage(message, 'user');
    
    // Show typing indicator
    chatIsProcessing = true;
    chatShowTyping(true);
    
    try {
        const response = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'chat',
                message: message,
                task: 'chat'
            })
        });
        
        const data = await response.json();
        
        chatShowTyping(false);
        chatIsProcessing = false;
        
        if (data.error) {
            chatAddMessage('Sorry, I encountered an error: ' + data.error, 'bot', true);
        } else if (data.reply) {
            chatAddMessage(data.reply, 'bot');
        } else {
            chatAddMessage('Sorry, I couldn\'t process your request. Please try again.', 'bot', true);
        }
        
    } catch (error) {
        console.error('Chat error:', error);
        chatShowTyping(false);
        chatIsProcessing = false;
        chatAddMessage('Sorry, I\'m having trouble connecting. Please check your connection and try again.', 'bot', true);
    }
}

// Open progress chat - Get real progress data
async function chatOpenProgress() {
    // Use the dashboard's toggleChatbot if needed
    if (!chatbotOpen) {
        toggleChatbot();
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    
    if (chatIsProcessing) return;
    
    // Add user message
    chatAddMessage('Show me my learning progress', 'user');
    
    chatIsProcessing = true;
    chatShowTyping(true);
    
    try {
        // First get progress data
        const progressResponse = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_progress' })
        });
        
        const progressData = await progressResponse.json();
        
        // Show progress card if data available
        if (progressData.success && progressData.progress) {
            chatAddProgressCard(progressData.progress);
        }
        
        // Now get AI analysis
        const aiResponse = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'progress',
                message: 'Analyze my learning progress and give me personalized recommendations.',
                task: 'progress'
            })
        });
        
        const aiData = await aiResponse.json();
        
        chatShowTyping(false);
        chatIsProcessing = false;
        
        if (aiData.error) {
            chatAddMessage('I couldn\'t analyze your progress right now: ' + aiData.error, 'bot', true);
        } else if (aiData.reply) {
            chatAddMessage(aiData.reply, 'bot');
        }
        
    } catch (error) {
        console.error('Progress error:', error);
        chatShowTyping(false);
        chatIsProcessing = false;
        chatAddMessage('Sorry, I couldn\'t fetch your progress. Please try again later.', 'bot', true);
    }
}

// Add progress card to chat
function chatAddProgressCard(progressData) {
    const container = document.getElementById('chatMessages');
    const modules = progressData.modules || {};
    const quizzes = progressData.quizzes || {};
    
    // Calculate overall progress
    const totalModules = parseInt(modules.total_modules) || 0;
    const completedModules = parseInt(modules.completed_modules) || 0;
    const activeModules = parseInt(modules.active_modules) || 0;
    const totalQuizzes = parseInt(quizzes.total_quizzes) || 0;
    const completedQuizzes = parseInt(quizzes.completed_quizzes) || 0;
    const passedQuizzes = parseInt(quizzes.passed_quizzes) || 0;
    const avgScore = parseFloat(quizzes.avg_score) || 0;
    
    const totalItems = totalModules + totalQuizzes;
    const completedItems = completedModules + passedQuizzes;
    const overallProgress = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
    
    const cardHtml = `
        <div class="flex items-start space-x-2 message-slide-in">
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-chart-pie text-primary-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl rounded-tl-none px-4 py-3 shadow-sm border border-blue-100">
                    <p class="text-gray-800 text-sm font-semibold mb-3">📊 Your Progress Summary</p>
                    
                    <!-- Progress Bar -->
                    <div class="mb-3">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Overall Progress</span>
                            <span class="font-semibold text-primary-600">${overallProgress}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-2 rounded-full transition-all duration-500" 
                                 style="width: ${overallProgress}%"></div>
                        </div>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="bg-white rounded-lg p-2 text-center">
                            <p class="text-lg font-bold text-emerald-600">${completedModules}</p>
                            <p class="text-gray-500">Modules Done</p>
                        </div>
                        <div class="bg-white rounded-lg p-2 text-center">
                            <p class="text-lg font-bold text-blue-600">${activeModules}</p>
                            <p class="text-gray-500">In Progress</p>
                        </div>
                        <div class="bg-white rounded-lg p-2 text-center">
                            <p class="text-lg font-bold text-green-600">${passedQuizzes}</p>
                            <p class="text-gray-500">Quizzes Passed</p>
                        </div>
                        <div class="bg-white rounded-lg p-2 text-center">
                            <p class="text-lg font-bold text-purple-600">${avgScore.toFixed(1)}%</p>
                            <p class="text-gray-500">Avg Score</p>
                        </div>
                    </div>
                </div>
                <span class="text-xs text-gray-400 mt-1 block">${chatFormatTime(new Date())}</span>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', cardHtml);
    chatScrollToBottom();
}

// Request flashcards - shows module selection
function chatRequestFlashcards() {
    // Use dashboard's toggle if needed
    if (!chatbotOpen) {
        toggleChatbot();
    }
    
    if (chatAvailableModules.length === 0) {
        chatAddMessage('Loading modules...', 'bot');
        chatLoadModules().then(() => {
            if (chatAvailableModules.length > 0) {
                chatShowModuleSelection();
            } else {
                chatAddMessage('❌ No modules available. Please try again later.', 'bot', true);
            }
        });
    } else {
        chatShowModuleSelection();
    }
}

// Show module selection for flashcards
function chatShowModuleSelection() {
    const container = document.getElementById('chatMessages');
    
    if (chatAvailableModules.length === 0) {
        chatAddMessage('❌ No modules available.', 'bot', true);
        return;
    }
    
    let modulesHTML = '';
    chatAvailableModules.forEach(module => {
        const status = (module.status || 'Pending').toLowerCase();
        const gradient = status === 'completed' ? 'from-green-50 to-emerald-50' : 
                        status === 'in progress' ? 'from-yellow-50 to-orange-50' : 
                        'from-blue-50 to-indigo-50';
        const border = status === 'completed' ? 'border-green-200 hover:border-green-300' : 
                      status === 'in progress' ? 'border-yellow-200 hover:border-yellow-300' : 
                      'border-blue-200 hover:border-blue-300';
        const icon = status === 'completed' ? '✅' : 
                    status === 'in progress' ? '📖' : '📘';
        
        const moduleTitle = chatEscapeHtml(module.title || 'Untitled');
        
        modulesHTML += `
            <button 
                class="w-full flex items-center gap-3 p-3 bg-gradient-to-r ${gradient} hover:shadow-md rounded-lg transition-all text-left border-2 ${border} mb-2"
                onclick="chatStartFlashcard(${module.id}, '${moduleTitle.replace(/'/g, "\\'")}')">
                <span class="text-2xl">${icon}</span>
                <div class="flex-1">
                    <span class="font-medium text-gray-900 text-sm block">${moduleTitle}</span>
                    <span class="text-xs text-gray-600">Click to start flashcard quiz</span>
                </div>
                <i class="fas fa-play-circle text-purple-600 text-xl"></i>
            </button>
        `;
    });
    
    const selectionHtml = `
        <div class="flex items-start space-x-2 message-slide-in">
            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-primary-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <div class="bg-white rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                    <p class="font-semibold text-gray-900 mb-3 text-sm">📚 Select a module for flashcard quiz:</p>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        ${modulesHTML}
                    </div>
                </div>
                <span class="text-xs text-gray-400 mt-1 block">${chatFormatTime(new Date())}</span>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', selectionHtml);
    chatScrollToBottom();
}

// Start flashcard quiz
function chatStartFlashcard(moduleId, moduleTitle) {
    chatAddMessage(`🚀 Starting flashcard quiz for: ${moduleTitle}`, 'bot');
    
    setTimeout(() => {
        window.location.href = `flashcard_quiz.php?module_id=${moduleId}`;
    }, 500);
}

// Quick question shortcut
async function chatQuickQuestion(question) {
    if (!chatbotOpen) {
        toggleChatbot();
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    
    document.getElementById('chatInput').value = question;
    chatSendMessage(new Event('submit'));
}

// Add message to chat
function chatAddMessage(text, sender, isError = false) {
    const container = document.getElementById('chatMessages');
    const time = chatFormatTime(new Date());
    
    let messageHtml = '';
    
    if (sender === 'user') {
        messageHtml = `
            <div class="flex items-start space-x-2 justify-end message-slide-in">
                <div class="flex-1 flex flex-col items-end">
                    <div class="bg-primary-600 text-white rounded-2xl rounded-tr-none px-4 py-3 shadow-sm max-w-[85%]">
                        <p class="text-sm break-words">${chatEscapeHtml(text)}</p>
                    </div>
                    <span class="text-xs text-gray-400 mt-1">${time}</span>
                </div>
                <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
            </div>
        `;
    } else {
        messageHtml = `
            <div class="flex items-start space-x-2 message-slide-in">
                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-robot text-primary-600 text-sm"></i>
                </div>
                <div class="flex-1">
                    <div class="bg-white ${isError ? 'border-2 border-red-200' : ''} rounded-2xl rounded-tl-none px-4 py-3 shadow-sm">
                        <p class="text-gray-800 text-sm break-words whitespace-pre-wrap">${isError ? chatEscapeHtml(text) : chatFormatMessage(text)}</p>
                    </div>
                    <span class="text-xs text-gray-400 mt-1 block">${time}</span>
                </div>
            </div>
        `;
    }
    
    container.insertAdjacentHTML('beforeend', messageHtml);
    chatScrollToBottom();
    
    // Store in history
    chatMessageHistory.push({ role: sender === 'user' ? 'user' : 'assistant', content: text });
}

// Show/hide typing indicator
function chatShowTyping(show) {
    const indicator = document.getElementById('typingIndicator');
    const container = document.getElementById('chatMessages');
    
    if (show) {
        indicator.classList.remove('hidden');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    } else {
        indicator.classList.add('hidden');
    }
}

// Format bot message
function chatFormatMessage(text) {
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/^\d+\.\s+(.+)$/gm, '<div class="ml-2 mb-1">• $1</div>')
        .replace(/^[-•]\s+(.+)$/gm, '<div class="ml-2 mb-1">• $1</div>')
        .replace(/\n/g, '<br>');
}

// Escape HTML
function chatEscapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format time
function chatFormatTime(date) {
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// Scroll to bottom
function chatScrollToBottom() {
    const container = document.getElementById('chatMessages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Initialize chatbot-specific features on DOM load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🤖 Chatbot component initialized');
    
    // Load modules in background
    setTimeout(() => {
        chatLoadModules();
    }, 1000);
});
</script>