<!-- 
    Flashcard History Modal Component
    Add this to your student dashboard.php file
    Location: Include in /medace/member/dashboard.php
-->

<!-- Flashcard History Button (Add to your dashboard stats or quick actions section) -->
<div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-custom card-hover border border-gray-100">
    <div class="flex items-center justify-between mb-3 sm:mb-4">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center text-white shadow-lg" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <i class="fas fa-clone text-xl sm:text-2xl"></i>
        </div>
        <button onclick="openFlashcardHistory()" class="text-purple-600 hover:text-purple-700 text-sm font-semibold">
            View History <i class="fas fa-arrow-right ml-1"></i>
        </button>
    </div>
    <h3 class="text-gray-500 text-xs sm:text-sm font-medium mb-1">Flashcard Practice</h3>
    <div class="flex items-baseline space-x-2">
        <p class="text-3xl sm:text-4xl font-bold text-gray-900" id="flashcardAttempts">--</p>
        <span class="text-sm text-gray-500">attempts</span>
    </div>
    <p class="text-xs text-gray-400 mt-2">Avg Score: <span id="flashcardAvgScore" class="font-semibold text-purple-600">--%</span></p>
</div>

<!-- Flashcard History Modal -->
<div id="flashcardHistoryModal" class="fixed inset-0 z-50 hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeFlashcardHistory()"></div>
    
    <!-- Modal Content -->
    <div class="absolute inset-4 sm:inset-8 lg:inset-16 bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-scale-in">
        <!-- Modal Header -->
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-purple-600 to-indigo-600">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-clone"></i>
                    Flashcard History
                </h2>
                <p class="text-purple-100 text-xs sm:text-sm mt-0.5">Your practice quiz attempts</p>
            </div>
            <button onclick="closeFlashcardHistory()" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Stats Summary -->
        <div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white rounded-xl p-3 sm:p-4 text-center shadow-sm">
                    <div class="text-2xl sm:text-3xl font-bold text-purple-600" id="statTotalAttempts">0</div>
                    <div class="text-xs text-gray-500 mt-1">Total Attempts</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 text-center shadow-sm">
                    <div class="text-2xl sm:text-3xl font-bold text-green-600" id="statAvgScore">0%</div>
                    <div class="text-xs text-gray-500 mt-1">Avg Score</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 text-center shadow-sm">
                    <div class="text-2xl sm:text-3xl font-bold text-yellow-600" id="statBestScore">0%</div>
                    <div class="text-xs text-gray-500 mt-1">Best Score</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 text-center shadow-sm">
                    <div class="text-2xl sm:text-3xl font-bold text-blue-600" id="statTotalCorrect">0</div>
                    <div class="text-xs text-gray-500 mt-1">Total Correct</div>
                </div>
            </div>
        </div>
        
        <!-- History List -->
        <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4">
            <div id="historyLoading" class="flex items-center justify-center py-12">
                <div class="w-8 h-8 border-3 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
            </div>
            
            <div id="historyEmpty" class="hidden text-center py-12">
                <i class="fas fa-clipboard-list text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No History Yet</h3>
                <p class="text-gray-500 text-sm mb-4">Start practicing with flashcards to see your history here.</p>
                <a href="quizzes.php" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-medium">
                    <i class="fas fa-play"></i> Start Practice
                </a>
            </div>
            
            <div id="historyList" class="space-y-3 hidden"></div>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-4 sm:px-6 py-3 border-t border-gray-200 bg-gray-50 flex justify-end">
            <button onclick="closeFlashcardHistory()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm font-medium">
                Close
            </button>
        </div>
    </div>
</div>

<style>
@keyframes scale-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-scale-in {
    animation: scale-in 0.2s ease-out;
}

.history-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1rem;
    transition: all 0.2s;
}
.history-card:hover {
    border-color: #a855f7;
    box-shadow: 0 4px 12px rgba(168, 85, 247, 0.1);
}

