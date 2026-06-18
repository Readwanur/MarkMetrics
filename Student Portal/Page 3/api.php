<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'markmetrics';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Auto-migration: make requested_by nullable for student-initiated requests
$conn->query("ALTER TABLE grade_correction_requests MODIFY COLUMN requested_by VARCHAR(50) NULL");

$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '21-44502-1';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';

// ─── Get logged-in student info ───
if ($action === 'get_my_info') {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, s.cumulative_gpa, s.total_credits_earned,
               p.name as program_name, p.program_code
        FROM users u
        JOIN students s ON u.id = s.student_id
        LEFT JOIN programs p ON s.program_id = p.program_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();

    if (!$info) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    echo json_encode($info);
    exit;
}

// ─── Get student's enrolled courses with grades ───
if ($action === 'get_my_courses') {
    $scope = isset($_GET['scope']) ? $_GET['scope'] : 'all';

    if ($scope === 'correction') {
        // Only return courses from the current and previous semester
        // Step 1: Find the student's two most recent semesters
        $sem_stmt = $conn->prepare("
            SELECT DISTINCT s.semester_id
            FROM enrollments e
            JOIN semesters s ON e.semester_id = s.semester_id
            WHERE e.student_id = ?
            ORDER BY s.semester_id DESC
            LIMIT 2
        ");
        $sem_stmt->bind_param("s", $user_id);
        $sem_stmt->execute();
        $sem_result = $sem_stmt->get_result();
        $eligible_ids = [];
        while ($row = $sem_result->fetch_assoc()) {
            $eligible_ids[] = (int)$row['semester_id'];
        }

        if (empty($eligible_ids)) {
            echo json_encode([]);
            exit;
        }

        // Step 2: Fetch courses only from those semesters
        $placeholders = implode(',', array_fill(0, count($eligible_ids), '?'));
        $types = str_repeat('i', count($eligible_ids));

        $stmt = $conn->prepare("
            SELECT e.enrollment_id, e.course_code, c.course_name, c.credits,
                   e.grade as current_grade, e.points, e.status,
                   s.display_name as semester_name, s.semester_id,
                   u.name as teacher_name
            FROM enrollments e
            JOIN courses c ON e.course_code = c.course_code
            JOIN semesters s ON e.semester_id = s.semester_id
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE e.student_id = ? AND e.semester_id IN ($placeholders)
            ORDER BY s.semester_id DESC, c.course_name ASC
        ");
        $bind_types = 's' . $types;
        $bind_params = array_merge([$user_id], $eligible_ids);
        $stmt->bind_param($bind_types, ...$bind_params);
    } else {
        // Return all courses (used for withdrawal which only filters ongoing in JS)
        $stmt = $conn->prepare("
            SELECT e.enrollment_id, e.course_code, c.course_name, c.credits,
                   e.grade as current_grade, e.points, e.status,
                   s.display_name as semester_name, s.semester_id,
                   u.name as teacher_name
            FROM enrollments e
            JOIN courses c ON e.course_code = c.course_code
            JOIN semesters s ON e.semester_id = s.semester_id
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE e.student_id = ?
            ORDER BY s.semester_id DESC, c.course_name ASC
        ");
        $stmt->bind_param("s", $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    echo json_encode($courses);
    exit;
}

// ─── Submit grade correction request ───
if ($action === 'submit_request') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'No data provided']);
        exit;
    }

    $student_id = $data['student_id'] ?? '';
    $course_code = $data['course_code'] ?? '';
    $current_grade = $data['current_grade'] ?? '';
    $new_grade = $data['new_grade'] ?? '';
    $justification = $data['justification'] ?? '';

    // Security: students can only submit for themselves
    if ($user_role === 'student' && $student_id !== $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized: You can only submit requests for yourself']);
        exit;
    }

    if (empty($course_code) || empty($new_grade) || empty($justification)) {
        http_response_code(400);
        echo json_encode(['error' => 'Please fill in all required fields']);
        exit;
    }

    // Check for duplicate pending request
    $dup_stmt = $conn->prepare("
        SELECT request_id FROM grade_correction_requests
        WHERE student_id = ? AND course_code = ? AND status = 'Pending'
    ");
    $dup_stmt->bind_param("ss", $student_id, $course_code);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
    if ($dup_result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'A pending request already exists for this course']);
        exit;
    }

    // For student-initiated requests, requested_by is NULL (FK references teachers table)
    $stmt = $conn->prepare("
        INSERT INTO grade_correction_requests
        (student_id, course_code, current_grade, new_grade, justification, requested_by)
        VALUES (?, ?, ?, ?, ?, NULL)
    ");
    $stmt->bind_param(
        "sssss",
        $student_id,
        $course_code,
        $current_grade,
        $new_grade,
        $justification
    );

    if ($stmt->execute()) {
        // Log the action
        $log_desc = "Student $user_id submitted grade correction for $course_code: $current_grade → $new_grade";
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, description, target_entity_type, target_entity_id) VALUES (?, 'Grade Correction Request', ?, 'grade_correction_requests', ?)");
        $req_id = (string)$stmt->insert_id;
        $log_stmt->bind_param("sss", $user_id, $log_desc, $req_id);
        $log_stmt->execute();

        echo json_encode(['success' => true, 'request_id' => $stmt->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit request: ' . $stmt->error]);
    }
    exit;
}

// ─── Withdraw from a course ───
if ($action === 'withdraw_course') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'No data provided']);
        exit;
    }

    $enrollment_id = $data['enrollment_id'] ?? 0;
    $reason = $data['reason'] ?? '';

    if (empty($enrollment_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Enrollment ID is required']);
        exit;
    }

    // Verify this enrollment belongs to the logged-in student and is currently Ongoing
    $verify_stmt = $conn->prepare("
        SELECT e.enrollment_id, e.course_code, e.status, c.course_name, e.semester_id
        FROM enrollments e
        JOIN courses c ON e.course_code = c.course_code
        WHERE e.enrollment_id = ? AND e.student_id = ?
    ");
    $verify_stmt->bind_param("is", $enrollment_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $enrollment = $verify_result->fetch_assoc();

    if (!$enrollment) {
        http_response_code(404);
        echo json_encode(['error' => 'Enrollment not found or does not belong to you']);
        exit;
    }

    if ($enrollment['status'] !== 'Ongoing') {
        http_response_code(400);
        echo json_encode(['error' => 'Only ongoing courses can be withdrawn. This course is: ' . $enrollment['status']]);
        exit;
    }

    // Check if a pending withdrawal request already exists for this course
    $check_stmt = $conn->prepare("SELECT request_id FROM withdraw_requests WHERE student_id = ? AND course_code = ? AND status = 'Pending'");
    $check_stmt->bind_param("ss", $user_id, $enrollment['course_code']);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();
    if ($check_res->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'A pending withdraw request already exists for this course']);
        exit;
    }

    // Insert withdrawal request with Pending status
    $insert_stmt = $conn->prepare("INSERT INTO withdraw_requests (student_id, course_code, semester_id, status) VALUES (?, ?, ?, 'Pending')");
    $insert_stmt->bind_param("ssi", $user_id, $enrollment['course_code'], $enrollment['semester_id']);

    if ($insert_stmt->execute()) {
        $request_id = $insert_stmt->insert_id;
        // Log the withdrawal request submission
        $log_desc = "Student $user_id requested course withdrawal from {$enrollment['course_code']} ({$enrollment['course_name']})";
        if (!empty($reason)) {
            $log_desc .= ". Reason: $reason";
        }
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, description, target_entity_type, target_entity_id) VALUES (?, 'Course Withdrawal Request', ?, 'withdraw_requests', ?)");
        $req_id_str = (string)$request_id;
        $log_stmt->bind_param("sss", $user_id, $log_desc, $req_id_str);
        $log_stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => "Withdrawal request for {$enrollment['course_name']} submitted successfully and is pending approval."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process withdrawal request']);
    }
    exit;
}

