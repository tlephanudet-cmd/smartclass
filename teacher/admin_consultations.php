<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "กล่องที่ปรึกษา";

// Ensure is_anonymous column exists
$conn->query("ALTER TABLE `consultations` ADD COLUMN `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 AFTER `student_id`");

// Fetch ALL consultations (LEFT JOIN to include anonymous ones too)
$consultations = $conn->query("SELECT c.*, s.full_name, s.student_code, s.profile_image 
                              FROM consultations c 
                              LEFT JOIN students s ON c.student_id = s.id 
                              ORDER BY c.created_at DESC");

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-pie w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-check w-8"></i> เช็คชื่อ</a>
            <a href="gradebook.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-list w-8"></i> สมุดคะแนน</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังสื่อการสอน</a>
            <a href="admin_consultations.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-comments w-8"></i> กล่องปรึกษา</a>
            <a href="manage_leaves.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> จัดการใบลา</a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-cog w-8"></i> ตั้งค่า</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <h2 class="text-2xl font-bold mb-4">กล่องข้อความจากนักเรียน</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- List -->
            <div class="glass-panel p-4 h-[600px] overflow-y-auto">
                <?php if ($consultations && $consultations->num_rows > 0): ?>
                    <?php while($row = $consultations->fetch_assoc()): ?>
                        <?php
                            $isAnon = !empty($row['is_anonymous']);
                            $displayName = $isAnon 
                                ? 'ผู้ไม่ประสงค์ออกนาม 🕵️' 
                                : (isset($row['full_name']) ? htmlspecialchars($row['full_name']) : 'ไม่ทราบชื่อ');
                            $realName = isset($row['full_name']) ? htmlspecialchars($row['full_name']) : 'ไม่ทราบชื่อ';
                        ?>
                        <div class="p-3 mb-2 rounded hover:bg-gray-700 transition border border-gray-700 <?php echo (isset($row['status']) && $row['status']=='pending') ? 'bg-gray-800 border-l-4 border-l-yellow-500' : 'bg-gray-900'; ?> relative group">
                            <div class="cursor-pointer" onclick="loadChat(<?php echo $row['id']; ?>)">
                                <div class="flex justify-between mb-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-sm <?php echo $isAnon ? 'text-gray-400 italic' : ''; ?>" id="name-display-<?php echo $row['id']; ?>"><?php echo $displayName; ?></span>
                                        <?php if ($isAnon): ?>
                                            <button onclick="event.stopPropagation(); toggleReveal(<?php echo $row['id']; ?>, '<?php echo addslashes($realName); ?>')" 
                                                class="text-amber-400/60 hover:text-amber-300 hover:bg-amber-500/10 p-0.5 px-1 rounded text-xs transition flex items-center gap-1" 
                                                id="reveal-btn-<?php echo $row['id']; ?>"
                                                title="ดูชื่อจริง">
                                                <i class="fas fa-eye text-[10px]"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-500"><?php echo isset($row['created_at']) ? date('d/m H:i', strtotime($row['created_at'])) : ''; ?></span>
                                </div>
                                <p class="text-sm text-gray-300 truncate"><?php echo isset($row['topic']) ? htmlspecialchars($row['topic']) : (isset($row['topic_category']) ? htmlspecialchars($row['topic_category']) : 'ไม่มีหัวข้อ'); ?></p>
                                <?php if(isset($row['status']) && ($row['status'] == 'pending' || $row['status'] == 'processing')): ?>
                                    <span class="text-xs bg-yellow-500 text-black px-2 py-0.5 rounded mt-1 inline-block">รอตอบกลับ</span>
                                <?php endif; ?>
                                <?php if ($isAnon): ?>
                                    <span class="text-xs bg-gray-600/50 text-gray-400 px-2 py-0.5 rounded mt-1 inline-block ml-1">🕵️ ไม่ระบุตัวตน</span>
                                <?php endif; ?>
                            </div>
                            <button onclick="event.stopPropagation(); deleteConsultation(<?php echo $row['id']; ?>)" 
                                class="absolute top-2 right-2 text-red-400/40 hover:text-red-400 hover:bg-red-500/10 p-1 rounded transition opacity-0 group-hover:opacity-100"
                                title="ลบข้อความนี้">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-gray-500 py-10">
                        <i class="fas fa-inbox text-4xl mb-3 block opacity-30"></i>
                        <p class="font-bold">ยังไม่มีข้อความ</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Chat Area -->
            <div class="glass-panel p-4 h-[600px] flex flex-col relative">
                <div id="chat-header" class="border-b border-gray-700 pb-2 mb-2">
                    <h3 class="font-bold">เลือกข้อความเพื่อเริ่มสนทนา</h3>
                </div>
                
                <div id="chat-messages" class="flex-1 overflow-y-auto space-y-2 p-2 bg-black/20 rounded mb-2">
                    <!-- Messages loaded via AJAX -->
                    <div class="text-center text-gray-500 mt-20">ยังไม่ได้เลือกแชท</div>
                </div>

                <form id="reply-form" class="mt-auto hidden" onsubmit="sendReply(event)">
                    <input type="hidden" name="consultation_id" id="chat-id">
                    <div class="flex gap-2">
                        <input type="text" name="message" id="reply-msg" class="flex-1 bg-gray-800 border-gray-700 rounded p-2 text-white" placeholder="พิมพ์ข้อความตอบกลับ..." required>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle reveal/hide real name for anonymous messages
function toggleReveal(id, realName) {
    const nameEl = document.getElementById('name-display-' + id);
    const btnEl = document.getElementById('reveal-btn-' + id);
    
    if (btnEl.dataset.revealed === 'true') {
        // Hide name again
        nameEl.textContent = 'ผู้ไม่ประสงค์ออกนาม 🕵️';
        nameEl.classList.add('text-gray-400', 'italic');
        btnEl.innerHTML = '<i class="fas fa-eye text-[10px]"></i>';
        btnEl.title = 'ดูชื่อจริง';
        btnEl.dataset.revealed = 'false';
    } else {
        // Reveal real name
        nameEl.textContent = '👨‍🎓 ' + realName;
        nameEl.classList.remove('text-gray-400', 'italic');
        nameEl.classList.add('text-amber-300');
        btnEl.innerHTML = '<i class="fas fa-eye-slash text-[10px]"></i>';
        btnEl.title = 'ซ่อนชื่อ';
        btnEl.dataset.revealed = 'true';
    }
}

function loadChat(id) {
    document.getElementById('chat-id').value = id;
    document.getElementById('reply-form').classList.remove('hidden');
    
    // Fetch info & messages
    fetch('../api/consultation_api.php?action=get_messages&id=' + id)
    .then(res => res.json())
    .then(data => {
        const header = document.getElementById('chat-header');
        
        // Show anonymous label with reveal button in chat header too
        let headerHTML = '';
        if (data.is_anonymous) {
            headerHTML = `
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-gray-400 italic" id="chat-name-display">ผู้ไม่ประสงค์ออกนาม 🕵️</h3>
                    <button onclick="toggleChatReveal('${data.real_name || 'ไม่ทราบชื่อ'}')" 
                        class="text-amber-400/70 hover:text-amber-300 text-xs px-2 py-1 rounded bg-amber-500/10 hover:bg-amber-500/20 transition flex items-center gap-1"
                        id="chat-reveal-btn" data-revealed="false">
                        <i class="fas fa-eye text-[10px]"></i> ดูชื่อจริง
                    </button>
                </div>
                <p class="text-xs text-gray-400">${data.topic || 'ไม่มีหัวข้อ'}</p>`;
        } else {
            headerHTML = `<h3 class="font-bold">${data.student_name || 'ไม่ทราบชื่อ'}</h3><p class="text-xs text-gray-400">${data.topic || 'ไม่มีหัวข้อ'}</p>`;
        }
        header.innerHTML = headerHTML;
        
        const content = document.getElementById('chat-messages');
        content.innerHTML = '';
        
        // Original post
        content.innerHTML += `
            <div class="flex justify-start mb-2">
                <div class="bg-gray-700 p-3 rounded-lg max-w-[80%] rounded-tl-none">
                    <p class="text-sm">${data.message}</p>
                    <span class="text-xs text-gray-500 block mt-1">${data.created_at}</span>
                </div>
            </div>
        `;

        // Render replies
        if (data.replies && data.replies.length > 0) {
            data.replies.forEach(r => {
                const isMe = r.sender_type === 'teacher'; 
                const msgText = r.message || '';
                
                content.innerHTML += `
                    <div class="flex ${isMe ? 'justify-end' : 'justify-start'} mb-2">
                        <div class="${isMe ? 'bg-indigo-600' : 'bg-gray-700'} p-2 rounded-lg max-w-[80%] text-sm">
                            <p>${msgText}</p>
                        </div>
                    </div>
                `;
            });
        }
        content.scrollTop = content.scrollHeight;
    });
}

function toggleChatReveal(realName) {
    const nameEl = document.getElementById('chat-name-display');
    const btnEl = document.getElementById('chat-reveal-btn');
    
    if (btnEl.dataset.revealed === 'true') {
        nameEl.textContent = 'ผู้ไม่ประสงค์ออกนาม 🕵️';
        nameEl.classList.add('text-gray-400', 'italic');
        nameEl.classList.remove('text-amber-300');
        btnEl.innerHTML = '<i class="fas fa-eye text-[10px]"></i> ดูชื่อจริง';
        btnEl.dataset.revealed = 'false';
    } else {
        nameEl.textContent = '👨‍🎓 ' + realName;
        nameEl.classList.remove('text-gray-400', 'italic');
        nameEl.classList.add('text-amber-300');
        btnEl.innerHTML = '<i class="fas fa-eye-slash text-[10px]"></i> ซ่อนชื่อ';
        btnEl.dataset.revealed = 'true';
    }
}

function sendReply(e) {
    e.preventDefault();
    const id = document.getElementById('chat-id').value;
    const msgInput = document.getElementById('reply-msg');
    const msg = msgInput.value.trim();
    
    if (!msg) return;

    const formData = new FormData();
    formData.append('consultation_id', id);
    formData.append('message', msg);

    const btn = document.querySelector('#reply-form button');
    btn.disabled = true;

    fetch('save_reply.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if(data.status === 'success') {
            msgInput.value = '';
            
            const content = document.getElementById('chat-messages');
            content.innerHTML += `
                <div class="flex justify-end mb-2 animate-fade-in-up">
                    <div class="bg-indigo-600 p-2 rounded-lg max-w-[80%] text-sm">
                        <p>${msg}</p>
                    </div>
                </div>
            `;
            content.scrollTop = content.scrollHeight;
        } else {
            alert('บันทึกไม่สำเร็จ: ' + (data.message || 'Error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        console.error('Error:', err);
        alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ (ตรวจสอบไฟล์ save_reply.php)');
    });
}

function deleteConsultation(id) {
    if (!confirm('⚠️ ต้องการลบข้อความนี้ใช่ไหม?\nข้อความและการตอบกลับทั้งหมดจะถูกลบถาวร')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('../student/api_delete_consultation.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert('❌ ' + (data.message || 'เกิดข้อผิดพลาด'));
        }
    })
    .catch(() => alert('เชื่อมต่อล้มเหลว'));
}
</script>

<?php require_once '../includes/footer.php'; ?>
