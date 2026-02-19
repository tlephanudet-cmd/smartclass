<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action == 'get_messages' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $result = $conn->query("SELECT c.*, s.full_name FROM consultations c LEFT JOIN students s ON c.student_id = s.id WHERE c.id = $id");
        $con = $result ? $result->fetch_assoc() : null;
        
        if (!$con) {
            echo json_encode(['error' => 'not_found']);
            exit;
        }
        
        $replies = [];
        $res = $conn->query("SELECT * FROM consultation_replies WHERE consultation_id = $id ORDER BY created_at ASC");
        if ($res) {
            while($r = $res->fetch_assoc()) {
                $replies[] = $r;
            }
        }

        // Fallback: support both 'topic' and 'topic_category' column names
        $topic_value = $con['topic'] ?? $con['topic_category'] ?? 'ไม่มีหัวข้อ';
        
        $isAnon = !empty($con['is_anonymous']);
        $realName = $con['full_name'] ?? 'ไม่ทราบชื่อ';

        echo json_encode([
            'student_name' => $isAnon ? 'ผู้ไม่ประสงค์ออกนาม 🕵️' : $realName,
            'real_name' => $realName,
            'is_anonymous' => $isAnon,
            'topic' => $topic_value,
            'message' => $con['message'] ?? '',
            'created_at' => $con['created_at'] ?? '',
            'replies' => $replies
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] == 'reply') {
        $cid = (int)$_POST['consultation_id'];
        $msg = sanitize($_POST['message']);
        $role = $_POST['sender_role'];
        $uid = $_SESSION['user_id']; // This is actually user_id, but table structure says sender_id. Ideally stick to user_id or specific id.
        // For simplicity let's use user_id, assuming logic knows who it is.
        
        $stmt = $conn->prepare("INSERT INTO consultation_replies (consultation_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $cid, $uid, $role, $msg);
        
        if($stmt->execute()) {
            // Update status
            if ($role == 'teacher') {
                $conn->query("UPDATE consultations SET status = 'resolved' WHERE id = $cid");
            }
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }
}
?>
