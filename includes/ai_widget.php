<?php
// includes/ai_widget.php
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'student'): 
?>
<div id="ai-widget-container" class="fixed bottom-6 right-6 z-50">
    <!-- Chat Button -->
    <button id="ai-toggle-btn" onclick="toggleChat()" class="bg-gradient-to-r from-indigo-500 to-purple-600 w-16 h-16 rounded-full shadow-2xl flex items-center justify-center transform hover:scale-110 transition duration-300 border-2 border-white/20 animate-bounce-slow">
        <i class="fas fa-robot text-3xl text-white"></i>
    </button>

    <!-- Chat Window -->
    <div id="ai-chat-window" class="hidden absolute bottom-20 right-0 w-80 md:w-96 glass-panel flex flex-col shadow-2xl border border-indigo-500/30 overflow-hidden transform origin-bottom-right transition-all duration-300 scale-90 opacity-0" style="height: 500px;">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 p-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-robot text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-white">ครูเติ้ล (AI Teacher)</h3>
                    <p class="text-xs text-indigo-200 flex items-center"><span class="w-2 h-2 bg-green-400 rounded-full mr-1"></span> ออนไลน์</p>
                </div>
            </div>
            <button onclick="toggleChat()" class="text-white/70 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <!-- Messages Area -->
        <div id="ai-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-900/50 scrollbar-thin">
            <!-- Welcome Message -->
            <div class="flex justify-start">
                <div class="bg-white text-gray-900 p-3 rounded-2xl rounded-tl-none max-w-[85%] text-sm shadow-md">
                    สวัสดีครับ! ครูเติ้ลเองครับ 🤖 มีอะไรให้ครูช่วยไหม? ถามเรื่องเรียนหรือการบ้านได้เลยนะ
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="p-3 bg-gray-800/90 border-t border-gray-700">
            <form id="ai-form" class="relative">
                <input type="text" id="ai-input" class="w-full bg-gray-900 border border-gray-600 text-white rounded-full pl-4 pr-12 py-3 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition placeholder-gray-500" placeholder="ถามครูเติ้ล..." autocomplete="off">
                <button type="submit" id="ai-send-btn" class="absolute right-2 top-1.5 w-9 h-9 bg-indigo-600 hover:bg-indigo-500 text-white rounded-full flex items-center justify-center transition shadow-lg">
                    <i class="fas fa-paper-plane text-xs"></i>
                </button>
            </form>
            <p class="text-[10px] text-gray-500 text-center mt-2">AI อาจตอบผิดพลาดได้ โปรดตรวจสอบข้อมูลอีกครั้ง</p>
        </div>
    </div>
</div>

<style>
    .animate-bounce-slow { animation: bounce 3s infinite; }
    #ai-chat-window.open { display: flex; transform: scale(100%); opacity: 1; }
</style>



<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    // === AI Chat Widget ===
    // Dynamic Base URL for API
    const BASE_URL = '<?php echo $base_url; ?>';
    const AI_API_URL = `${BASE_URL}/api_chat.php`;

    function toggleChat() {
        const win = document.getElementById('ai-chat-window');
        if (win.classList.contains('hidden')) {
            win.classList.remove('hidden');
            setTimeout(() => win.classList.add('open'), 10);
            document.getElementById('ai-input').focus();
        } else {
            win.classList.remove('open');
            setTimeout(() => win.classList.add('hidden'), 300);
        }
    }

    // Form submit handler
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('ai-form');
        const input = document.getElementById('ai-input');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent page reload
                sendChatMessage();
            });
        }
        
        // Also allow Enter key (redundant but safe)
        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendChatMessage();
                }
            });
        }
    });

    function sendChatMessage() {
        const input = document.getElementById('ai-input');
        const message = input.value.trim();
        if (!message) return;

        // User Message
        addChatMessage(message, 'user');
        input.value = '';
        input.focus();

        // Typing Indicator
        const typingId = 'typing-' + Date.now();
        showTypingIndicator(typingId);

        // API Call
        const formData = new FormData();
        formData.append('action', 'chat');
        formData.append('message', message);

        fetch(AI_API_URL, { method: 'POST', body: formData })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            removeTypingIndicator(typingId);
            if (data.status === 'success') {
                addChatMessage(data.reply, 'ai');
            } else {
                addChatMessage(data.reply || 'เกิดข้อผิดพลาด: ' + (data.message || 'รหัสคำสั่งไม่ถูกต้อง'), 'ai');
            }
        })
        .catch(err => {
            console.error('Chat Error:', err);
            removeTypingIndicator(typingId);
            addChatMessage('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err.message, 'ai');
        });
    }

    function addChatMessage(text, sender) {
        const div = document.createElement('div');
        div.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'} animate-fade-in-up mb-3`;
        
        const bubble = document.createElement('div');
        // Ensure text is black for User, and readable for AI
        // User: Purple bg, White text
        // AI: White bg, Black text (explicitly set text-gray-900)
        bubble.className = sender === 'user' 
            ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-3 rounded-2xl rounded-tr-none max-w-[85%] text-sm shadow-md' 
            : 'bg-white text-gray-900 p-3 rounded-2xl rounded-tl-none max-w-[85%] text-sm shadow-md prose prose-sm border border-gray-200';
        
        if (sender === 'ai' && typeof marked !== 'undefined') {
            try {
                bubble.innerHTML = marked.parse(text);
            } catch (e) {
                bubble.innerHTML = text.replace(/\n/g, '<br>');
            }
        } else {
            bubble.innerHTML = text.replace(/\n/g, '<br>');
        }
        
        div.appendChild(bubble);
        
        const container = document.getElementById('ai-messages');
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function showTypingIndicator(id) {
        const div = document.createElement('div');
        div.id = id;
        div.className = 'flex justify-start animate-pulse mb-3';
        div.innerHTML = `
            <div class="bg-gray-200 p-3 rounded-2xl rounded-tl-none text-sm flex gap-1 items-center">
                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full animate-bounce"></span>
                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                <span class="w-1.5 h-1.5 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
            </div>
        `;
        document.getElementById('ai-messages').appendChild(div);
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if(el) el.remove();
    }
</script>
<?php endif; ?>
