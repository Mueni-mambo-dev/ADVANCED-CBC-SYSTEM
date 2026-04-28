<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $role = $_POST['role'];
        $password = password_hash("password123", PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $username, $email, $password, $full_name, $role);
        $stmt->execute();
        $_SESSION['message'] = "User added! Default password: password123";
        $stmt->close();
    }
    elseif (isset($_POST['toggle_status'])) {
        $id = $_POST['id'];
        $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$id");
        $_SESSION['message'] = "User status toggled";
    }
    header("Location: manage-users.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=$id AND role != 'admin'");
    $_SESSION['message'] = "User deleted";
    header("Location: manage-users.php");
    exit();
}

$users = $conn->query("SELECT * FROM users ORDER BY role, full_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .btn { display: inline-block; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn-primary { background: #1abc9c; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; background: #d4edda; color: #155724; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; width: 50%; margin: 5% auto; padding: 20px; border-radius: 12px; }
    </style>
</head>
<body>
<header><h2>👥 User Management - Kitere CBC</h2></header>
<div class="container">
    <h2>System Users</h2>
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">➕ Add New User</button>
    
    <table>
        <thead><tr><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody>
            <?php while($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo $u['username']; ?></td>
                <td><?php echo $u['full_name']; ?></td>
                <td><?php echo $u['email']; ?></td>
                <td><?php echo ucfirst($u['role']); ?></td>
                <td><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></td>
                <td><?php echo $u['last_login'] ? date('d-m-Y', strtotime($u['last_login'])) : 'Never'; ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        <button type="submit" name="toggle_status" class="btn btn-warning">Toggle Status</button>
                    </form>
                    <?php if($u['role'] != 'admin'): ?>
                        <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete?')">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span onclick="document.getElementById('addModal').style.display='none'" style="float:right; cursor:pointer;">&times;</span>
        <h3>Add New User</h3>
        <form method="POST">
            <div class="form-group"><input type="text" name="username" placeholder="Username" required></div>
            <div class="form-group"><input type="email" name="email" placeholder="Email" required></div>
            <div class="form-group"><input type="text" name="full_name" placeholder="Full Name" required></div>
            <div class="form-group">
                <select name="role">
                    <option value="teacher">Teacher</option>
                    <option value="student">Student</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="add" class="btn btn-primary">Add User (Default Pass: password123)</button>
        </form>
    </div>
</div>

<script>
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) event.target.style.display = 'none';
    }
</script>
</body>
</html>