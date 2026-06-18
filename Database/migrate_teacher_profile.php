<?php
include(__DIR__ . '/../LoginPage/connect2db.php');

echo "Starting migration: adding columns to teachers table...\n";

// Check if initials column exists
$check_initials = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'initials'");
if (mysqli_num_rows($check_initials) == 0) {
    echo "Adding initials column to teachers table...\n";
    $alter1 = mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN initials VARCHAR(20) DEFAULT NULL");
    if ($alter1) {
        echo "initials column added successfully.\n";
    } else {
        echo "Error adding initials column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "initials column already exists.\n";
}

// Check if education_background column exists
$check_edu = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'education_background'");
if (mysqli_num_rows($check_edu) == 0) {
    echo "Adding education_background column to teachers table...\n";
    $alter2 = mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN education_background TEXT DEFAULT NULL");
    if ($alter2) {
        echo "education_background column added successfully.\n";
    } else {
        echo "Error adding education_background column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "education_background column already exists.\n";
}

echo "Migration finished.\n";
?>
