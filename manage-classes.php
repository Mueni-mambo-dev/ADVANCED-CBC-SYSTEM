<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['allocate'])) {
    $class = $_POST['class'];
    $subject_id = $_POST['subject_id'];
    $teacher_id = $_POST['teacher_id'];
    
    $check = $conn->query("SELECT id FROM class_subjects WHERE class='$class' AND subject_id=$subject_id");
    if($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO class_subjects (class, subject_id, teacher_id) VALUES (?,?,?)");
        $stmt->bind_param("sii", $class, $subject_id, $teacher_id);
        $stmt->execute();
        $_SESSION['message'] = "Subject allocated to class!";
    } else {
        $_SESSION['message'] = "Subject already allocated to this class";
    }
    header("Location: manage-classes.php");
    exit();
}

if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
    $conn->query("DELETE FROM class-subjects WHERE id=$id");
    $_SESSION['message'] = "Allocation removed";
    header("Location: manage-classes.php");
    exit();
}

$classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
$subjects = $conn->query("SELECT * FROM subjects WHERE is_active=1");
$teachers = $conn->query("SELECT id, full_name FROM users WHERE role='teacher'");
$allocations = $conn->query("SELECT cs.*, sub.subject_name, u.full_name as teacher_name FROM class_subjects cs JOIN subjects sub ON cs.subject_id=sub.id JOIN users u ON cs.teacher_id=u.id ORDER BY cs.class, sub.subject_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Class Subjects - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 1000px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .btn { display: inline-block; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn-primary { background: #1abc9c; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .form-group { margin-bottom: 15px; }
        select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; background: #d4edda; color: #155724; }
    </style>
</head>
<body>
<header><h2>🏫 Class Subject Allocation</h2></header>
<div class="container">
    <h2>Allocate Subjects to Classes</h2>
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group"><select name="class" required><option value="">Select Class</option><?php while($c=$classes->fetch_assoc()) echo "<option value='{$c['class']}'>{$c['class']}</option>"; ?></select></div>
        <div class="form-group"><select name="subject_id" required><option value="">Select Subject</option><?php while($sub=$subjects->fetch_assoc()) echo "<option value='{$sub['id']}'>{$sub['subject_name']}</option>"; ?></select></div>
        <div class="form-group"><select name="teacher_id" required><option value="">Select Teacher</option><?php while($t=$teachers->fetch_assoc()) echo "<option value='{$t['id']}'>{$t['full_name']}</option>"; ?></select></div>
        <button type="submit" name="allocate" class="btn btn-primary">Allocate Subject</button>
    </form>
    
    <h3 style="margin-top: 30px;">Current Allocations</h3>
    <table>
        <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Action</th></tr></thead>
        <tbody>
            <?php while($a = $allocations->fetch_assoc()): ?>
            <tr>
                <td><?php echo $a['class']; ?></td>
                <td><?php echo $a['subject_name']; ?></td>
                <td><?php echo $a['teacher_name']; ?></td>
                <td><a href="?remove=<?php echo $a['id']; ?>" class="btn btn-danger" onclick="return confirm('Remove allocation?')">Remove</a></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>