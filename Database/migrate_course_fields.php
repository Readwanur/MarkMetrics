<?php
// Database/migrate_course_fields.php
header('Content-Type: text/plain');

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'MarkMetrics';

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

// 1. Add section column to courses if it doesn't exist
$check_section = mysqli_query($conn, "SHOW COLUMNS FROM courses LIKE 'section'");
if (mysqli_num_rows($check_section) == 0) {
    $alter = mysqli_query($conn, "ALTER TABLE courses ADD COLUMN section VARCHAR(10) DEFAULT 'A'");
    if ($alter) {
        echo "Added section column to courses.\n";
    } else {
        echo "Error adding section column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "section column already exists.\n";
}

// 2. Add room_number column to courses if it doesn't exist
$check_room = mysqli_query($conn, "SHOW COLUMNS FROM courses LIKE 'room_number'");
if (mysqli_num_rows($check_room) == 0) {
    $alter = mysqli_query($conn, "ALTER TABLE courses ADD COLUMN room_number VARCHAR(20) DEFAULT NULL");
    if ($alter) {
        echo "Added room_number column to courses.\n";
    } else {
        echo "Error adding room_number column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "room_number column already exists.\n";
}

// 3. Define deterministic hashing function for course section and room number
function getDeterministicSectionAndRoom($course_code) {
    $hash = md5($course_code);
    
    $sections = ['A', 'B', 'C', 'D'];
    $sec_idx = hexdec(substr($hash, 0, 2)) % count($sections);
    $section = $sections[$sec_idx];
    
    $rooms = ['Room-301', 'Room-302', 'Room-401', 'Room-402', 'Room-501', 'Room-502', 'Room-601', 'Room-602'];
    $room_idx = hexdec(substr($hash, 2, 2)) % count($rooms);
    $room_number = $rooms[$room_idx];
    
    return ['section' => $section, 'room_number' => $room_number];
}

// 4. Update all existing courses with deterministic values
$courses_q = mysqli_query($conn, "SELECT course_code FROM courses");
if ($courses_q) {
    while ($row = mysqli_fetch_assoc($courses_q)) {
        $code = $row['course_code'];
        $vals = getDeterministicSectionAndRoom($code);
        $sec = $vals['section'];
        $room = $vals['room_number'];
        
        $update = mysqli_query($conn, "UPDATE courses SET section = '$sec', room_number = '$room' WHERE course_code = '$code'");
        if ($update) {
            echo "Updated course $code -> Section: $sec, Room: $room\n";
        } else {
            echo "Error updating course $code: " . mysqli_error($conn) . "\n";
        }
    }
}

mysqli_close($conn);
echo "Migration completed.\n";
?>
