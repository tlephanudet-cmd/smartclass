<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Database Configuration
// ⚠️ สำหรับ localhost (XAMPP) ใช้ค่านี้
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'if0_41193517_smartclass');

// ⚠️ สำหรับ InfinityFree ให้ uncomment ข้างล่างแทน:
// define('DB_HOST', 'sql303.infinityfree.com');
// define('DB_USER', 'if0_41193517');
// define('DB_PASS', '0819896617Tle');
// define('DB_NAME', 'if0_41193517_smartclass');

// Base URL Configuration
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/Smart Classroom Management System');
    // ⚠️ เมื่ออัปโหลดขึ้น InfinityFree ให้เปลี่ยนเป็น URL จริง เช่น:
    // define('BASE_URL', 'http://yourdomain.infinityfreeapp.com');
}

// Connect to Database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Charset to UTF-8
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");

// Start Session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper Function: Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
}
?>
