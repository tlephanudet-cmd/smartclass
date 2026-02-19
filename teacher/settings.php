<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$pageTitle = "ตั้งค่าเว็บไซต์";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_settings'])) {
        $settings = [
            'site_title' => $_POST['site_title'],
            'welcome_msg_main' => $_POST['welcome_msg_main'],
            'welcome_msg_sub' => $_POST['welcome_msg_sub'],
            'teacher_bio' => $_POST['teacher_bio'],
            'contact_fb' => $_POST['contact_fb'],
            'contact_line' => $_POST['contact_line'],
            'contact_tel' => $_POST['contact_tel'],
            'line_notify_token' => $_POST['line_notify_token'],
            'gemini_api_key' => $_POST['gemini_api_key']
        ];

        foreach ($settings as $key => $value) {
            $val = trim(sanitize($value));
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $key, $val);
            $stmt->execute();
        }

        // Handle File Uploads
        $uploadDir = '../uploads/cms/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] == 0) {
            $filename = 'logo_' . time() . '.' . pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['school_logo']['tmp_name'], $uploadDir . $filename);
            $path = 'uploads/cms/' . $filename;
            $conn->query("UPDATE site_settings SET setting_value = '$path' WHERE setting_key = 'school_logo'");
        }

        if (isset($_FILES['hero_bg']) && $_FILES['hero_bg']['error'] == 0) {
            $filename = 'hero_' . time() . '.' . pathinfo($_FILES['hero_bg']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['hero_bg']['tmp_name'], $uploadDir . $filename);
            $path = 'uploads/cms/' . $filename;
            $conn->query("UPDATE site_settings SET setting_value = '$path' WHERE setting_key = 'hero_bg'");
        }

        setFlashMessage('success', "บันทึกการตั้งค่าเรียบร้อยแล้ว");
    }

    if (isset($_POST['add_announcement'])) {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $type = sanitize($_POST['type']);
        
        $sql = "INSERT INTO announcements (category, title, message, is_active) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $type, $title, $content);
        
        if ($stmt->execute()) {
            setFlashMessage('success', "เพิ่มประกาศเรียบร้อยแล้ว");
        } else {
            setFlashMessage('error', "ไม่สามารถเพิ่มประกาศได้: " . $conn->error);
        }
    }
}

// Handle Delete/Toggle Announcement
if (isset($_GET['delete_ann'])) {
    $id = (int)$_GET['delete_ann'];
    $conn->query("DELETE FROM announcements WHERE id = $id");
    header("Location: settings.php");
    exit();
}

if (isset($_GET['toggle_ann'])) {
    $id = (int)$_GET['toggle_ann'];
    $conn->query("UPDATE announcements SET is_active = NOT is_active WHERE id = $id");
    header("Location: settings.php");
    exit();
}

// Fetch Data
$announcements = $conn->query("SELECT * FROM announcements ORDER BY COALESCE(updated_at, created_at) DESC");

