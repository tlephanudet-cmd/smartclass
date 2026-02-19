<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "เครื่องมือจัดการห้องเรียน";
require_once '../includes/header.php';

// Fetch all students for tools
$students = $conn->query("SELECT * FROM students ORDER BY number ASC");
$student_list = [];
while($row = $students->fetch_assoc()) {
    $student_list[] = $row;
}
?>

<script>
    const students = <?php echo json_encode($student_list); ?>;
</script>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <div class="w-24 h-24 bg-indigo-500/20 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden border-2 border-indigo-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user-tie text-4xl text-indigo-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="tools.php" class="block px-4 py-2 rounded bg-indigo-600 text-white"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-cog w-8"></i> ตั้งค่าเว็บไซต์</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">

        <h1 class="text-3xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600">
            <i class="fas fa-hat-wizard mr-2"></i> เครื่องมือช่วยสอน
        </h1>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- 1. Random Picker -->
            <div class="glass-panel p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <i class="fas fa-dice text-9xl"></i>
                </div>
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-random mr-2 text-yellow-400"></i> สุ่มเลือกนักเรียน</h2>
                
                <div id="winner-display" class="bg-gray-800 rounded-lg h-48 flex flex-col items-center justify-center mb-4 border-2 border-dashed border-gray-600">
                    <div class="text-6xl font-bold text-gray-600" id="random-number">?</div>
                    <div class="text-xl text-gray-400 mt-2" id="random-name">พร้อมสุ่ม...</div>
                </div>
                
                <button onclick="pickStudent()" class="w-full btn btn-primary py-3 text-lg font-bold shadow-lg transform hover:scale-105 transition">
                    <i class="fas fa-play mr-2"></i> สุ่มเลย!
                </button>
            </div>

            <!-- 2. Timer -->
            <div class="glass-panel p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <i class="fas fa-stopwatch text-9xl"></i>
                </div>
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-stopwatch mr-2 text-red-400"></i> นาฬิกาจับเวลา</h2>
                
                <div class="flex justify-center mb-6">
                    <div class="text-6xl font-mono font-bold text-white tracking-widest" id="timer-display">00:00</div>
                </div>
                
                <div class="flex gap-2 justify-center mb-4">
                    <button onclick="setTimer(5)" class="px-3 py-1 bg-gray-700 rounded hover:bg-gray-600 text-sm">5 นาที</button>
                    <button onclick="setTimer(10)" class="px-3 py-1 bg-gray-700 rounded hover:bg-gray-600 text-sm">10 นาที</button>
                    <button onclick="setTimer(30)" class="px-3 py-1 bg-gray-700 rounded hover:bg-gray-600 text-sm">30 นาที</button>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <button onclick="startTimer()" class="btn bg-green-600 hover:bg-green-500 text-white"><i class="fas fa-play"></i> เริ่ม</button>
                    <button onclick="pauseTimer()" class="btn bg-yellow-600 hover:bg-yellow-500 text-white"><i class="fas fa-pause"></i> หยุด</button>
                    <button onclick="resetTimer()" class="btn bg-red-600 hover:bg-red-500 text-white"><i class="fas fa-redo"></i> รีเซ็ต</button>
                </div>
            </div>

            <!-- 3. Group Generator -->
            <div class="glass-panel p-6 lg:col-span-2">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-users-cog mr-2 text-blue-400"></i> แบ่งกลุ่มอัตโนมัติ</h2>
                <div class="flex gap-4 items-end mb-4">
                    <div class="flex-grow">
                        <label class="block text-sm text-gray-400 mb-1">จำนวนกลุ่ม</label>
                        <input type="number" id="group-count" value="5" min="2" max="10" class="w-full bg-gray-700 border border-gray-600 rounded p-2 text-white">
                    </div>
                    <button onclick="generateGroups()" class="btn btn-secondary"><i class="fas fa-cogs mr-2"></i> สร้างกลุ่ม</button>
                </div>
                
                <div id="group-results" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div class="p-4 bg-gray-800 rounded text-center text-gray-500 border border-gray-700 col-span-full">
                        เลือกจำนวนกลุ่มแล้วกดปุ่ม "สร้างกลุ่ม"
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Soundboard Fixed Bottom Bar -->
<div class="fixed bottom-0 left-0 w-full bg-gray-900/90 backdrop-blur-md border-t border-gray-700 p-4 z-50 transform transition-transform duration-300" id="soundboard">
    <div class="container mx-auto flex items-center justify-between">
        <div class="flex items-center gap-4">
            <span class="text-indigo-400 font-bold uppercase tracking-wider text-sm"><i class="fas fa-music mr-2"></i> แผงเสียงเอฟเฟกต์</span>
            <div class="flex gap-2">
                <button onclick="playSound('applause')" class="p-2 bg-gray-800 rounded-full hover:bg-green-600 transition w-10 h-10 flex items-center justify-center" title="ปรบมือ">👏</button>
                <button onclick="playSound('drum')" class="p-2 bg-gray-800 rounded-full hover:bg-blue-600 transition w-10 h-10 flex items-center justify-center" title="กลอง">🥁</button>
                <button onclick="playSound('wrong')" class="p-2 bg-gray-800 rounded-full hover:bg-red-600 transition w-10 h-10 flex items-center justify-center" title="ผิด">❌</button>
                <button onclick="playSound('correct')" class="p-2 bg-gray-800 rounded-full hover:bg-yellow-600 transition w-10 h-10 flex items-center justify-center" title="ถูก">✨</button>
                <button onclick="playSound('horn')" class="p-2 bg-gray-800 rounded-full hover:bg-purple-600 transition w-10 h-10 flex items-center justify-center" title="แตร">📢</button>
            </div>
        </div>
        <button onclick="document.getElementById('soundboard').classList.toggle('translate-y-full')" class="text-gray-500 hover:text-white">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
