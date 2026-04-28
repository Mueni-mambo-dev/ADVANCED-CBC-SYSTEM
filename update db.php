<?php
// update_db.php - Run this once to add users table to your database
include "db.php";

// Create users table
$users_table_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher', 'student') DEFAULT 'teacher',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)";

if ($conn->query($users_table_sql) === TRUE) {
    echo "✅ Users table created successfully!<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create an admin user (default credentials)
$admin_username = "admin";
$admin_email = "admin@kitere.edu";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);
$admin_full_name = "System Administrator";
$admin_role = "admin";

$check_admin = "SELECT id FROM users WHERE username = 'admin'";
$admin_result = $conn->query($check_admin);

if ($admin_result->num_rows == 0) {
    $insert_admin = "INSERT INTO users (username, email, password, full_name, role) 
                     VALUES ('$admin_username', '$admin_email', '$admin_password', '$admin_full_name', '$admin_role')";
    if ($conn->query($insert_admin) === TRUE) {
        echo "✅ Admin user created!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error creating admin: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Admin user already exists.<br>";
}

$conn->close();
?>


<?php
    session_start();
    if (isset($_SESSION['message'])) {
        echo "<div class='message " . $_SESSION['message_type'] . "'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>

     <?php
            include "db.php";
            $students = $conn->query("SELECT id, admission_number, first_name, last_name, class FROM students WHERE status='Active' ORDER BY first_name");
            while ($s = $students->fetch_assoc()) {
                echo "<option value='{$s['id']}'>{$s['admission_number']} - {$s['first_name']} {$s['last_name']} ({$s['class']})</option>";
            }
            ?>

            <?php
            $subjects = $conn->query("SELECT id, subject_code, subject_name FROM subjects WHERE is_active=1 ORDER BY subject_name");
            while ($sub = $subjects->fetch_assoc()) {
                echo "<option value='{$sub['id']}'>{$sub['subject_code']} - {$sub['subject_name']}</option>";
            }
            ?>

            <?php
            $terms = $conn->query("SELECT id, term_name FROM terms ORDER BY term_number");
            while ($t = $terms->fetch_assoc()) {
                $selected = ($t['is_current'] ?? false) ? "selected" : "";
                echo "<option value='{$t['id']}' $selected>{$t['term_name']}</option>";
            }
            ?>

            <?php
            $years = $conn->query("SELECT id, year FROM academic_years ORDER BY year DESC");
            while ($y = $years->fetch_assoc()) {
                $selected = ($y['is_current'] ?? false) ? "selected" : "";
                echo "<option value='{$y['id']}' $selected>{$y['year']}</option>";
            }
            ?>


            