require_once '../includes/header.php';
?>

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
            <a href="settings.php" class="block px-4 py-2 rounded bg-indigo-600 text-white"><i class="fas fa-cog w-8"></i> ตั้งค่าเว็บไซต์</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>

        <!-- General Settings -->
        <div class="glass-panel p-6">
            <h2 class="text-2xl font-bold mb-4"><i class="fas fa-globe mr-2"></i> ตั้งค่าเว็บไซต์ทั่วไป</h2>
            <form action="settings.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="update_settings" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label>ชื่อเว็บไซต์</label>
                        <input type="text" name="site_title" value="<?php echo getSetting('site_title'); ?>" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label>ข้อความต้อนรับ (หลัก)</label>
                        <input type="text" name="welcome_msg_main" value="<?php echo getSetting('welcome_msg_main'); ?>">
                    </div>
                    <div>
                        <label>ข้อความต้อนรับ (รอง)</label>
                        <input type="text" name="welcome_msg_sub" value="<?php echo getSetting('welcome_msg_sub'); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                        <label>โลโก้โรงเรียน (อัพเดท)</label>
                        <input type="file" name="school_logo" accept="image/*">
                        <?php if(getSetting('school_logo')): ?>
                            <img src="../<?php echo getSetting('school_logo'); ?>" class="h-10 mt-2">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label>รูปพื้นหลังหน้าแรก (อัพเดท)</label>
                        <input type="file" name="hero_bg" accept="image/*">
                        <?php if(getSetting('hero_bg')): ?>
                            <img src="../<?php echo getSetting('hero_bg'); ?>" class="h-10 mt-2">
                        <?php endif; ?>
                    </div>
                </div>

                <h3 class="text-lg font-bold mt-4 border-b border-gray-700 pb-2">ข้อมูลครูผู้สอน</h3>
                <div>
                    <label>ประวัติ / คำทักทาย</label>
                    <textarea name="teacher_bio" rows="3"><?php echo getSetting('teacher_bio'); ?></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label>ลิงก์เฟซบุ๊ก</label>
                        <input type="text" name="contact_fb" value="<?php echo getSetting('contact_fb'); ?>">
                    </div>
                    <div>
                        <label>ลิงก์ไลน์</label>
                        <input type="text" name="contact_line" value="<?php echo getSetting('contact_line'); ?>">
                    </div>
                    <div>
                        <label>เบอร์โทรศัพท์</label>
                        <input type="text" name="contact_tel" value="<?php echo getSetting('contact_tel'); ?>">
                    </div>
                </div>

                <div class="p-4 bg-indigo-500/10 border border-indigo-500/30 rounded-lg mt-4">
                    <h3 class="text-lg font-bold text-indigo-400 mb-2"><i class="fas fa-robot mr-2"></i> AI Teacher Tle (Gemini)</h3>
                    <p class="text-sm text-gray-400 mb-4">เชื่อมต่อกับสมองกล Google Gemini เพื่อให้ครูเติ้ลตอบคำถามนักเรียนได้จริง</p>
                    <div>
                        <label>Google Gemini API Key</label>
                        <input type="text" name="gemini_api_key" value="<?php echo htmlspecialchars(getSetting('gemini_api_key')); ?>" placeholder="AI API Key (AIza...)" style="font-family: monospace;">
                        <p class="text-xs text-gray-500 mt-1">รับ API Key ได้ที่ <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-indigo-400">Google AI Studio</a></p>
                    </div>
                </div>

                <div class="p-4 bg-green-500/10 border border-green-500/30 rounded-lg mt-4">
                    <h3 class="text-lg font-bold text-green-400 mb-2"><i class="fab fa-line mr-2"></i> LINE Notify Integration</h3>
                    <p class="text-sm text-gray-400 mb-4">รับการแจ้งเตือนเหตุสำคัญ (บูลลี่, งานใหม่, ปรึกษา) ผ่านแอป LINE ทันที</p>
                    <div>
                        <label>LINE Notify Token (ครู)</label>
                        <input type="text" name="line_notify_token" value="<?php echo getSetting('line_notify_token'); ?>" placeholder="ใส่ Token จาก LINE Notify ที่นี่...">
                        <p class="text-xs text-gray-500 mt-1">รับ Token ได้ที่ <a href="https://notify-bot.line.me/" target="_blank" class="text-indigo-400">notify-bot.line.me</a></p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">บันทึกการตั้งค่า</button>
                </div>
            </form>
        </div>

        <!-- Announcement Manager -->
        <div class="glass-panel p-6">
            <h2 class="text-2xl font-bold mb-4"><i class="fas fa-bullhorn mr-2"></i> จัดการประกาศ</h2>
            
            <!-- Add New -->
            <form action="settings.php" method="POST" class="mb-6 p-4 border border-gray-700 rounded-lg">
                <input type="hidden" name="add_announcement" value="1">
                <h3 class="font-bold mb-2">เพิ่มประกาศใหม่</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                    <div class="md:col-span-2">
                         <label>หัวข้อ</label>
                        <input type="text" name="title" placeholder="หัวข้อประกาศ..." required>
                    </div>
                    <div>
                         <label>ความสำคัญ</label>
                        <select name="type">
                            <option value="normal">ข่าวทั่วไป</option>
                            <option value="urgent">ด่วน / แจ้งเตือนสำคัญ</option>
                        </select>
                    </div>
                </div>
                
                <label>รายละเอียด</label>
                <textarea name="content" rows="2" placeholder="รายละเอียดประกาศ..." class="mb-2" required></textarea>
                
                <div class="text-right">
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-plus"></i> โพสต์ประกาศ</button>
                </div>
            </form>

            <!-- List -->
            <div class="space-y-2">
                <?php while($news = $announcements->fetch_assoc()): ?>
                    <div class="p-4 border border-gray-700 rounded flex flex-col md:flex-row justify-between items-center gap-4 <?php echo ($news['category'] ?? $news['type'] ?? '') == 'urgent' ? 'bg-red-900/20 border-red-800' : 'bg-gray-800/30'; ?>">
                        <div class="flex-1 <?php echo $news['is_active'] ? '' : 'opacity-50'; ?>">
                            <div class="flex items-center gap-2 mb-1">
                                <?php if(($news['category'] ?? $news['type'] ?? '') == 'urgent'): ?>
                                    <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded font-bold">ด่วน</span>
                                <?php endif; ?>
                                <h4 class="font-bold text-lg"><?php echo $news['title']; ?></h4>
                            </div>
                            <p class="text-gray-300"><?php echo $news['message'] ?? $news['content'] ?? ''; ?></p>
                            <p class="text-xs text-gray-500 mt-2"><i class="fas fa-clock mr-1"></i> <?php echo $news['updated_at'] ?? $news['created_at'] ?? ''; ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="settings.php?toggle_ann=<?php echo $news['id']; ?>" class="btn btn-sm <?php echo $news['is_active'] ? 'btn-primary' : 'bg-gray-600'; ?>" title="สลับสถานะ">
                                <?php echo $news['is_active'] ? '<i class="fas fa-eye"></i> แสดง' : '<i class="fas fa-eye-slash"></i> ซ่อน'; ?>
                            </a>
                            <a href="settings.php?delete_ann=<?php echo $news['id']; ?>" class="btn btn-secondary btn-sm text-red-400 hover:text-red-300" onclick="return confirm('ต้องการลบประกาศนี้หรือไม่?');" title="ลบ">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
