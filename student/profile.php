<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$pageTitle = "โปรไฟล์ของฉัน";
$user_id = $_SESSION['user_id'];

// Ensure student_id is in session
if (!isset($_SESSION['student_id'])) {
    $s_res = $conn->query("SELECT * FROM students WHERE user_id = $user_id");
    if ($s_res->num_rows > 0) {
        $sd = $s_res->fetch_assoc();
        $_SESSION['student_id'] = $sd['id'];
        $_SESSION['full_name'] = $sd['full_name'];
    }
}
$student_id = $_SESSION['student_id'];

// Auto-add missing columns
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone varchar(20) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS email varchar(100) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS birthday date DEFAULT NULL");

// Fetch current data
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

// Pending leaves (for sidebar badge)
$pending_leaves = 0;
$pl_res = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE student_id = $student_id AND status = 'pending'");
if ($pl_res) $pending_leaves = $pl_res->fetch_assoc()['cnt'] ?? 0;

$success_msg = '';
$error_msg = '';

// ========== Handle Profile Image Upload ==========
if (isset($_POST['action']) && $_POST['action'] === 'upload_image') {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowed)) {
            $error_msg = 'ประเภทไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์ JPG, PNG, GIF หรือ WEBP เท่านั้น';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error_msg = 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . $student_id . '_' . time() . '.' . $ext;
            $upload_dir = '../uploads/students/';
            $filepath = $upload_dir . $filename;
            $db_path = 'uploads/students/' . $filename;

            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old image
                if (!empty($student['profile_image']) && file_exists('../' . $student['profile_image'])) {
                    unlink('../' . $student['profile_image']);
                }

                $stmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE id = ?");
                $stmt->bind_param("si", $db_path, $student_id);
                $stmt->execute();

                $_SESSION['profile_image'] = $db_path;
                $student['profile_image'] = $db_path;
                $success_msg = 'อัปโหลดรูปโปรไฟล์สำเร็จแล้ว!';
            } else {
                $error_msg = 'ไม่สามารถอัปโหลดไฟล์ได้ กรุณาลองใหม่';
            }
        }
    } else {
        $error_msg = 'กรุณาเลือกไฟล์รูปภาพ';
    }
}

// ========== Handle Edit Info ==========
if (isset($_POST['action']) && $_POST['action'] === 'update_info') {
    $nickname = sanitize($_POST['nickname'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $birthday = sanitize($_POST['birthday'] ?? '');

    $stmt = $conn->prepare("UPDATE students SET nickname = ?, phone = ?, email = ?, birthday = ? WHERE id = ?");
    $bday_val = !empty($birthday) ? $birthday : null;
    $stmt->bind_param("ssssi", $nickname, $phone, $email, $bday_val, $student_id);

    if ($stmt->execute()) {
        $student['nickname'] = $nickname;
        $student['phone'] = $phone;
        $student['email'] = $email;
        $student['birthday'] = $bday_val;
        $success_msg = 'บันทึกข้อมูลเรียบร้อยแล้ว!';
    } else {
        $error_msg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
    }
}

// ========== Handle Password Change ==========
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error_msg = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (!password_verify($current_pass, $user['password'])) {
        $error_msg = 'รหัสผ่านเดิมไม่ถูกต้อง';
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($new_pass) < 4) {
        $error_msg = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 4 ตัวอักษร';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
            $success_msg = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว!';
        } else {
            $error_msg = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
        }
    }
}

require_once '../includes/header.php';
?>

