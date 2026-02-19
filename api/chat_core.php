<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

// === SEND MESSAGE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == 'send_message') {
    $sender_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    $message = sanitize($_POST['message']);
    
    if ($role == 'student') {
        $student_id = $_SESSION['student_id'];
        // Find teacher (Active teacher or default)
        // For now, let's pick the first teacher or a specific one if known.
        // If system has multiple teachers, we might need to select one.
        // Default to teacher_id = 1 for now if not specified.
        $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 1; 
        $sender_role = 'student';
    } else if ($role == 'teacher') {
        $teacher_id = $_SESSION['teacher_id'] ?? 1; // Need teacher_id in session
        $student_id = (int)$_POST['student_id'];
        $sender_role = 'teacher';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Role']);
        exit;
    }

    if (!empty($message)) {
        $sql = "INSERT INTO private_chats (student_id, teacher_id, sender_role, message) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $student_id, $teacher_id, $sender_role, $message);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Empty message']);
    }
    exit;
}

// === GET MESSAGES ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action == 'get_messages') {
    $role = $_SESSION['role'];
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    if ($role == 'student') {
        $student_id = $_SESSION['student_id'];
        // Get messages for this student
        // Assuming single teacher system or filtering by teacher if needed.
        // For simple start: get all chats for this student.
        $sql = "SELECT * FROM private_chats WHERE student_id = ? AND id > ? ORDER BY created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $last_id);
    } else if ($role == 'teacher') {
        $student_id = (int)$_GET['student_id'];
        if (!$student_id) {
            echo json_encode([]); exit;
        }
        $sql = "SELECT * FROM private_chats WHERE student_id = ? AND id > ? ORDER BY created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $last_id);
    } else {
        echo json_encode([]); exit;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_formatted'] = date('H:i', strtotime($row['created_at']));
        $messages[] = $row;
    }
    
    echo json_encode($messages);
    exit;
}

// === GET CHAT LIST (For Teacher) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action == 'get_chat_list' && $_SESSION['role'] == 'teacher') {
    // Get list of students who have chatted
    $sql = "SELECT s.id, s.full_name, s.student_code, s.profile_image, 
            (SELECT message FROM private_chats WHERE student_id = s.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM private_chats WHERE student_id = s.id ORDER BY created_at DESC LIMIT 1) as last_time
            FROM students s
            WHERE s.id IN (SELECT DISTINCT student_id FROM private_chats)
            ORDER BY last_time DESC";
            
    $result = $conn->query($sql);
    $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }
    echo json_encode($list);
    exit;
}
?>
