<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    header("Location: login.html");
    exit();
}
include "db.php";

$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$class_filter = isset($_POST['class']) ? $_POST['class'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_attendance'])) {
    $date = $_POST['date'];
    foreach ($_POST['attendance'] as $student_id => $status) {
        $check = $conn->query("SELECT id FROM attendance WHERE student_id=$student_id AND date='$date'");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE attendance SET status='$status', recorded_by={$_SESSION['user_id']} WHERE student_id=$student_id AND date='$date'");
        } else {
            $conn->query("INSERT INTO attendance (student_id, date, status, recorded_by) VALUES ($student_id, '$date', '$status', {$_SESSION['user_id']})");
        }
    }
    $_SESSION['message'] = "Attendance saved for $date";
    header("Location: take-attendance.php");
    exit();
}

$classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
$students = [];
if ($class_filter) {
    $students = $conn->query("SELECT * FROM students WHERE class='$class_filter' AND status='Active' ORDER BY first_name");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Take Attendance - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .btn { display: inline-block; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn-primary { background: #1abc9c; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .form-group { margin-bottom: 15px; }
        select, input { padding: 8px; margin: 5px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; background: #d4edda; color: #155724; }
    </style>
</head>
<body>
<header><h2>📋 Take Attendance - Kitere CBC</h2></header>
<div class="container">
    <h2>Mark Student Attendance</h2>
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Select Class:</label>
            <select name="class" required>
                <option value="">-- Select Class --</option>
                <?php while($c = $classes->fetch_assoc()): ?>
                    <option value="<?php echo $c['class']; ?>" <?php echo $class_filter==$c['class']?'selected':''; ?>><?php echo $c['class']; ?></option>
                <?php endwhile; ?>
            </select>
            <label>Date:</label>
            <input type="date" name="date" value="<?php echo $date; ?>" required>
            <button type="submit" name="load" class="btn btn-primary">Load Students</button>
        </div>
    </form>
    
    <?php if($class_filter && $students && $students->num_rows > 0): ?>
        <form method="POST">
            <input type="hidden" name="date" value="<?php echo $date; ?>">
            <input type="hidden" name="class" value="<?php echo $class_filter; ?>">
            <table>
                <thead><tr><th>Admission</th><th>Student Name</th><th>Status</th></tr></thead>
                <tbody>
                    <?php while($s = $students->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $s['admission_number']; ?></td>
                        <td><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></td>
                        <td>
                            <select name="attendance[<?php echo $s['id']; ?>]">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Excused">Excused</option>
                            </select>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
        </form>
    <?php elseif($class_filter): ?>
        <p>No active students found in this class.</p>
    <?php endif; ?>
</div>
</body>
</html>