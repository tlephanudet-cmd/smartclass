<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "ห้องแชทนักเรียน";
require_once '../includes/header.php';
?>

<div class="h-[calc(100vh-140px)] flex gap-4 overflow-hidden">
    <!-- Student List Sidebar -->
    <div class="w-1/3 md:w-1/4 glass-panel flex flex-col">
        <div class="p-4 border-b border-gray-700">
            <h3 class="font-bold text-lg"><i class="fas fa-comments text-indigo-400 mr-2"></i> แชทล่าสุด</h3>
        </div>
        <div id="student-list" class="flex-1 overflow-y-auto p-2 space-y-1">
            <!-- Loaded via AJAX -->
            <div class="text-center text-gray-500 mt-10">กำลังโหลด...</div>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 glass-panel flex flex-col relative w-2/3 md:w-3/4">
        <!-- Header -->
        <div id="chat-header" class="p-4 border-b border-gray-700 bg-gray-900/50 flex items-center justify-between">
            <h3 class="font-bold text-lg">เลือกนักเรียนเพื่อเริ่มแชท</h3>
        </div>

        <!-- Messages -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-black/20">
            <div class="flex h-full items-center justify-center text-gray-500 flex-col gap-3">
                <i class="fas fa-paper-plane text-4xl opacity-50"></i>
                <p>เลือกนักเรียนจากรายการทางซ้าย</p>
            </div>
        </div>

        <!-- Input -->
        <div id="chat-input-area" class="p-3 bg-gray-800 border-t border-gray-700 hidden">
            <form id="chat-form" class="flex gap-2 relative">
                <input type="hidden" id="current-student-id">
                <input type="text" id="message-input" class="flex-1 bg-gray-900 border border-gray-600 rounded-full px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="พิมพ์ข้อความ..." autocomplete="off">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let currentStudentId = 0;
let lastId = 0;
let chatInterval = null;

const studentList = document.getElementById('student-list');
const chatBox = document.getElementById('chat-messages');

// Load Student List
function loadStudentList() {
    fetch('../api/chat_core.php?action=get_chat_list')
    .then(res => res.json())
    .then(data => {
        studentList.innerHTML = '';
        if (data.length === 0) {
            studentList.innerHTML = '<div class="text-center text-gray-500 mt-4">ไม่มีประวัติการแชท</div>';
            return;
        }
        
        data.forEach(s => {
            const activeClass = currentStudentId == s.id ? 'bg-indigo-600/20 border-l-4 border-indigo-500' : 'hover:bg-gray-800 border-l-4 border-transparent';
            studentList.innerHTML += `
                <div onclick="selectStudent(${s.id}, '${s.full_name}')" class="p-3 rounded cursor-pointer transition ${activeClass} flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-700 rounded-full flex-shrink-0 flex items-center justify-center overflow-hidden">
                        ${s.profile_image ? `<img src="../${s.profile_image}" class="w-full h-full object-cover">` : '<i class="fas fa-user text-gray-400"></i>'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-sm truncate text-gray-200">${s.full_name}</h4>
                        <p class="text-xs text-gray-500 truncate">${s.last_message || 'ส่งรูปภาพ'}</p>
                    </div>
                </div>
            `;
        });
    });
}

function selectStudent(id, name) {
    currentStudentId = id;
    document.getElementById('current-student-id').value = id;
    document.getElementById('chat-header').innerHTML = `<h3 class="font-bold text-lg"><i class="fas fa-user-graduate mr-2 text-indigo-400"></i> ${name}</h3>`;
    document.getElementById('chat-input-area').classList.remove('hidden');
    
    // Reset Chat Box
    chatBox.innerHTML = '<div class="text-center text-gray-500 mt-5">กำลังโหลดข้อความ...</div>';
    lastId = 0;
    
    // Clear previous interval if any
    if (chatInterval) clearInterval(chatInterval);
    
    getMessages();
    chatInterval = setInterval(getMessages, 3000);
    
    // Refresh list UI to highlight active
    loadStudentList(); 
}

function getMessages() {
    if (!currentStudentId) return;

    fetch(`../api/chat_core.php?action=get_messages&student_id=${currentStudentId}&last_id=${lastId}`)
    .then(res => res.json())
    .then(data => {
        if (lastId === 0) chatBox.innerHTML = '';
        
        if (data.length > 0) {
            data.forEach(msg => {
                appendMessage(msg);
                lastId = msg.id;
            });
            scrollToBottom();
        }
    });
}

function appendMessage(msg) {
    const isMe = msg.sender_role === 'teacher';
    const div = document.createElement('div');
    div.className = `flex ${isMe ? 'justify-end' : 'justify-start'} animate-fade-in-up`;
    
    div.innerHTML = `
        <div class="max-w-[75%] ${isMe ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-gray-700 text-gray-200 rounded-bl-none'} p-3 rounded-2xl shadow-sm">
            <p>${msg.message}</p>
            <span class="text-[10px] opacity-70 block text-right mt-1">${msg.time_formatted}</span>
        </div>
    `;
    chatBox.appendChild(div);
}

function scrollToBottom() {
    chatBox.scrollTop = chatBox.scrollHeight;
}

document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('message-input');
    const text = input.value.trim();
    if (!text) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', text);
    formData.append('student_id', currentStudentId);

    fetch('../api/chat_core.php?action=send_message', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            input.value = '';
            getMessages(); 
        }
    });
});

// Init
loadStudentList();
setInterval(loadStudentList, 10000); // Updated list every 10s
</script>

<?php require_once '../includes/footer.php'; ?>