</div>

<script>
    // --- Random Picker Logic ---
    function pickStudent() {
        if(students.length === 0) return alert('ไม่พบนักเรียนในระบบ!');
        
        const displayNum = document.getElementById('random-number');
        const displayName = document.getElementById('random-name');
        
        let interval = setInterval(() => {
            const random = students[Math.floor(Math.random() * students.length)];
            displayNum.innerText = random.number;
            displayName.innerText = random.full_name;
        }, 50);

        playSound('drum');

        setTimeout(() => {
            clearInterval(interval);
            const winner = students[Math.floor(Math.random() * students.length)];
            displayNum.innerText = winner.number;
            displayName.innerText = winner.full_name;
            displayNum.classList.add('text-green-500');
            playSound('applause');
        }, 2000);
    }

    // --- Timer Logic ---
    let timerInterval;
    let timeLeft = 0;

    function setTimer(minutes) {
        timeLeft = minutes * 60;
        updateTimerDisplay();
    }

    function updateTimerDisplay() {
        const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
        const s = (timeLeft % 60).toString().padStart(2, '0');
        document.getElementById('timer-display').innerText = `${m}:${s}`;
    }

    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            if (timeLeft > 0) {
                timeLeft--;
                updateTimerDisplay();
            } else {
                clearInterval(timerInterval);
                playSound('horn');
                alert("หมดเวลาแล้ว!");
            }
        }, 1000);
    }

    function pauseTimer() {
        clearInterval(timerInterval);
    }

    function resetTimer() {
        pauseTimer();
        timeLeft = 0;
        updateTimerDisplay();
    }

    // --- Group Generator Logic ---
    function generateGroups() {
        const count = parseInt(document.getElementById('group-count').value);
        if(!count || count < 2) return;

        let shuffled = [...students].sort(() => 0.5 - Math.random());
        
        let groups = Array.from({length: count}, () => []);
        shuffled.forEach((student, index) => {
            groups[index % count].push(student);
        });

        const container = document.getElementById('group-results');
        container.innerHTML = '';
        groups.forEach((group, i) => {
            let html = `<div class="bg-gray-800 rounded p-4 border border-gray-700">`;
            html += `<h4 class="font-bold text-indigo-400 mb-2">กลุ่มที่ ${i+1}</h4>`;
            html += `<ul class="text-sm space-y-1 text-gray-300">`;
            group.forEach(s => {
                html += `<li>${s.number}. ${s.nickname || s.full_name}</li>`;
            });
            html += `</ul></div>`;
            container.innerHTML += html;
        });
    }

    // --- Soundboard Logic ---
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    
    function playSound(type) {
        if (type === 'applause') {
            console.log("Playing applause");
        } else if (type === 'drum') {
             console.log("Playing drum");
        }
        
        const btn = document.querySelector(`button[onclick="playSound('${type}')"]`);
        if(btn) {
            btn.classList.add('scale-125');
            setTimeout(() => btn.classList.remove('scale-125'), 200);
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
