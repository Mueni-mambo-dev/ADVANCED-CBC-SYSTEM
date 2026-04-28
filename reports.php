<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

$report_type = isset($_GET['type']) ? $_GET['type'] : '';
$class = isset($_GET['class']) ? $_GET['class'] : '';
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$term_id = isset($_GET['term_id']) ? $_GET['term_id'] : '';
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : '';

$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
$classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");

// Performance Report
if ($report_type == 'performance' && $class && $subject_id && $term_id && $year_id) {
    $report = $conn->query("SELECT s.admission_number, s.first_name, s.last_name, m.marks, m.grade FROM marks m JOIN students s ON m.student_id = s.id WHERE s.class='$class' AND m.subject_id=$subject_id AND m.term_id=$term_id AND m.academic_year_id=$year_id ORDER BY m.marks DESC");
}
// Class Summary Report
if ($report_type == 'class_summary' && $class && $term_id && $year_id) {
    $report = $conn->query("SELECT sub.subject_name, AVG(m.marks) as avg_marks, MAX(m.marks) as max_marks, MIN(m.marks) as min_marks FROM marks m JOIN subjects sub ON m.subject_id = sub.id JOIN students s ON m.student_id = s.id WHERE s.class='$class' AND m.term_id=$term_id AND m.academic_year_id=$year_id GROUP BY sub.subject_name");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - Kitere CBC</title>
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
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 10px; }
        select, input { padding: 8px; margin: 5px; }
        .report-header { margin-bottom: 20px; }
    </style>
</head>
<body>
<header><h2>📈 Reports Generator</h2></header>
<div class="container">
    <h2>Generate Academic Reports</h2>
    
    <form method="GET" class="report-header">
        <input type="hidden" name="type" value="performance">
        <div class="form-group"><select name="class"><option value="">Select Class</option><?php while($c=$classes->fetch_assoc()) echo "<option value='{$c['class']}'>{$c['class']}</option>"; ?></select></div>
        <div class="form-group"><select name="subject_id"><option value="">Select Subject</option><?php while($sub=$subjects->fetch_assoc()) echo "<option value='{$sub['id']}'>{$sub['subject_name']}</option>"; ?></select></div>
        <div class="form-group"><select name="term_id"><option value="">Select Term</option><?php while($t=$terms->fetch_assoc()) echo "<option value='{$t['id']}'>{$t['term_name']}</option>"; ?></select></div>
        <div class="form-group"><select name="year_id"><option value="">Select Year</option><?php while($y=$years->fetch_assoc()) echo "<option value='{$y['id']}'>{$y['year']}</option>"; ?></select></div>
        <button type="submit" class="btn btn-primary">Generate Performance Report</button>
    </form>
    
    <form method="GET" class="report-header">
        <input type="hidden" name="type" value="class_summary">
        <div class="form-group"><select name="class"><option value="">Select Class</option><?php $classes2=$conn->query("SELECT DISTINCT class FROM students"); while($c=$classes2->fetch_assoc()) echo "<option value='{$c['class']}'>{$c['class']}</option>"; ?></select></div>
        <div class="form-group"><select name="term_id"><option value="">Select Term</option><?php $terms2=$conn->query("SELECT * FROM terms"); while($t=$terms2->fetch_assoc()) echo "<option value='{$t['id']}'>{$t['term_name']}</option>"; ?></select></div>
        <div class="form-group"><select name="year_id"><option value="">Select Year</option><?php $years2=$conn->query("SELECT * FROM academic_years"); while($y=$years2->fetch_assoc()) echo "<option value='{$y['id']}'>{$y['year']}</option>"; ?></select></div>
        <button type="submit" class="btn btn-primary">Generate Class Summary</button>
    </form>
    
    <?php if(isset($report) && $report->num_rows > 0): ?>
        <h3>Report Results</h3>
        <table>
            <thead>
                <?php if($report_type == 'performance'): ?>
                <tr><th>Admission</th><th>Student Name</th><th>Marks</th><th>Grade</th></tr>
                <?php else: ?>
                <tr><th>Subject</th><th>Average Marks</th><th>Highest</th><th>Lowest</th></tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php while($r = $report->fetch_assoc()): ?>
                <tr>
                    <?php if($report_type == 'performance'): ?>
                        <td><?php echo $r['admission_number']; ?></td>
                        <td><?php echo $r['first_name'] . ' ' . $r['last_name']; ?></td>
                        <td><?php echo $r['marks']; ?></td>
                        <td><?php echo $r['grade']; ?></td>
                    <?php else: ?>
                        <td><?php echo $r['subject_name']; ?></td>
                        <td><?php echo round($r['avg_marks'], 2); ?></td>
                        <td><?php echo $r['max_marks']; ?></td>
                        <td><?php echo $r['min_marks']; ?></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
    <?php elseif($report_type): ?>
        <p>No data found for selected criteria.</p>
    <?php endif; ?>
</div>
</body>
</html>