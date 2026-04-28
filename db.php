<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "kitere_cbc_exam_system";
 
$conn = new mysqli($servername,$username,$password, $dbname);

if ($conn->connect_error) {
    die("connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    ?>


