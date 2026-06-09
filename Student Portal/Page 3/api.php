<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'markmetrics';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '21-44502-1';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';

if ($action === 'search_students') {
    $query = isset($_GET['q']) ? $_GET['q'] : '';
    
    if ($user_role === 'student') {
        $stmt = $conn->prepare("
            SELECT u.id, u.name 
            FROM users u 
            WHERE u.id = ? AND u.role = 'student'
        ");
        $stmt->bind_param("s", $user_id);
    } else {
        $stmt = $conn->prepare("
            SELECT u.id, u.name 
            FROM users u 
            WHERE u.role = 'student' AND (u.name LIKE ? OR u.id LIKE ?) 
            LIMIT 10
        ");
        $searchTerm = "%$query%";
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    echo json_encode($students);
    exit;
}
if ($action === 'get_student_courses') {
    $student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
    
    // Security check: if the user is a student, they can only view their own courses
    if ($user_role === 'student' && $student_id !== $user_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT e.course_code, c.course_name, e.grade as current_grade
        FROM enrollments e
        JOIN courses c ON e.course_code = c.course_code
        WHERE e.student_id = ?
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    echo json_encode($courses);
    exit;
}

if ($action === 'submit_request') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['error' => 'No data provided']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO grade_correction_requests 
        (student_id, course_code, current_grade, new_grade, justification, requested_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssss", 
        $data['student_id'], 
        $data['course_code'], 
        $data['current_grade'], 
        $data['new_grade'], 
        $data['justification'], 
        $user_id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'request_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to submit request']);
    }
    exit;
}

if ($action === 'get_registry_data') {
    if ($user_role === 'student') {
        $stmt = $conn->prepare("
            SELECT r.*, c.course_name, u.name as teacher_name
            FROM grade_correction_requests r
            JOIN courses c ON r.course_code = c.course_code
            JOIN users u ON r.requested_by = u.id
            WHERE r.student_id = ? OR r.requested_by = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stmt->bind_param("ss", $user_id, $user_id);
    } else {
        $stmt = $conn->prepare("
            SELECT r.*, c.course_name, u.name as teacher_name
            FROM grade_correction_requests r
            JOIN courses c ON r.course_code = c.course_code
            JOIN users u ON r.requested_by = u.id
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $activity = [];
    while ($row = $result->fetch_assoc()) {
        $activity[] = $row;
    }

    if ($user_role === 'student') {
        $stats_stmt = $conn->prepare("SELECT COUNT(*) as pending FROM grade_correction_requests WHERE status = 'Pending' AND (student_id = ? OR requested_by = ?)");
        $stats_stmt->bind_param("ss", $user_id, $user_id);
        $stats_stmt->execute();
        $pending_count = $stats_stmt->get_result()->fetch_assoc()['pending'];

        $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM grade_correction_requests WHERE status IN ('Approved', 'Rejected') AND (student_id = ? OR requested_by = ?)");
        $total_stmt->bind_param("ss", $user_id, $user_id);
        $total_stmt->execute();
        $total_resolved = $total_stmt->get_result()->fetch_assoc()['total'];

        $approved_stmt = $conn->prepare("SELECT COUNT(*) as approved FROM grade_correction_requests WHERE status = 'Approved' AND (student_id = ? OR requested_by = ?)");
        $approved_stmt->bind_param("ss", $user_id, $user_id);
        $approved_stmt->execute();
        $approved_count = $approved_stmt->get_result()->fetch_assoc()['approved'];
    } else {
        $stats_stmt = $conn->prepare("SELECT COUNT(*) as pending FROM grade_correction_requests WHERE status = 'Pending'");
        $stats_stmt->execute();
        $pending_count = $stats_stmt->get_result()->fetch_assoc()['pending'];

        $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM grade_correction_requests WHERE status IN ('Approved', 'Rejected')");
        $total_stmt->execute();
        $total_resolved = $total_stmt->get_result()->fetch_assoc()['total'];

        $approved_stmt = $conn->prepare("SELECT COUNT(*) as approved FROM grade_correction_requests WHERE status = 'Approved'");
        $approved_stmt->execute();
        $approved_count = $approved_stmt->get_result()->fetch_assoc()['approved'];
    }

    $approval_rate = ($total_resolved > 0) ? round(($approved_count / $total_resolved) * 100) : 100;

    echo json_encode([
        'activity' => $activity,
        'stats' => [
            'pending_tasks' => $pending_count,
            'approval_rate' => $approval_rate . '%'
        ]
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
