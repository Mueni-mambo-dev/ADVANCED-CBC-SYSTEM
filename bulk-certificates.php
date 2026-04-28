<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

$class = isset($_POST['class']) ? $_POST['class'] : '';
$certificate_type = isset($_POST['certificate_type']) ? $_POST['certificate_type'] : 'standard';
$generate_all = isset($_POST['generate_all']) ? true : false;

if ($generate_all && $class) {
    $students = $conn->query("SELECT id FROM students WHERE class = '$class' AND status = 'Active'");
    $student_ids = [];
    while($s = $students->fetch_assoc()) {
        $student_ids[] = $s['id'];
    }
    
    // Redirect to bulk generation page
    $_SESSION['bulk_students'] = $student_ids;
    $_SESSION['bulk_type'] = $certificate_type;
    header("Location: bulk-certificates-display.php");
    exit();
}

$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Certificates - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #002147; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { display: inline-block; padding: 12px 24px; background: #1abc9c; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; margin-top: 10px; width: 100%; }
        .btn:hover { background: #16a085; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #856404; }
    </style>
</head>
<body>
<header><h2>📚 Bulk Certificate Generator</h2></header>
<div class="container">
    <h2>Generate Multiple Certificates</h2>
    <div class="warning">
        ⚠️ This will generate certificates for ALL students in the selected class at once.
        Please ensure you have sufficient paper/ink before proceeding.
    </div>
    <form method="POST">
        <div class="form-group">
            <label>Select Class:</label>
            <select name="class" required>
                <option value="">-- Select Class --</option>
                <?php while($c = $classes->fetch_assoc()): ?>
                    <option value="<?php echo $c['class']; ?>"><?php echo $c['class']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Certificate Type:</label>
            <select name="certificate_type">
                <option value="standard">Standard Leaving Certificate</option>
                <option value="merit">Merit Certificate</option>
                <option value="transfer">Transfer Certificate</option>
            </select>
        </div>
        <button type="submit" name="generate_all" class="btn">📄 Generate All Certificates</button>
    </form>
    <a href="leaving-certificate.php" style="display: block; text-align: center; margin-top: 15px; color: #1abc9c;">← Back to Single Certificate</a>
</div>
</body>
</html>