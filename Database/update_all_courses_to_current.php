<?php
include(__DIR__ . '/../LoginPage/connect2db.php');

$current_semester_id = getCurrentSemesterId($conn);

// 1. Update all courses to current semester
$sql1 = "UPDATE courses SET semester_id = $current_semester_id";
if (mysqli_query($conn, $sql1)) {
    echo "All courses updated to semester ID: $current_semester_id\n";
} else {
    echo "Error updating courses: " . mysqli_error($conn) . "\n";
}

// 2. Update all enrollments to current semester
$sql2 = "UPDATE enrollments SET semester_id = $current_semester_id";
if (mysqli_query($conn, $sql2)) {
    echo "All enrollments updated to semester ID: $current_semester_id\n";
} else {
    echo "Error updating enrollments: " . mysqli_error($conn) . "\n";
}
?>
