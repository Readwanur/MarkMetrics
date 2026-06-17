<?php
include(__DIR__ . '/../LoginPage/connect2db.php');

function generateTeacherInitials($name) {
    $clean_name = preg_replace('/^(prof\.|dr\.|mr\.|mrs\.|ms\.)\s+/i', '', $name);
    $clean_name = preg_replace('/[^a-zA-Z\s]/', '', $clean_name);
    $words = array_values(array_filter(explode(' ', $clean_name)));
    
    $num_words = count($words);
    $initials = '';
    
    if ($num_words === 0) {
        return 'Temp';
    }
    
    if ($num_words === 1) {
        $initials = ucfirst(strtolower(substr($words[0], 0, 4)));
    } elseif ($num_words === 2) {
        $initials = ucfirst(strtolower(substr($words[0], 0, 2))) . ucfirst(strtolower(substr($words[1], 0, 2)));
    } elseif ($num_words === 3) {
        $initials = ucfirst(strtolower(substr($words[0], 0, 2))) . ucfirst(strtolower(substr($words[1], 0, 2))) . ucfirst(strtolower(substr($words[2], 0, 2)));
    } elseif ($num_words === 4) {
        $initials = ucfirst(strtolower(substr($words[0], 0, 1))) . ucfirst(strtolower(substr($words[1], 0, 1))) . ucfirst(strtolower(substr($words[2], 0, 1))) . ucfirst(strtolower(substr($words[3], 0, 1)));
    } elseif ($num_words === 5) {
        $initials = ucfirst(strtolower(substr($words[0], 0, 1))) . ucfirst(strtolower(substr($words[1], 0, 1))) . ucfirst(strtolower(substr($words[2], 0, 1))) . ucfirst(strtolower(substr($words[3], 0, 1))) . ucfirst(strtolower(substr($words[4], 0, 1)));
    } else {
        for ($i = 0; $i < min($num_words, 6); $i++) {
            $initials .= ucfirst(strtolower(substr($words[$i], 0, 1)));
        }
    }
    
    return $initials;
}

$teachers_q = mysqli_query($conn, "SELECT u.id, u.name FROM users u JOIN teachers t ON u.id = t.teacher_id WHERE u.role = 'teacher'");
if ($teachers_q) {
    while ($row = mysqli_fetch_assoc($teachers_q)) {
        $name = $row['name'];
        $teacher_id = $row['id'];
        $computed_initials = generateTeacherInitials($name);
        
        mysqli_query($conn, "UPDATE teachers SET initials = '$computed_initials' WHERE teacher_id = '$teacher_id'");
        echo "Updated teacher $teacher_id ($name) initials to: $computed_initials\n";
    }
}
?>
