<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Check if user exists (by username or email)
    $sql = "SELECT * FROM users WHERE username = '$username' OR email = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Password is correct
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            
            // Update last login time
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
            $conn->query($update_sql);
            
            // Set remember me cookie (30 days)
            if ($remember) {
                setcookie('user_id', $user['id'], time() + (86400 * 30), "/");
                setcookie('username', $user['username'], time() + (86400 * 30), "/");
            }
            
            $_SESSION['message'] = "✅ Welcome back, " . $user['full_name'] . "!";
            $_SESSION['message_type'] = "success";
            
            // Redirect based on role
            if ($user['role'] == 'admin') {
                header("Location: admin-dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['message'] = "❌ Invalid password! Please try again.";
            $_SESSION['message_type'] = "error";
            header("Location: login.html");
            exit();
        }
    } else {
        $_SESSION['message'] = "❌ User not found! Please check your username/email.";
        $_SESSION['message_type'] = "error";
        header("Location: login.html");
        exit();
    }
    
    $conn->close();
} else {
    header("Location: login.html");
    exit();
}
?>