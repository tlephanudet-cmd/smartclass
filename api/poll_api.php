<?php
// api/poll_api.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check Session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- Teacher Actions ---

if ($action == 'create_poll' && $_SESSION['role'] == 'teacher') {
    $question = sanitize($_POST['question']);
    $options = $_POST['options']; // Array

    if (empty($question) || empty($options)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing data']);
        exit();
    }

    // Close all previous polls first (Optional: single active poll policy)
    $conn->query("UPDATE polls SET status = 'closed'");

    // Create Poll
    $stmt = $conn->prepare("INSERT INTO polls (question, status) VALUES (?, 'open')");
    $stmt->bind_param("s", $question);
    $stmt->execute();
    $poll_id = $conn->insert_id;

    // Create Options
    $stmt_opt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
    foreach ($options as $opt) {
        $clean_opt = sanitize($opt);
        $stmt_opt->bind_param("is", $poll_id, $clean_opt);
        $stmt_opt->execute();
    }

    echo json_encode(['status' => 'success', 'poll_id' => $poll_id]);
    exit();
}

if ($action == 'close_poll' && $_SESSION['role'] == 'teacher') {
    $conn->query("UPDATE polls SET status = 'closed'");
    echo json_encode(['status' => 'success']);
    exit();
}

if ($action == 'get_results' && $_SESSION['role'] == 'teacher') {
    $poll = $conn->query("SELECT * FROM polls WHERE status = 'open' LIMIT 1")->fetch_assoc();
    
    if (!$poll) {
        // Return latest closed poll if no open one, or empty
        $poll = $conn->query("SELECT * FROM polls ORDER BY id DESC LIMIT 1")->fetch_assoc();
    }

    if ($poll) {
        $options_res = $conn->query("SELECT id, option_text FROM poll_options WHERE poll_id = " . $poll['id']);
        $options = [];
        $labels = [];
        $data = [];

        while ($row = $options_res->fetch_assoc()) {
            // Count votes
            $count = $conn->query("SELECT COUNT(*) as c FROM poll_answers WHERE option_id = " . $row['id'])->fetch_assoc()['c'];
            $row['count'] = $count;
            $options[] = $row;
            $labels[] = $row['option_text'];
            $data[] = $count;
        }

        echo json_encode([
            'status' => 'success',
            'poll' => $poll,
            'results' => $options,
            'chart_data' => ['labels' => $labels, 'values' => $data]
        ]);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit();
}

// --- Student Actions ---

if ($action == 'get_active_poll') {
    $poll = $conn->query("SELECT * FROM polls WHERE status = 'open' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    
    if ($poll) {
        // Check if student already voted
        $student_id = $_SESSION['student_id'] ?? 0; // Assuming student_id is set in session on login
        // If not set in session, we need to fetch it from users table. 
        // For Phase 1 Auth, we might not have set it. Let's fix that in login or here.
        if($student_id == 0 && $_SESSION['role'] == 'student') {
            $u_id = $_SESSION['user_id'];
            $s_res = $conn->query("SELECT id FROM students WHERE user_id = $u_id");
            if($s_res->num_rows > 0) {
                $_SESSION['student_id'] = $s_res->fetch_assoc()['id'];
                $student_id = $_SESSION['student_id'];
            }
        }

        $voted = $conn->query("SELECT id FROM poll_answers WHERE poll_id = {$poll['id']} AND student_id = $student_id")->num_rows > 0;

        $options = [];
        $opt_res = $conn->query("SELECT id, option_text FROM poll_options WHERE poll_id = " . $poll['id']);
        while ($r = $opt_res->fetch_assoc()) {
            $options[] = $r;
        }

        echo json_encode([
            'status' => 'success',
            'poll' => $poll,
            'options' => $options,
            'voted' => $voted
        ]);
    } else {
        echo json_encode(['status' => 'no_poll']);
    }
    exit();
}

if ($action == 'vote' && $_SESSION['role'] == 'student') {
    $poll_id = (int)$_POST['poll_id'];
    $option_id = (int)$_POST['option_id'];
    $student_id = $_SESSION['student_id'];

    // Double check not voted
    $check = $conn->query("SELECT id FROM poll_answers WHERE poll_id = $poll_id AND student_id = $student_id");
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO poll_answers (poll_id, student_id, option_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $poll_id, $student_id, $option_id);
        $stmt->execute();
        
        // Add XP?
        // $conn->query("UPDATE students SET xp = xp + 5 WHERE id = $student_id");
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Already voted']);
    }
    exit();
}

?>