.score-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
}
.score-badge.excellent { background: #dcfce7; color: #166534; }
.score-badge.good { background: #fef3c7; color: #92400e; }
.score-badge.needs-work { background: #fee2e2; color: #991b1b; }

.mode-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    background: #f3f4f6;
    border-radius: 0.375rem;
    font-size: 0.7rem;
    color: #6b7280;
    font-weight: 500;
}
</style>

<script>
// Load flashcard stats on page load
document.addEventListener('DOMContentLoaded', function() {
    loadFlashcardStats();
});

async function loadFlashcardStats() {
    try {
        const response = await fetch('../actions/get_flashcard_history.php?limit=1');
        const data = await response.json();
        
        if (data.success && data.stats) {
            document.getElementById('flashcardAttempts').textContent = data.stats.total_attempts;
            document.getElementById('flashcardAvgScore').textContent = data.stats.avg_score + '%';
        }
    } catch (error) {
        console.error('Error loading flashcard stats:', error);
    }
}

function openFlashcardHistory() {
    document.getElementById('flashcardHistoryModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    loadFlashcardHistory();
}

function closeFlashcardHistory() {
    document.getElementById('flashcardHistoryModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

async function loadFlashcardHistory() {
    const loading = document.getElementById('historyLoading');
    const empty = document.getElementById('historyEmpty');
    const list = document.getElementById('historyList');
    
    loading.classList.remove('hidden');
    empty.classList.add('hidden');
    list.classList.add('hidden');
    
    try {
        const response = await fetch('../actions/get_flashcard_history.php?limit=50');
        const data = await response.json();
        
        loading.classList.add('hidden');
        
        if (data.success) {
            // Update stats
            if (data.stats) {
                document.getElementById('statTotalAttempts').textContent = data.stats.total_attempts;
                document.getElementById('statAvgScore').textContent = data.stats.avg_score + '%';
                document.getElementById('statBestScore').textContent = data.stats.best_score + '%';
                document.getElementById('statTotalCorrect').textContent = data.stats.total_correct;
            }
            
            if (data.attempts && data.attempts.length > 0) {
                list.classList.remove('hidden');
                list.innerHTML = data.attempts.map(attempt => createHistoryCard(attempt)).join('');
            } else {
                empty.classList.remove('hidden');
            }
        } else {
            empty.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading history:', error);
        loading.classList.add('hidden');
        empty.classList.remove('hidden');
    }
}

function createHistoryCard(attempt) {
    const score = parseFloat(attempt.score_percentage);
    let scoreClass = 'needs-work';
    if (score >= 80) scoreClass = 'excellent';
    else if (score >= 60) scoreClass = 'good';
    
    const date = new Date(attempt.completed_at);
    const formattedDate = date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
    const formattedTime = date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit'
    });
    
    const timeSpent = attempt.time_spent_seconds 
        ? formatTime(attempt.time_spent_seconds) 
        : '--';
    
    const modeIcon = attempt.mode === 'definition' ? 'fa-pencil-alt' : 'fa-list-ul';
    const modeText = attempt.mode === 'definition' ? 'Definition' : 'Multiple Choice';
    
    return `
        <div class="history-card">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-gray-900 truncate">${escapeHtml(attempt.source_title)}</h4>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                        <span class="mode-tag">
                            <i class="fas ${modeIcon}"></i>
                            ${modeText}
                        </span>
                        <span class="text-xs text-gray-500">
                            <i class="fas fa-clock mr-1"></i>${timeSpent}
                        </span>
                        <span class="text-xs text-gray-500">
                            <i class="fas fa-calendar mr-1"></i>${formattedDate} at ${formattedTime}
                        </span>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="score-badge ${scoreClass}">${Math.round(score)}%</span>
                    <div class="text-xs text-gray-500 mt-1.5">
                        <span class="text-green-600 font-medium">${attempt.correct_answers}</span>
                        <span class="text-gray-400">/</span>
                        <span>${attempt.total_questions}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function formatTime(seconds) {
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFlashcardHistory();
    }
});
</script>