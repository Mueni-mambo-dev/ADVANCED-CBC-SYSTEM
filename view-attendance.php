<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

$class = isset($_GET['class']) ? $_GET['class'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

$where = "WHERE a.date BETWEEN '$date_from' AND '$date_to'";
if ($class) $where .= " AND s.class='$class'";

$attendance = $conn->query("SELECT a.*, s.admission_number, s.first_name, s.last_name, s.class FROM attendance a JOIN students s ON a.student_id = s.id $where ORDER BY a.date DESC, s.class, s.first_name");
$classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Attendance - Kitere CBC</title>
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
        .filter-box { margin-bottom: 20px; }
        input, select { padding: 8px; margin: 5px; }
        .present { color: green; font-weight: bold; }
        .absent { color: red; font-weight: bold; }
        .late { color: orange; font-weight: bold; }
    </style>
</head>
<body>
<header><h2>📊 Attendance Records</h2></header>
<div class="container">
    <h2>View Attendance History</h2>
    
    <form method="GET" class="filter-box">
        <select name="class">
            <option value="">All Classes</option>
            <?php while($c = $classes->fetch_assoc()): ?>
                <option value="<?php echo $c['class']; ?>" <?php echo $class==$c['class']?'selected':''; ?>><?php echo $c['class']; ?></option>
            <?php endwhile; ?>
        </select>
        <input type="date" name="date_from" value="<?php echo $date_from; ?>"> to
        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
    
    <table>
        <thead><tr><th>Date</th><th>Admission</th><th>Student</th><th>Class</th><th>Status</th></tr></thead>
        <tbody>
            <?php while($a = $attendance->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d-m-Y', strtotime($a['date'])); ?></td>
                <td><?php echo $a['admission_number']; ?></td>
                <td><?php echo $a['first_name'] . ' ' . $a['last_name']; ?></td>
                <td><?php echo $a['class']; ?></td>
                <td class="<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>