<style>
    .profile-avatar {
        width: 140px; height: 140px;
        border-radius: 50%; overflow: hidden;
        border: 4px solid rgba(16, 185, 129, 0.4);
        box-shadow: 0 0 30px rgba(16, 185, 129, 0.15);
        margin: 0 auto; transition: all 0.3s;
    }
    .profile-avatar:hover { border-color: rgba(16, 185, 129, 0.7); transform: scale(1.03); }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-avatar .placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #059669, #10b981); }

    .tab-btn { padding: 10px 20px; font-weight: 600; font-size: 14px; border: none; border-radius: 10px 10px 0 0; cursor: pointer; transition: all 0.2s; background: rgba(255,255,255,0.05); color: #94a3b8; }
    .tab-btn.active { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border-bottom: 2px solid #10b981; }
    .tab-btn:hover:not(.active) { background: rgba(255,255,255,0.08); color: #e2e8f0; }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 14px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; }
    .form-label i { color: #34d399; margin-right: 6px; }
    .form-input {
        width: 100%; padding: 10px 14px; font-size: 14px; font-family: 'Kanit', sans-serif;
        background: rgba(15, 23, 42, 0.6); border: 1px solid #374151; border-radius: 10px;
        color: #f1f5f9; outline: none; transition: all 0.2s;
    }
    .form-input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
    .form-input:disabled { opacity: 0.5; cursor: not-allowed; }

    .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; }
    .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }

    .info-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 4px 12px; border-radius: 20px; font-size: 12px; color: #6ee7b7; }
</style>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <div class="profile-avatar">
                <?php if (!empty($student['profile_image'])): ?>
                    <img src="../<?php echo $student['profile_image']; ?>" alt="Profile">
                <?php else: ?>
                    <div class="placeholder"><i class="fas fa-user-graduate text-5xl text-white/80"></i></div>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold mt-4"><?php echo htmlspecialchars($student['full_name']); ?></h3>
            <div class="flex flex-wrap justify-center gap-2 mt-2">
                <span class="info-chip"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['student_code']); ?></span>
                <span class="info-chip"><i class="fas fa-school"></i> ม.<?php echo htmlspecialchars($student['class_level']); ?>/<?php echo htmlspecialchars($student['room']); ?></span>
            </div>
            <p class="text-emerald-400 text-sm font-bold mt-2">นักเรียน</p>
        </div>

        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="grades.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-line w-8"></i> ผลการเรียน</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
            <a href="request_leave.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> ขอลา
                <?php if ($pending_leaves > 0): ?>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full ml-1"><?php echo $pending_leaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="block px-4 py-2 rounded bg-emerald-600 text-white shadow-lg"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <!-- Header -->
        <div class="glass-panel p-6">
            <h2 class="text-2xl font-bold"><i class="fas fa-user-cog mr-2 text-emerald-400"></i>จัดการโปรไฟล์</h2>
            <p class="text-gray-400 text-sm mt-1">แก้ไขข้อมูลส่วนตัว อัปโหลดรูปภาพ และเปลี่ยนรหัสผ่าน</p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle text-lg"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle text-lg"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="glass-panel overflow-hidden">
            <div class="flex border-b border-gray-700 px-4 pt-2">
                <button class="tab-btn active" onclick="switchTab('info')"><i class="fas fa-user mr-2"></i>ข้อมูลส่วนตัว</button>
                <button class="tab-btn" onclick="switchTab('photo')"><i class="fas fa-camera mr-2"></i>รูปโปรไฟล์</button>
                <button class="tab-btn" onclick="switchTab('password')"><i class="fas fa-lock mr-2"></i>เปลี่ยนรหัสผ่าน</button>
            </div>

            <!-- Tab: Personal Info -->
            <div class="tab-panel active p-6" id="tab-info">
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">

                    <!-- Read-only fields -->
                    <h4 class="text-sm font-bold text-gray-400 mb-4 uppercase"><i class="fas fa-lock mr-1 text-gray-500"></i>ข้อมูลที่ไม่สามารถแก้ไขได้</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-id-card"></i>รหัสนักเรียน</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($student['student_code']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user"></i>ชื่อ-นามสกุล</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($student['full_name']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-school"></i>ชั้นเรียน</label>
                            <input type="text" class="form-input" value="ม.<?php echo htmlspecialchars($student['class_level']); ?>/<?php echo htmlspecialchars($student['room']); ?> เลขที่ <?php echo $student['number']; ?>" disabled>
                        </div>
                    </div>

                    <hr class="border-gray-700 mb-6">

                    <!-- Editable fields -->
                    <h4 class="text-sm font-bold text-gray-400 mb-4 uppercase"><i class="fas fa-edit mr-1 text-emerald-400"></i>ข้อมูลที่แก้ไขได้</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-smile"></i>ชื่อเล่น</label>
                            <input type="text" name="nickname" class="form-input" value="<?php echo htmlspecialchars($student['nickname'] ?? ''); ?>" placeholder="ชื่อเล่นของคุณ">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-phone"></i>เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" placeholder="081-xxx-xxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-envelope"></i>อีเมล</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="student@email.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-birthday-cake"></i>วันเกิด</label>
                            <input type="date" name="birthday" class="form-input" value="<?php echo htmlspecialchars($student['birthday'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary px-6 mt-2" style="background: linear-gradient(135deg, #059669, #10b981);"><i class="fas fa-save mr-2"></i>บันทึกข้อมูล</button>
                </form>
            </div>

            <!-- Tab: Profile Photo -->
            <div class="tab-panel p-6" id="tab-photo">
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="text-center flex-shrink-0">
                        <div class="profile-avatar" style="width: 180px; height: 180px;">
                            <?php if (!empty($student['profile_image'])): ?>
                                <img src="../<?php echo $student['profile_image']; ?>" alt="Profile" id="preview-img">
                            <?php else: ?>
                                <div class="placeholder" id="preview-placeholder"><i class="fas fa-user-graduate text-6xl text-white/80"></i></div>
                                <img src="" alt="Profile" id="preview-img" style="display:none; width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-400 text-sm mt-3">รูปปัจจุบัน</p>
                    </div>

                    <div class="flex-1 w-full">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_image">

                            <div class="border-2 border-dashed border-gray-600 rounded-xl p-8 text-center hover:border-emerald-500/50 transition cursor-pointer"
                                 onclick="document.getElementById('file-input').click()">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-500 mb-3"></i>
                                <p class="text-gray-300 font-bold mb-1">คลิกเพื่อเลือกรูปภาพ</p>
                                <p class="text-gray-500 text-sm">รองรับ JPG, PNG, GIF, WEBP (สูงสุด 5MB)</p>
                                <p class="text-emerald-400 text-sm mt-2 font-bold" id="file-name" style="display:none;"></p>
                            </div>

                            <input type="file" name="profile_image" id="file-input" accept="image/*" class="hidden" onchange="previewFile(this)">

                            <button type="submit" class="btn btn-primary px-6 mt-4" id="upload-btn" disabled style="background: linear-gradient(135deg, #059669, #10b981);">
                                <i class="fas fa-upload mr-2"></i>อัปโหลดรูปภาพ
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Change Password -->
            <div class="tab-panel p-6" id="tab-password">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="max-w-md space-y-5">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-key"></i>รหัสผ่านเดิม <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="current_password" class="form-input pr-10" required id="pass-current">
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300" onclick="togglePass('pass-current')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-lock"></i>รหัสผ่านใหม่ <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="new_password" class="form-input pr-10" required minlength="4" id="pass-new">
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300" onclick="togglePass('pass-new')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-lock"></i>ยืนยันรหัสผ่านใหม่ <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="confirm_password" class="form-input pr-10" required minlength="4" id="pass-confirm">
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300" onclick="togglePass('pass-confirm')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-xs mt-2 text-gray-500" id="pass-match-msg"></p>
                        </div>

                        <div class="p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-sm text-yellow-300">
                            <i class="fas fa-info-circle mr-2"></i> เมื่อเปลี่ยนรหัสผ่านแล้ว ให้ใช้รหัสผ่านใหม่ในการเข้าสู่ระบบครั้งถัดไป
                        </div>

                        <button type="submit" class="btn btn-primary px-6" style="background: linear-gradient(135deg, #059669, #10b981);"><i class="fas fa-shield-alt mr-2"></i>เปลี่ยนรหัสผ่าน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

    const tabMap = { 'info': 0, 'photo': 1, 'password': 2 };
    const btns = document.querySelectorAll('.tab-btn');
    if (btns[tabMap[tab]]) btns[tabMap[tab]].classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function previewFile(input) {
    const file = input.files[0];
    if (file) {
        document.getElementById('file-name').textContent = '📎 ' + file.name;
        document.getElementById('file-name').style.display = 'block';
        document.getElementById('upload-btn').disabled = false;

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('preview-img');
            if (img) { img.src = e.target.result; img.style.display = 'block'; }
            const ph = document.getElementById('preview-placeholder');
            if (ph) ph.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

function togglePass(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
    else { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
}

const newPass = document.getElementById('pass-new');
const confirmPass = document.getElementById('pass-confirm');
const matchMsg = document.getElementById('pass-match-msg');
if (confirmPass) {
    confirmPass.addEventListener('input', function() {
        if (this.value && newPass.value) {
            if (this.value === newPass.value) { matchMsg.textContent = '✅ รหัสผ่านตรงกัน'; matchMsg.className = 'text-xs mt-2 text-green-400'; }
            else { matchMsg.textContent = '❌ รหัสผ่านไม่ตรงกัน'; matchMsg.className = 'text-xs mt-2 text-red-400'; }
        } else { matchMsg.textContent = ''; }
    });
}

<?php if (isset($_POST['action'])): ?>
    <?php if ($_POST['action'] === 'upload_image'): ?>
        switchTab('photo');
    <?php elseif ($_POST['action'] === 'change_password'): ?>
        switchTab('password');
    <?php endif; ?>
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
