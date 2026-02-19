<?php
// api/check_mood.php - to check/submit mood
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) exit();

// Ensure student_id
$student_id = $_SESSION['student_id'] ?? 0;
if ($student_id == 0) {
     $uid = $_SESSION['user_id'];
     $s = $conn->query("SELECT id FROM students WHERE user_id = $uid")->fetch_assoc();
     if ($s) $student_id = $s['id'];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'check_today') {
    // Check if logged today
    $today = date('Y-m-d');
    $check = $conn->query("SELECT id FROM mood_logs WHERE student_id = $student_id AND DATE(created_at) = '$today'");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'logged']);
    } else {
        echo json_encode(['status' => 'not_logged']);
    }
    exit();
}

if ($action == 'log_mood') {
    $mood = (int)$_POST['mood']; // 1-5
    $note = sanitize($_POST['note']); // Optional
    
    $stmt = $conn->prepare("INSERT INTO mood_logs (student_id, mood_level, note) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $student_id, $mood, $note);
    $stmt->execute();
    
    echo json_encode(['status' => 'success']);
    exit();
}
?>
