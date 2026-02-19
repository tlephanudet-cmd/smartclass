<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'teacher') {
        header("Location: teacher/admin_dashboard.php");
    } elseif ($_SESSION['role'] == 'student') {
        header("Location: student/student_dashboard.php");
    } else {
        header("Location: teacher/admin_dashboard.php");
    }
    exit();
}

// Fetch Dynamic Content
$site_title = getSetting('site_title') ?: 'Smart Classroom';
$pageTitle = "ยินดีต้อนรับ";

$hero_bg_path = getSetting('hero_bg');
$hero_bg_style = $hero_bg_path ? "background-image: url('$hero_bg_path'); background-size: cover; background-position: center;" : "background: linear-gradient(135deg, #1f2937 0%, #111827 100%);";

$announcements = getAnnouncements();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Kanit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Kanit', sans-serif; }
        .hero-overlay {
            background: rgba(0, 0, 0, 0.6); /* Dark overlay */
        }
        /* Announcement Ticker/Marquee */
        .marquee-container {
            overflow: hidden;
            white-space: nowrap;
        }
        .marquee-content {
            display: inline-block;
            animation: marquee 20s linear infinite;
        }
        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
    </style>
</head>
<body class="bg-gray-900 text-white overflow-hidden h-screen flex flex-col premium-gradient relative">
    
    <!-- Background Gimmicks -->
    <div class="bg-glow top-[-100px] left-[-100px] opacity-30 animate-pulse"></div>
    <div class="bg-glow bottom-[-100px] right-[-100px] opacity-20" style="background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);"></div>

    <!-- Urgent News Alert (Fixed Top) -->
    <?php if (!empty($urgent_news)): ?>
        <div class="bg-red-600/90 backdrop-blur-md text-white px-4 py-2 flex items-center justify-center font-bold animate-pulse z-50 shadow-xl border-b border-red-500/50">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span>
                <?php foreach($urgent_news as $news) echo $news['title'] . ": " . $news['content'] . " | "; ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Navbar (Transparent) -->
    <nav class="absolute top-0 w-full z-40 p-6 flex justify-between items-center bg-gradient-to-b from-black/50 to-transparent">
        <div class="flex items-center gap-3 group cursor-pointer">
            <?php if(getSetting('school_logo')): ?>
                <img src="<?php echo getSetting('school_logo'); ?>" alt="Logo" class="h-12 w-auto drop-shadow-2xl group-hover:scale-110 transition duration-500">
            <?php else: ?>
                <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg group-hover:rotate-12 transition duration-500">
                    <i class="fas fa-graduation-cap text-2xl text-white"></i>
                </div>
            <?php endif; ?>
            
            <div class="text-xl font-bold tracking-wider drop-shadow-md hidden md:block group-hover:text-indigo-400 transition">
                <?php echo getSetting('site_title'); ?>
            </div>
        </div>
        <div class="flex items-center gap-6">
             <a href="#about" class="text-gray-300 hover:text-white transition-all hover:scale-105 hidden lg:block"><i class="fas fa-info-circle mr-1 text-indigo-400"></i> เกี่ยวกับผู้สอน</a>
             <button onclick="openModal('login')" class="btn btn-secondary btn-sm !py-2 !px-6 border-indigo-500/30 hover:border-indigo-500 shadow-lg whitespace-nowrap">ลงชื่อเข้าใช้</button>
        </div>
    </nav>

    <!-- Main Hero Section -->
    <main class="flex-grow relative flex flex-col justify-center overflow-auto py-20">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>

        <!-- Content Container (Bootstrap Style) -->
        <div class="container relative z-10 mx-auto">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10 text-center">
            
            <!-- Welcome Text -->
            <div class="mb-12 card-reveal active">
                <div class="inline-block px-4 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-bold mb-4 border border-indigo-500/30 tracking-widest uppercase">
                    Revolutionizing Classroom
                </div>
                <h1 class="text-5xl md:text-7xl font-extrabold mb-4 text-white drop-shadow-2xl tracking-tighter">
                    <?php echo getSetting('welcome_msg_main'); ?>
                </h1>
                <p class="text-xl md:text-2xl text-gray-300 drop-shadow-md max-w-2xl mx-auto font-light">
                    <?php echo getSetting('welcome_msg_sub'); ?>
                </p>
            </div>

            <!-- Gateway Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 max-w-5xl mx-auto card-reveal active" style="transition-delay: 0.2s;">
                
                <!-- Teacher Button (Triggers Modal) -->
                <button onclick="openModal('teacher')" class="group glass-panel p-10 hover:bg-indigo-600/20 transition-all cursor-pointer border-indigo-500/20 hover:border-indigo-500/60 text-left relative overflow-hidden animate-float">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-20 transition duration-700 group-hover:scale-125">
                        <i class="fas fa-chalkboard-teacher text-9xl text-indigo-300"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-3xl flex items-center justify-center mb-6 text-white shadow-2xl group-hover:rotate-6 transition duration-500">
                            <i class="fas fa-chalkboard-user text-4xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold mb-3 group-hover:text-indigo-300 transition">สำหรับคุณครู</h2>
                        <p class="text-gray-400 text-base leading-relaxed">ระบบหลังบ้านสำหรับจัดการการเรียนการสอน เช็คชื่อ และประมวลผลเกรดอัตโนมัติ</p>
                        
                        <div class="mt-6 flex items-center text-indigo-400 font-bold group-hover:translate-x-2 transition duration-300">
                            เข้าสู่ห้องเรียน <i class="fas fa-arrow-right ml-2 group-hover:ml-4 transition-all"></i>
                        </div>
                    </div>
                </button>

                <!-- Student Button (Triggers Modal) -->
                <button onclick="openModal('student')" class="group glass-panel p-10 hover:bg-emerald-600/20 transition-all cursor-pointer border-emerald-500/20 hover:border-emerald-500/60 text-left relative overflow-hidden animate-float" style="animation-delay: 0.5s;">
                     <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-20 transition duration-700 group-hover:scale-125">
                        <i class="fas fa-user-graduate text-9xl text-emerald-300"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl flex items-center justify-center mb-6 text-white shadow-2xl group-hover:-rotate-6 transition duration-500">
                            <i class="fas fa-rocket text-4xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold mb-3 group-hover:text-emerald-300 transition">สำหรับนักเรียน</h2>
                        <p class="text-gray-400 text-base leading-relaxed">พื้นที่ปล่อยของ ส่งงาน เก็บ XP และร่วมกิจกรรมสนุกๆ ในชั้นเรียนไปพร้อมกัน</p>
                        
                        <div class="mt-6 flex items-center text-emerald-400 font-bold group-hover:translate-x-2 transition duration-300">
                            เริ่มต้นการเรียนรู้ <i class="fas fa-arrow-right ml-2 group-hover:ml-4 transition-all"></i>
                        </div>
                    </div>
                </button>

            </div>

             <!-- Parent Zone -->
             <div class="mt-16 card-reveal active" style="transition-delay: 0.4s;">
                <a href="#" class="inline-flex items-center text-gray-400 hover:text-white border border-gray-700/50 rounded-full px-8 py-3 bg-black/40 hover:bg-indigo-600/20 hover:border-indigo-500/50 transition-all duration-300 shadow-lg group">
                    <i class="fas fa-qrcode mr-3 text-indigo-400 group-hover:scale-125 transition"></i> ผู้ปกครองตรวจสอบผลการเรียนรายบุคคล (Scan QR)
                </a>
             </div>

                </div> <!-- End Col -->
            </div> <!-- End Row -->
        </div> <!-- End Root Container -->
    </main>

    <!-- Modal Layout -->
    <div id="authModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/90 backdrop-blur-md" onclick="closeModal()"></div>
        
        <!-- Modal Content -->
        <div class="relative z-10 w-full max-w-sm p-4">
            <div class="glass-panel p-10 relative shadow-[0_0_50px_rgba(79,70,229,0.3)] border border-white/10 ring-1 ring-white/10">
                <button onclick="closeModal()" class="absolute top-6 right-6 text-gray-500 hover:text-white hover:rotate-90 transition duration-300">
                    <i class="fas fa-times text-2xl"></i>
                </button>
                
                <div class="text-center mb-8">
                    <div id="modalIcon" class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg">
                        <i class="fas fa-lock text-3xl"></i>
                    </div>
                    <h3 id="modalTitle" class="text-2xl font-black mb-2 text-white tracking-tight">เข้าสู่ระบบ</h3>
                    <p id="modalSubtitle" class="text-gray-400 text-sm">กรุณายืนยันตัวตนเพื่อเข้าใช้งาน</p>
                </div>
                
                <form action="auth/login.php" method="POST" class="space-y-5">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase text-gray-500 ml-1">ชื่อผู้ใช้ / รหัสนักเรียน</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                            <input type="text" name="username" class="w-full bg-black/30 border border-gray-700/50 rounded-xl py-3 pl-12 pr-4 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-all text-white placeholder-gray-600" placeholder="Username" required>
                        </div>
                    </div>
                    <div class="space-y-1">
                         <label class="block text-xs font-bold uppercase text-gray-500 ml-1">รหัสผ่าน</label>
                         <div class="relative">
                            <i class="fas fa-key absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                            <input type="password" name="password" class="w-full bg-black/30 border border-gray-700/50 rounded-xl py-3 pl-12 pr-4 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-all text-white placeholder-gray-600" placeholder="••••••" required>
                         </div>
                    </div>
                    
                    <button type="submit" class="w-full btn btn-primary !py-4 font-bold text-lg group">
                        ยืนยันการเข้าสู่ระบบ 
                        <i class="fas fa-chevron-right ml-2 group-hover:translate-x-1 transition"></i>
                    </button>
                </form>
                
                <div class="mt-8 text-center bg-white/5 p-4 rounded-xl border border-white/5">
                    <a href="auth/register.php" class="text-sm text-indigo-400 hover:text-indigo-300 font-bold transition">ยังไม่มีบัญชี? สมัครสมาชิกใหม่</a>
                </div>
            </div>
        </div>
    </div>

    <!-- News Ticker (Bottom Fixed) -->
    <?php if (!empty($normal_news)): ?>
        <div class="absolute bottom-0 w-full bg-black/60 backdrop-blur-lg border-t border-white/5 py-4 z-30">
            <div class="container mx-auto flex items-center px-4">
                <div class="bg-indigo-600 text-[10px] font-black px-3 py-1 rounded-full mr-4 uppercase tracking-[0.2em] shadow-lg shadow-indigo-500/20">ประกาศ</div>
                <div class="marquee-container flex-grow overflow-hidden text-gray-300 text-sm font-medium">
                    <div class="marquee-content italic">
                        <?php foreach($normal_news as $news) echo "<span class='mx-8 border-l-2 border-indigo-500 pl-4'><i class='fas fa-sparkles text-indigo-400 mr-2'></i> " . $news['title'] . ": " . $news['content'] . "</span>"; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(role) {
            const modal = document.getElementById('authModal');
            const title = document.getElementById('modalTitle');
            const sub = document.getElementById('modalSubtitle');
            const icon = document.getElementById('modalIcon');
            
            modal.classList.remove('hidden');
            
            if(role === 'teacher') {
                title.innerText = 'เข้าสู่ระบบสำหรับครู';
                sub.innerText = 'จัดการชั้นเรียนและดูแลนักเรียน';
                icon.className = 'w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg';
                icon.innerHTML = '<i class="fas fa-chalkboard-user text-3xl"></i>';
            } else if(role === 'student') {
                title.innerText = 'เข้าสู่ระบบสำหรับนักเรียน';
                sub.innerText = 'ดูคะแนน ส่งงาน และสะสมแต้ม';
                icon.className = 'w-16 h-16 bg-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg';
                icon.innerHTML = '<i class="fas fa-rocket text-3xl"></i>';
            } else {
                title.innerText = 'เข้าสู่ระบบ';
                sub.innerText = 'กรุณายืนยันตัวตนเพื่อเข้าใช้งาน';
                icon.className = 'w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg';
                icon.innerHTML = '<i class="fas fa-lock text-3xl"></i>';
            }
        }
        
        function closeModal() {
            document.getElementById('authModal').classList.add('hidden');
        }

        // Card reveal animation check
        document.addEventListener('DOMContentLoaded', () => {
            const reveals = document.querySelectorAll('.card-reveal');
            reveals.forEach(el => el.classList.add('active'));
        });
    </script>
</body>
</html>
