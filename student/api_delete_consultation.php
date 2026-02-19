<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid consultation ID']);
    exit();
}

$role = $_SESSION['role'] ?? '';
$student_id = $_SESSION['student_id'] ?? 0;

// Permission check: students can only delete their own messages
if ($role === 'student') {
    $check = $conn->query("SELECT id FROM consultations WHERE id = $id AND student_id = $student_id");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ลบข้อความนี้']);
        exit();
    }
} elseif ($role !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit();
}

// Delete replies first, then the consultation
$conn->query("DELETE FROM consultation_replies WHERE consultation_id = $id");
$result = $conn->query("DELETE FROM consultations WHERE id = $id");

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'ลบข้อความเรียบร้อยแล้ว']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
}
?>
