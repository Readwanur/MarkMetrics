<?php
include('../LoginPage/connect2db.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting Grading Scale Update...\n";

// Disable foreign key checks temporarily to avoid dependency issues if any
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

// Truncate grading_scale
$truncate = mysqli_query($conn, "TRUNCATE TABLE grading_scale");
if ($truncate) {
    echo "Truncated grading_scale table successfully.\n";
} else {
    echo "Failed to truncate grading_scale: " . mysqli_error($conn) . "\n";
}

// Insert new grading scale rows from the image
$inserts = [
    ['A',  90.00, 100.00, 4.00],
    ['A-', 86.00, 89.99,  3.67],
    ['B+', 82.00, 85.99,  3.33],
    ['B',  78.00, 81.99,  3.00],
    ['B-', 74.00, 77.99,  2.67],
    ['C+', 70.00, 73.99,  2.33],
    ['C',  66.00, 69.99,  2.00],
    ['C-', 62.00, 65.99,  1.67],
    ['D+', 58.00, 61.99,  1.33],
    ['D',  55.00, 57.99,  1.00],
    ['F',  0.00,  54.99,  0.00]
];

foreach ($inserts as $row) {
    $grade = $row[0];
    $min = $row[1];
    $max = $row[2];
    $pts = $row[3];
    
    $q = mysqli_query($conn, "INSERT INTO grading_scale (grade, min_score, max_score, points) VALUES ('$grade', $min, $max, $pts)");
    if ($q) {
        echo "Inserted Grade: $grade ($min - $max) -> $pts points\n";
    } else {
        echo "Failed to insert Grade $grade: " . mysqli_error($conn) . "\n";
    }
}

// Re-enable foreign key checks
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

echo "Grading Scale Update Complete!\n";
?>
