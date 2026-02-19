<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$pageTitle = "เข้าสู่ระบบ";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $errors = [];

    if (empty($username) || empty($password)) {
        $errors[] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    }

    if (empty($errors)) {
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Get Teacher/Student ID
                if ($user['role'] == 'teacher') {
                    $sql_t = "SELECT id, full_name, profile_image FROM teachers WHERE user_id = ?";
                    $stmt_t = $conn->prepare($sql_t);
                    $stmt_t->bind_param("i", $user['id']);
                    $stmt_t->execute();
                    $teacher = $stmt_t->get_result()->fetch_assoc();
                    $_SESSION['profile_id'] = $teacher['id'];
                    $_SESSION['full_name'] = $teacher['full_name'];
                    $_SESSION['profile_image'] = $teacher['profile_image'] ?? '';
                    
                    header("Location: ../teacher/admin_dashboard.php");
                } elseif ($user['role'] == 'student') {
                    $sql_s = "SELECT id, full_name, profile_image FROM students WHERE user_id = ?";
                    $stmt_s = $conn->prepare($sql_s);
                    $stmt_s->bind_param("i", $user['id']);
                    $stmt_s->execute();
                    $student = $stmt_s->get_result()->fetch_assoc();
                    $_SESSION['profile_id'] = $student['id'];
                    $_SESSION['full_name'] = $student['full_name'];
                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['profile_image'] = $student['profile_image'] ?? '';
                    
                    header("Location: ../student/student_dashboard.php");
                } else {
                    // Admin
                     header("Location: ../teacher/admin_dashboard.php");
                }
                exit();
            } else {
                $errors[] = "รหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $errors[] = "ไม่พบชื่อผู้ใช้ในระบบ";
        }
    }
}

require_once '../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full glass-panel p-8 space-y-8 fade-in">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-white">
                เข้าสู่ระบบ
            </h2>
            <p class="mt-2 text-center text-sm text-gray-400">ระบบห้องเรียนอัจฉริยะ</p>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-100 px-4 py-3 rounded relative" role="alert">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST">
            <div class="rounded-md shadow-sm space-y-4">
                <div>
                    <label for="username">ชื่อผู้ใช้</label>
                    <input id="username" name="username" type="text" required placeholder="กรอกชื่อผู้ใช้ของคุณ">
                </div>
                <div>
                    <label for="password">รหัสผ่าน</label>
                    <input id="password" name="password" type="password" required placeholder="กรอกรหัสผ่าน">
                </div>
            </div>

            <div>
                <button type="submit" class="btn btn-primary w-full">
                    เข้าสู่ระบบ
                </button>
            </div>
            
            <div class="text-center">
                <a href="register.php" class="text-sm text-indigo-400 hover:text-indigo-300">
                    ยังไม่มีบัญชี? สมัครสมาชิกใหม่
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
