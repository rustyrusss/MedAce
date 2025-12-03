/**
 * MedAce Chatbot JavaScript - Component Compatible Version
 * Works with chatbot.php component and chatbot_endpoint.php
 * Location: /medace/assets/js/chatbot.js
 */

console.log('🤖 Chatbot JS loading...');

let availableModules = [];

// Load modules from PHP endpoint
async function loadModules() {
    console.log('📚 Loading modules from endpoint...');
    
    try {
        const response = await fetch('../config/chatbot_endpoint.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_modules' })
        });

        console.log('Response status:', response.status);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        console.log('Response data:', result);

        if (result.success && result.modules) {
            availableModules = result.modules;
            console.log('✅ Loaded', availableModules.length, 'modules');
            return availableModules;
        } else {
            console.error('❌ No modules in response:', result);
            return [];
        }
    } catch (error) {
        console.error('❌ Load modules error:', error);
        return [];
    }
}

// Request flashcards - shows module selection
function requestFlashcards() {
    console.log('🎴 requestFlashcards called');
    console.log('Available modules:', availableModules.length);
    
    if (availableModules.length === 0) {
        addMessage('Loading modules...', 'bot');
        loadModules().then(modules => {
            if (modules.length > 0) {
                showModuleSelection();
            } else {
                addMessage('❌ No modules available. Please try again later.', 'bot');
            }
        });
    } else {
        showModuleSelection();
    }
}

// Show module selection
function showModuleSelection() {
    console.log('📋 Showing module selection');
    
    const messagesDiv = document.getElementById('chatMessages');
    if (!messagesDiv) return;
    
    if (availableModules.length === 0) {
        addMessage('❌ No modules available.', 'bot');
        return;
    }
    
    const selectionDiv = document.createElement('div');
    selectionDiv.className = 'flex items-start space-x-2 mb-4';
    
    let modulesHTML = '';
    availableModules.forEach(module => {
        const statusLower = (module.status || module.student_status || 'pending').toLowerCase();
        const gradient = statusLower === 'completed' ? 'from-green-50 to-emerald-50' : 
                        statusLower === 'in progress' ? 'from-yellow-50 to-orange-50' : 
                        'from-blue-50 to-indigo-50';
        const border = statusLower === 'completed' ? 'border-green-200 hover:border-green-300' : 
                      statusLower === 'in progress' ? 'border-yellow-200 hover:border-yellow-300' : 
                      'border-blue-200 hover:border-blue-300';
        const icon = statusLower === 'completed' ? '✅' : 
                    statusLower === 'in progress' ? '📖' : '📘';
        
        const moduleTitle = escapeHtml(module.title || 'Untitled');
        
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
                <p class="font-semibold text-gray-900 mb-3 text-sm">📚 Select a module for flashcard quiz:</p>
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
    console.log('🚀 Starting quiz:', moduleId, moduleTitle);
    addMessage(`🚀 Starting flashcard quiz for: ${moduleTitle}`, 'bot');
    
    setTimeout(() => {
        window.location.href = `flashcard_quiz.php?module_id=${moduleId}`;
    }, 500);
}

// Helper: Add message to chat
function addMessage(text, type) {
    const messagesDiv = document.getElementById('chatMessages');
    if (!messagesDiv) return;
    
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
    console.log('✅ Chatbot initialized');
    loadModules();
});

console.log('✅ Chatbot JS loaded');