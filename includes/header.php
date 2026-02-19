<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Base URL - Auto detect
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = "/Smart Classroom Management System"; // Adjust if installed in a subdirectory
$base_url = $protocol . "://" . $host . $path;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . " - " : ""; ?>ห้องเรียนอัจฉริยะ</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    <!-- FontAwesome (You might want to add a CDN link here or download it) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Kanit', sans-serif; /* Use Kanit for Thai support */
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- Navigation Bar for Logged in Users -->
    <nav class="glass-header">
        <div class="logo">
            <a href="<?php echo $base_url; ?>" class="text-xl font-bold flex items-center gap-2">
                <i class="fas fa-graduation-cap text-indigo-500"></i>
                ห้องเรียนอัจฉริยะ
            </a>
        </div>
        <div class="menu flex items-center gap-3">
            <?php
                $nav_profile_img = $_SESSION['profile_image'] ?? '';
                $nav_profile_link = ($_SESSION['role'] == 'teacher') ? $base_url . '/teacher/profile.php' : $base_url . '/student/profile.php';
            ?>
            <a href="<?php echo $nav_profile_link; ?>" class="flex items-center gap-2 hover:opacity-80 transition">
                <?php if (!empty($nav_profile_img)): ?>
                    <img src="<?php echo $base_url . '/' . $nav_profile_img; ?>" alt="Profile" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid rgba(99,102,241,0.5);">
                <?php else: ?>
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;border:2px solid rgba(99,102,241,0.3);">
                        <i class="fas fa-user text-xs text-indigo-400"></i>
                    </div>
                <?php endif; ?>
                <span class="text-gray-300 text-sm"><?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?></span>
            </a>
            <a href="<?php echo $base_url; ?>/auth/logout.php" class="btn btn-secondary btn-sm">ออกจากระบบ</a>
        </div>
    </nav>
<?php endif; ?>

<main class="container mx-auto p-4 pt-24">
