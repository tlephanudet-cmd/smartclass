<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$pageTitle = "ห้องแชทกับใจครู";
require_once '../includes/header.php';
?>

<div class="h-[calc(100vh-140px)] flex flex-col md:flex-row gap-4">
    <!-- Sidebar (Online Status / Info) -->
    <div class="md:w-1/4 hidden md:flex flex-col gap-4">
        <div class="glass-panel p-6 text-center">
            <div class="w-20 h-20 bg-indigo-600 rounded-full mx-auto flex items-center justify-center mb-3 shadow-lg shadow-indigo-500/30">
                <i class="fas fa-chalkboard-teacher text-3xl text-white"></i>
            </div>
            <h3 class="text-xl font-bold">ครูเติ้ล</h3>
            <p class="text-green-400 text-sm flex items-center justify-center gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> ออนไลน์
            </p>
        </div>
        
        <div class="glass-panel p-4 flex-1">
            <h4 class="font-bold mb-2">ข้อมูลการติดต่อ</h4>
            <p class="text-gray-400 text-sm">เบอร์โทร: <?php echo getSetting('contact_tel'); ?></p>
            <p class="text-gray-400 text-sm">LINE: <?php echo getSetting('contact_line'); ?></p>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 glass-panel flex flex-col relative overflow-hidden">
        <!-- Header -->
        <div class="p-4 border-b border-gray-700 bg-gray-900/50 flex items-center gap-3">
            <div class="md:hidden w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center">
                 <i class="fas fa-chalkboard-teacher text-white"></i>
            </div>
            <div>
                <h3 class="font-bold text-lg">ห้องแชทส่วนตัว</h3>
                <p class="text-xs text-gray-400">คุยกับครูเติ้ลได้ตลอด 24 ชม.</p>
            </div>
        </div>

        <!-- Messages -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-black/20">
            <div class="text-center text-gray-500 text-sm mt-5">เริ่มการสนทนา...</div>
        </div>

        <!-- Input -->
        <div class="p-3 bg-gray-800 border-t border-gray-700">
            <form id="chat-form" class="flex gap-2 relative">
                <input type="text" id="message-input" class="flex-1 bg-gray-900 border border-gray-600 rounded-full px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition" placeholder="พิมพ์ข้อความ..." autocomplete="off">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let lastId = 0;
const chatBox = document.getElementById('chat-messages');

// Poll for messages
function getMessages() {
    fetch(`../api/chat_core.php?action=get_messages&last_id=${lastId}`)
    .then(res => res.json())
    .then(data => {
        if (data.length > 0) {
            // Remove 'Start conversation' text if exists
            if (lastId === 0) chatBox.innerHTML = '';
            
            data.forEach(msg => {
                appendMessage(msg);
                lastId = msg.id;
            });
            scrollToBottom();
        }
    })
    .catch(err => console.error('Chat poll error:', err));
}

function appendMessage(msg) {
    const isMe = msg.sender_role === 'student';
    const div = document.createElement('div');
    div.className = `flex ${isMe ? 'justify-end' : 'justify-start'} animate-fade-in-up`;
    
    div.innerHTML = `
        <div class="max-w-[75%] ${isMe ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-gray-700 text-gray-200 rounded-bl-none'} p-3 rounded-2xl shadow-sm relative group">
            <p>${msg.message}</p>
            <span class="text-[10px] opacity-70 block text-right mt-1">${msg.time_formatted}</span>
        </div>
    `;
    chatBox.appendChild(div);
}

function scrollToBottom() {
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Send Message
document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('message-input');
    const text = input.value.trim();
    if (!text) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', text);
    // teacher_id defaults to 1 in API

    fetch('../api/chat_core.php?action=send_message', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            input.value = '';
            getMessages(); // Force fetch
        }
    });
});

// Init
getMessages();
setInterval(getMessages, 3000);
</script>

<?php require_once '../includes/footer.php'; ?>
