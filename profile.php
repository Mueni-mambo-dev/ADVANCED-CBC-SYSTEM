<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    if (!empty($_POST['new_password'])) {
        $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET full_name='$full_name', email='$email', phone='$phone', password='$password' WHERE id=$user_id");
        $_SESSION['message'] = "Profile updated with new password!";
    } else {
        $conn->query("UPDATE users SET full_name='$full_name', email='$email', phone='$phone' WHERE id=$user_id");
        $_SESSION['message'] = "Profile updated successfully!";
    }
    $_SESSION['full_name'] = $full_name;
    header("Location: profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 600px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .btn { display: inline-block; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn-primary { background: #1abc9c; color: white; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; background: #d4edda; color: #155724; }
    </style>
</head>
<body>
<header><h2>👤 My Profile</h2></header>
<div class="container">
    <h2>User Profile</h2>
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group"><label>Username</label><input type="text" value="<?php echo $user['username']; ?>" disabled></div>
        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo $user['full_name']; ?>" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo $user['email']; ?>" required></div>
        <div class="form-group"><label>Phone (Optional)</label><input type="text" name="phone" value="<?php echo $user['phone']; ?>"></div>
        <div class="form-group"><label>Role</label><input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled></div>
        <div class="form-group"><label>New Password (leave blank to keep current)</label><input type="password" name="new_password"></div>
        <button type="submit" class="btn btn-primary">Update Profile</button>
        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </form>
</div>
</body>
</html>