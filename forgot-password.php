<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $check_sql = "SELECT id, username FROM users WHERE email = '$email'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        // In a real system, you would send an email with reset link
        // For demo purposes, we'll just show a message
        $_SESSION['message'] = "✅ Password reset link has been sent to your email! (Demo feature)";
        $_SESSION['message_type'] = "success";
        header("Location: login.html");
        exit();
    } else {
        $_SESSION['message'] = "❌ Email not found in our system!";
        $_SESSION['message_type'] = "error";
        header("Location: forgot-password.html");
        exit();
    }
}
?>