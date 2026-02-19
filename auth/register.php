<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'];
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize($_POST['full_name']);

    // Validation
    if ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } elseif (usernameExists($username)) {
        $error = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว";
    } else {
        // Role specific logic
        if ($role == 'teacher') {
            $pin = $_POST['teacher_pin'];
            if ($pin !== '14099') {
                $error = "รหัสสำหรับครู (Security PIN) ไม่ถูกต้อง";
            }
        } elseif ($role == 'student') {
            // Check student redundant fields if needed
            // For now Phase 1 just basic auth
            $student_code = sanitize($_POST['student_code']);
            if (studentCodeExists($student_code)) {
                $error = "รหัสนักเรียนนี้มีในระบบแล้ว";
            }
        }

        if (!isset($error)) {
            // Register Logic
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $conn->begin_transaction();
            try {
                // Insert into users
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, '')");
                $stmt->bind_param("sss", $username, $hashed_password, $role);
                $stmt->execute();
                $user_id = $conn->insert_id;

                if ($role == 'teacher') {
                    $stmt = $conn->prepare("INSERT INTO teachers (user_id, full_name) VALUES (?, ?)");
                    $stmt->bind_param("is", $user_id, $full_name);
                    $stmt->execute();
                } else {
                    $student_code = sanitize($_POST['student_code']);
                    $nickname = sanitize($_POST['nickname']);
                    $class_level = sanitize($_POST['class_level']);
                    $room = sanitize($_POST['room']);
                    $number = (int)$_POST['number'];

                    $stmt = $conn->prepare("INSERT INTO students (user_id, student_code, full_name, nickname, class_level, room, number) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssi", $user_id, $student_code, $full_name, $nickname, $class_level, $room, $number);
                    $stmt->execute();
                }

                $conn->commit();
                header("Location: ../index.php"); // Redirect to home to login via modal
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - Smart Classroom</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Kanit', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4" style="background-image: url('../assets/img/hero-bg.jpg'); background-size: cover; background-blend-mode: overlay;">

    <div class="glass-panel w-full max-w-2xl p-8 animate-fade-in-up">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">สมัครสมาชิกใหม่</h1>
            <p class="text-gray-400">ระบบห้องเรียนอัจฉริยะ 4.0</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-600/20 border border-red-500 text-red-200 p-4 rounded mb-6 text-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" id="registerForm" class="space-y-6">
            
            <!-- Role Selection -->
            <div class="grid grid-cols-2 gap-6 mb-8">
                <label class="cursor-pointer group">
                    <input type="radio" name="role" value="student" class="peer sr-only" checked onchange="toggleFields()">
                    <div class="glass-panel text-center p-6 border-2 border-white/5 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 hover:bg-white/5 transition-all duration-300 transform group-hover:-translate-y-1">
                        <div class="w-16 h-16 bg-emerald-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-user-graduate text-3xl text-emerald-400"></i>
                        </div>
                        <h3 class="font-bold text-lg">ฉันคือนักเรียน</h3>
                        <p class="text-[10px] text-gray-500 mt-1">เข้าเรียน ส่งงาน สะสมแต้ม</p>
                    </div>
                </label>
                <label class="cursor-pointer group">
                    <input type="radio" name="role" value="teacher" class="peer sr-only" onchange="toggleFields()">
                    <div class="glass-panel text-center p-6 border-2 border-white/5 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 hover:bg-white/5 transition-all duration-300 transform group-hover:-translate-y-1">
                        <div class="w-16 h-16 bg-indigo-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-chalkboard-teacher text-3xl text-indigo-400"></i>
                        </div>
                        <h3 class="font-bold text-lg">ฉันคือคุณครู</h3>
                        <p class="text-[10px] text-gray-500 mt-1">จัดการชั้นเรียน ให้คะแนน</p>
                    </div>
                </label>
            </div>

            <!-- Common Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ชื่อผูัใช้ (Username)</label>
                    <input type="text" name="username" class="w-full bg-gray-800 border border-gray-700 rounded p-3 focus:border-indigo-500 transition" required placeholder="ตั้งชื่อผู้ใช้ภาษาอังกฤษ">
                </div>
                <div>
                     <label class="block text-sm text-gray-400 mb-1">ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" class="w-full bg-gray-800 border border-gray-700 rounded p-3 focus:border-indigo-500 transition" required placeholder="นายรักเรียน ตั้งใจ">
                </div>
                <div>
                     <label class="block text-sm text-gray-400 mb-1">รหัสผ่าน</label>
                    <input type="password" name="password" class="w-full bg-gray-800 border border-gray-700 rounded p-3 focus:border-indigo-500 transition" required placeholder="******">
                </div>
                 <div>
                     <label class="block text-sm text-gray-400 mb-1">ยืนยันรหัสผ่าน</label>
                    <input type="password" name="confirm_password" class="w-full bg-gray-800 border border-gray-700 rounded p-3 focus:border-indigo-500 transition" required placeholder="******">
                </div>
            </div>

            <!-- Student Specific Fields -->
            <div id="studentFields" class="space-y-4 border-t border-gray-700 pt-4">
                <h3 class="text-emerald-400 font-bold"><i class="fas fa-id-card mr-2"></i> ข้อมูลนักเรียน</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">รหัสนักเรียน</label>
                        <input type="text" name="student_code" class="w-full bg-gray-800 border border-gray-700 rounded p-3" placeholder="เลขประจำตัว 5 หลัก">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">ชื่อเล่น (ไว้เรียกขาน)</label>
                        <input type="text" name="nickname" class="w-full bg-gray-800 border border-gray-700 rounded p-3" placeholder="น้อง...">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                     <div>
                        <label class="block text-sm text-gray-400 mb-1">ระดับชั้น</label>
                        <select name="class_level" class="w-full bg-gray-800 border border-gray-700 rounded p-3">
                            <option value="ม.1">ม.1</option>
                            <option value="ม.2">ม.2</option>
                            <option value="ม.3">ม.3</option>
                            <option value="ม.4">ม.4</option>
                            <option value="ม.5">ม.5</option>
                            <option value="ม.6">ม.6</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">ห้อง</label>
                        <input type="text" name="room" class="w-full bg-gray-800 border border-gray-700 rounded p-3" placeholder="1">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">เลขที่</label>
                        <input type="number" name="number" class="w-full bg-gray-800 border border-gray-700 rounded p-3" placeholder="1-50">
                    </div>
                </div>
            </div>

            <!-- Teacher Specific Fields -->
            <div id="teacherFields" class="hidden space-y-4 border-t border-gray-700 pt-4">
                 <h3 class="text-indigo-400 font-bold"><i class="fas fa-user-shield mr-2"></i> ยืนยันตัวตนครู</h3>
                 <div>
                    <label class="block text-sm text-gray-400 mb-1">รหัสความปลอดภัย (Security PIN)</label>
                    <input type="password" name="teacher_pin" class="w-full bg-gray-800 border border-gray-700 rounded p-3" placeholder="กรอกรหัส 5 หลัก">
                    <p class="text-xs text-gray-500 mt-1">* เฉพาะบุคลากรเท่านั้น</p>
                </div>
            </div>

            <button type="submit" class="w-full btn btn-primary py-4 font-bold text-lg mt-6 shadow-lg hover:scale-105 transition transform">
                สมัครสมาชิก
            </button>
            
            <p class="text-center text-gray-400 mt-4">
                มีบัญชีแล้ว? <a href="../index.php" class="text-indigo-400 hover:underline">เข้าสู่ระบบที่นี่</a>
            </p>

        </form>
    </div>

    <script>
        function toggleFields() {
            const role = document.querySelector('input[name="role"]:checked').value;
            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');

            if (role === 'student') {
                studentFields.classList.remove('hidden');
                teacherFields.classList.add('hidden');
                
                // Add required to student fields, remove from teacher
                document.getElementsByName('student_code')[0].required = true;
                document.getElementsByName('nickname')[0].required = true;
                document.getElementsByName('room')[0].required = true;
                document.getElementsByName('number')[0].required = true;
                document.getElementsByName('teacher_pin')[0].required = false;
            } else {
                studentFields.classList.add('hidden');
                teacherFields.classList.remove('hidden');
                
                // Remove required from student fields, add to teacher
                document.getElementsByName('student_code')[0].required = false;
                document.getElementsByName('nickname')[0].required = false;
                document.getElementsByName('room')[0].required = false;
                document.getElementsByName('number')[0].required = false;
                document.getElementsByName('teacher_pin')[0].required = true;
            }
        }
        
        // Init
        toggleFields();
    </script>
</body>
</html>
