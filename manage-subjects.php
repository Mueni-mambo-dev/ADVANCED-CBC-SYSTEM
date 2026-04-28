<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $code = mysqli_real_escape_string($conn, $_POST['subject_code']);
        $name = mysqli_real_escape_string($conn, $_POST['subject_name']);
        $category = $_POST['category'];
        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, category) VALUES (?,?,?)");
        $stmt->bind_param("sss", $code, $name, $category);
        $stmt->execute();
        $_SESSION['message'] = "Subject added!";
        $stmt->close();
    }
    elseif (isset($_POST['toggle'])) {
        $id = $_POST['id'];
        $conn->query("UPDATE subjects SET is_active = NOT is_active WHERE id=$id");
        $_SESSION['message'] = "Subject status updated";
    }
    header("Location: manage-subjects.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM subjects WHERE id=$id");
    $_SESSION['message'] = "Subject deleted";
    header("Location: manage-subjects.php");
    exit();
}

$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Subjects - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 1000px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
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
<header><h2>📖 Subject Management - Kitere CBC</h2></header>
<div class="container">
    <h2>School Subjects</h2>
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">➕ Add New Subject</button>
    
    <table>
        <thead><tr><th>Subject Code</th><th>Subject Name</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php while($sub = $subjects->fetch_assoc()): ?>
            <tr>
                <td><?php echo $sub['subject_code']; ?></td>
                <td><?php echo $sub['subject_name']; ?></td>
                <td><?php echo $sub['category']; ?></td>
                <td><?php echo $sub['is_active'] ? 'Active' : 'Inactive'; ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                        <button type="submit" name="toggle" class="btn btn-warning">Toggle Status</button>
                    </form>
                    <a href="?delete=<?php echo $sub['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete subject? This will also delete related marks!')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span onclick="document.getElementById('addModal').style.display='none'" style="float:right; cursor:pointer;">&times;</span>
        <h3>Add New Subject</h3>
        <form method="POST">
            <div class="form-group"><input type="text" name="subject_code" placeholder="Subject Code (e.g., MATH101)" required></div>
            <div class="form-group"><input type="text" name="subject_name" placeholder="Subject Name" required></div>
            <div class="form-group">
                <select name="category">
                    <option value="Core">Core</option>
                    <option value="Elective">Elective</option>
                    <option value="Optional">Optional</option>
                </select>
            </div>
            <button type="submit" name="add" class="btn btn-primary">Add Subject</button>
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