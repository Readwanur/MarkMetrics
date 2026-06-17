<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');


$host = 'localhost';
$dbname = 'markmetrics';
$username = 'root'; 
$password = '';    

$student_id = isset($_SESSION['id']) ? $_SESSION['id'] : '21-44502-1'; 

try {
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

   
    $stmt = $pdo->prepare("
        SELECT u.name, s.cumulative_gpa, s.total_credits_earned, p.total_credits as program_credits 
        FROM users u 
        JOIN students s ON u.id = s.student_id 
        LEFT JOIN programs p ON s.program_id = p.program_id
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $student_id]);
    $studentInfo = $stmt->fetch();

    if (!$studentInfo) {
        throw new Exception("Student not found in database.");
    }


    require_once __DIR__ . '/../../config.php';
    $current_semester = SYSTEM_TERM_DISPLAY;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as enrolled 
        FROM enrollments e 
        JOIN semesters s ON e.semester_id = s.semester_id 
        WHERE e.student_id = :id AND s.display_name = :semester
    ");
    $stmt->execute(['id' => $student_id, 'semester' => $current_semester]);
    $coursesEnrolled = $stmt->fetch()['enrolled'];

    $stmt = $pdo->prepare("
        SELECT ut.title, ut.task_type as type, ut.due_date, c.course_code
        FROM upcoming_tasks ut
        JOIN courses c ON ut.course_code = c.course_code
        JOIN enrollments e ON c.course_code = e.course_code
        WHERE e.student_id = :id AND ut.due_date >= NOW()
        ORDER BY ut.due_date ASC
        LIMIT 3
    ");
    $stmt->execute(['id' => $student_id]);
    $tasks = $stmt->fetchAll();

    $pendingExams = [];
    $colors = ['#ff8a80', '#f97316', '#52525b'];
    foreach ($tasks as $index => $task) {
        $dateObj = new DateTime($task['due_date']);
        $pendingExams[] = [
            'course' => $task['course_code'], 
            'type' => $task['type'],
            'date' => $dateObj->format('M d'),
            'time' => $dateObj->format('h:i A'),
            'color' => $colors[$index % count($colors)]
        ];
    }
    
   
    if (empty($pendingExams)) {
        $pendingExams = [
            [
                'course' => 'Systems Architecture',
                'type' => 'Mid-term Exam',
                'date' => 'Apr 24',
                'time' => '02:30 PM',
                'color' => '#f97316'
            ]
        ];
    }

   
    // Fetch per-semester GPA from actual enrollment data
    $stmt = $pdo->prepare("
        SELECT s.display_name as semester, s.semester_id,
               ROUND(AVG(CASE WHEN gs.points IS NOT NULL THEN gs.points ELSE 0 END), 2) as semester_gpa
        FROM enrollments e
        JOIN semesters s ON e.semester_id = s.semester_id
        LEFT JOIN grading_scale gs ON e.grade = gs.grade
        WHERE e.student_id = :id AND e.grade IS NOT NULL
        GROUP BY s.semester_id, s.display_name
        ORDER BY s.semester_id ASC
    ");
    $stmt->execute(['id' => $student_id]);
    $semester_results = $stmt->fetchAll();

    $velocity = [];
    $sem_counter = 1;
    foreach ($semester_results as $sem) {
        $velocity[] = [
            'semester' => 'SEM ' . $sem_counter,
            'majorGpa' => (float)$sem['semester_gpa'],
            'overallGpa' => (float)$sem['semester_gpa']
        ];
        $sem_counter++;
    }

    // If no semester data exists, show cumulative GPA as a single point
    if (empty($velocity)) {
        $velocity[] = [
            'semester' => 'Current',
            'majorGpa' => (float)$studentInfo['cumulative_gpa'],
            'overallGpa' => (float)$studentInfo['cumulative_gpa']
        ];
    }

   
    $program_credits = $studentInfo['program_credits'] ? (float)$studentInfo['program_credits'] : 138;
    $progress_percentage = round(((float)$studentInfo['total_credits_earned'] / $program_credits) * 100);

    
    $data = [
        'user' => [
            'name' => $studentInfo['name']
        ],
        'gpa' => [
            'cumulative' => (float)$studentInfo['cumulative_gpa'],
            'change' => '+0.03', 
            'percentile' => 'Top 5% of Department'
        ],
        'credits' => [
            'earned' => (int)$studentInfo['total_credits_earned'],
            'total' => (int)$program_credits,
            'progress' => $progress_percentage
        ],
        'semester' => [
            'name' => $current_semester,
            'coursesEnrolled' => (int)$coursesEnrolled,
            'nextExam' => !empty($pendingExams) ? $pendingExams[0]['type'] : 'None'
        ],
        'velocity' => $velocity,
        'pendingExams' => $pendingExams
    ];

    echo json_encode($data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Error details: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
