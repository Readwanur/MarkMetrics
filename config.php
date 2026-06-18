<?php
// System-wide Academic Term configuration

// Option A: Hardcoded Simulated Semester (Default - currently Spring 2026)
// To change the term system-wide, simply modify these constants (e.g. 'Summer' and '2026')
define('SYSTEM_SEMESTER_NAME', 'Spring'); // e.g., 'Spring', 'Summer', 'Fall'
define('SYSTEM_YEAR', '2026');           // e.g., '2026'

// Option B: Dynamic Semester based on the calendar date (uncomment if you want automatic time-based progression)
/*
$current_month = (int)date('n');
$current_year = date('Y');
if ($current_month >= 1 && $current_month <= 4) {
    define('SYSTEM_SEMESTER_NAME', 'Spring');
} elseif ($current_month >= 5 && $current_month <= 8) {
    define('SYSTEM_SEMESTER_NAME', 'Summer');
} else {
    define('SYSTEM_SEMESTER_NAME', 'Fall');
}
define('SYSTEM_YEAR', $current_year);
*/

// Helper function to get the current semester ID from the database
function getCurrentSemesterId($conn) {
    $sem_name = SYSTEM_SEMESTER_NAME;
    $year = SYSTEM_YEAR;
    
    // Check if this semester exists in the database
    $stmt = mysqli_prepare($conn, "SELECT semester_id FROM semesters WHERE semester_name = ? AND academic_year = ?");
    mysqli_stmt_bind_param($stmt, "ss", $sem_name, $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return (int)$row['semester_id'];
    } else {
        // If it doesn't exist, insert it dynamically so the system doesn't break
        $insert_stmt = mysqli_prepare($conn, "INSERT INTO semesters (semester_name, academic_year) VALUES (?, ?)");
        mysqli_stmt_bind_param($insert_stmt, "ss", $sem_name, $year);
        mysqli_stmt_execute($insert_stmt);
        return (int)mysqli_insert_id($conn);
    }
}

define('SYSTEM_TERM_DISPLAY', SYSTEM_SEMESTER_NAME . ' ' . SYSTEM_YEAR);
?>
