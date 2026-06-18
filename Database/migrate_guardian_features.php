<?php
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'markmetrics';

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 1. Add mothers_name
$sql1 = "ALTER TABLE students ADD COLUMN mothers_name VARCHAR(255) DEFAULT 'Unknown' AFTER date_of_birth";
if(mysqli_query($conn, $sql1)) echo "Added mothers_name\n";
else echo "Error adding mothers_name: " . mysqli_error($conn) . "\n";

// 2. Add student_finances
$sql2 = "CREATE TABLE IF NOT EXISTS student_finances (
    finance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    total_billed DECIMAL(10,2) DEFAULT 0,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    waived_amount DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_finance (student_id)
)";
if(mysqli_query($conn, $sql2)) echo "Created student_finances\n";
else echo "Error creating student_finances: " . mysqli_error($conn) . "\n";

// 3. Add class_schedules
$sql3 = "CREATE TABLE IF NOT EXISTS class_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    day_of_week ENUM('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(20),
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE
)";
if(mysqli_query($conn, $sql3)) echo "Created class_schedules\n";
else echo "Error creating class_schedules: " . mysqli_error($conn) . "\n";

// 4. Seed Data
$sql4 = "
UPDATE students SET mothers_name = 'Shake Hasina' WHERE student_id = '0112330784';
UPDATE students SET mothers_name = 'Ayesha Begum' WHERE student_id = '21-44502-1';
INSERT IGNORE INTO student_finances (student_id, total_billed, paid_amount, waived_amount) VALUES 
('0112330784', 12450.00, 10210.00, 1000.00),
('21-44502-1', 45000.00, 45000.00, 0.00);

INSERT IGNORE INTO class_schedules (course_code, day_of_week, start_time, end_time, room_number) VALUES 
('CSE-4165', 'Wednesday', '09:00:00', '10:30:00', 'Room 201'),
('CSE-4402', 'Tuesday', '13:00:00', '14:30:00', 'Room 304'),
('CSE-2216', 'Saturday', '10:00:00', '13:00:00', 'Lab 2'),
('CSE-3301', 'Sunday', '11:00:00', '12:30:00', 'Room 101'),
('MAT-2201', 'Monday', '08:00:00', '09:30:00', 'Room 105'),
('CSE-4411', 'Thursday', '14:00:00', '15:30:00', 'Room 405');
";

if(mysqli_multi_query($conn, $sql4)) echo "Seeded Data\n";
else echo "Error seeding data: " . mysqli_error($conn) . "\n";

echo "Migration complete.\n";
?>
