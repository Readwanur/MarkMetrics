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

$student_id = isset($_SESSION['id']) ? $_SESSION['id'] : '21-44502-1';
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_profile') {
    $stmt = $conn->prepare("
        SELECT 
            u.name, 
            p.name as major, 
            s.academic_year, 
            s.enrollment_term, 
            s.cumulative_gpa, 
            s.total_credits_earned, 
            p.total_credits
        FROM students s
        JOIN users u ON s.student_id = u.id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.student_id = ?
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    
    if (!$profile) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    
    $profile['major_gpa'] = $profile['cumulative_gpa']; 
    $profile['deans_list'] = 4; 
    $profile['major_completion'] = 48;
    $profile['general_electives'] = 100; 
    
    echo json_encode($profile);
    exit;
}

if ($action === 'get_enrollments') {
    $stmt = $conn->prepare("
        SELECT 
            s.semester_name as term, 
            s.display_name as dates,
            e.status as enrollment_status,
            e.course_code, 
            c.course_name, 
            c.credits, 
            e.grade, 
            e.points, 
            e.status as course_status
        FROM enrollments e
        JOIN courses c ON e.course_code = c.course_code
        JOIN semesters s ON e.semester_id = s.semester_id
        WHERE e.student_id = ?
        ORDER BY s.semester_id DESC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $enrollments_by_term = [];
    while ($row = $result->fetch_assoc()) {
        $term = $row['term'];
        if (!isset($enrollments_by_term[$term])) {
            $enrollments_by_term[$term] = [
                'term' => $term,
                'term_gpa' => '0.00',
                'credits_registered' => 0,
                'status' => $row['enrollment_status'] === 'Completed' ? 'COMPLETED' : 'RUNNING',
                'dates' => $row['dates'] ?: '',
                'courses' => [],
                'total_points' => 0
            ];
        }
        
        $credits = (float)$row['credits'];
        $points = (float)$row['points'];
        
        $enrollments_by_term[$term]['courses'][] = [
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'credits' => $row['credits'],
            'grade' => $row['grade'] ?: '--',
            'points' => $row['points'] ?: '--',
            'status' => $row['course_status'] === 'Completed' ? 'Completed' : 'Running Course'
        ];
        
        $enrollments_by_term[$term]['credits_registered'] += $credits;
        $enrollments_by_term[$term]['total_points'] += ($credits * $points);
    }
    
    // Calculate GPA
    foreach ($enrollments_by_term as &$term_data) {
        if ($term_data['credits_registered'] > 0) {
            $term_gpa = $term_data['total_points'] / $term_data['credits_registered'];
            $term_data['term_gpa'] = number_format($term_gpa, 2);
        }
        unset($term_data['total_points']);
    }
    
    echo json_encode(array_values($enrollments_by_term));
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