// ─── Get registry data (activity feed + stats) ───
if ($action === 'get_registry_data') {
    // Get grade correction requests for this student
    $stmt = $conn->prepare("
        SELECT r.request_id, r.student_id, r.course_code, r.current_grade, r.new_grade,
               r.justification, r.status, r.created_at, r.resolved_at,
               c.course_name,
               COALESCE(u.name, 'Student Request') as requester_name
        FROM grade_correction_requests r
        JOIN courses c ON r.course_code = c.course_code
        LEFT JOIN users u ON r.requested_by = u.id
        WHERE r.student_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activity = [];
    while ($row = $result->fetch_assoc()) {
        $activity[] = $row;
    }

    // Get recent withdrawals for the activity feed
    $wdraw_stmt = $conn->prepare("
        SELECT al.log_id, al.action_type, al.description, al.created_at,
               al.target_entity_id
        FROM audit_logs al
        WHERE al.user_id = ? AND al.action_type = 'Course Withdrawal'
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    $wdraw_stmt->bind_param("s", $user_id);
    $wdraw_stmt->execute();
    $wdraw_result = $wdraw_stmt->get_result();
    $withdrawals = [];
    while ($row = $wdraw_result->fetch_assoc()) {
        $withdrawals[] = $row;
    }

    // Stats
    $pending_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM grade_correction_requests WHERE student_id = ? AND status = 'Pending'");
    $pending_stmt->bind_param("s", $user_id);
    $pending_stmt->execute();
    $pending_count = $pending_stmt->get_result()->fetch_assoc()['cnt'];

    $total_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM grade_correction_requests WHERE student_id = ? AND status IN ('Approved', 'Rejected')");
    $total_stmt->bind_param("s", $user_id);
    $total_stmt->execute();
    $total_resolved = $total_stmt->get_result()->fetch_assoc()['cnt'];

    $approved_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM grade_correction_requests WHERE student_id = ? AND status = 'Approved'");
    $approved_stmt->bind_param("s", $user_id);
    $approved_stmt->execute();
    $approved_count = $approved_stmt->get_result()->fetch_assoc()['cnt'];

    $approval_rate = ($total_resolved > 0) ? round(($approved_count / $total_resolved) * 100) : 100;

    // Count ongoing courses (for withdrawal context)
    $ongoing_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM enrollments WHERE student_id = ? AND status = 'Ongoing'");
    $ongoing_stmt->bind_param("s", $user_id);
    $ongoing_stmt->execute();
    $ongoing_count = $ongoing_stmt->get_result()->fetch_assoc()['cnt'];

    echo json_encode([
        'activity' => $activity,
        'withdrawals' => $withdrawals,
        'stats' => [
            'pending_requests' => (int)$pending_count,
            'approval_rate' => $approval_rate . '%',
            'ongoing_courses' => (int)$ongoing_count
        ]
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);

// ─── Get pending notification count for the bell badge ───
if ($action === 'get_notifications') {
    // Grade correction requests that have been resolved (Approved/Rejected) since last seen
    $noti_stmt = $conn->prepare("
        SELECT r.request_id, r.course_code, r.status, r.current_grade, r.new_grade,
               r.resolved_at, c.course_name, u.name as teacher_name
        FROM grade_correction_requests r
        JOIN courses c ON r.course_code = c.course_code
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE r.student_id = ? AND r.status IN ('Approved', 'Rejected')
        ORDER BY r.resolved_at DESC LIMIT 10
    ");
    $noti_stmt->bind_param("s", $user_id);
    $noti_stmt->execute();
    $noti_result = $noti_stmt->get_result();
    $notifications = [];
    while ($row = $noti_result->fetch_assoc()) {
        $notifications[] = $row;
    }

    // Pending count
    $pend_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM grade_correction_requests WHERE student_id = ? AND status = 'Pending'");
    $pend_stmt->bind_param("s", $user_id);
    $pend_stmt->execute();
    $pending_count = $pend_stmt->get_result()->fetch_assoc()['cnt'];

    echo json_encode(['notifications' => $notifications, 'pending_count' => (int)$pending_count]);
    exit;
}
?>
