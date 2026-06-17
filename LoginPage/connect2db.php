<?php

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'MarkMetrics';

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);

require_once __DIR__ . '/../config.php';

?>