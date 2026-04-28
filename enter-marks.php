<?php
session_start();
include "db.php";

// Fetch students
$students = $conn->query("SELECT id, admission_number, first_name, last_name, class FROM students WHERE status='Active' ORDER BY first_name");

// Fetch subjects
$subjects = $conn->query("SELECT id, subject_code, subject_name FROM subjects WHERE is_active=1 ORDER BY subject_name");

// Fetch terms
$terms = $conn->query("SELECT id, term_name FROM terms ORDER BY term_number");

// Fetch academic years
$years = $conn->query("SELECT id, year FROM academic_years ORDER BY year DESC");

// Get current term/year for pre-selection
$current_term = $conn->query("SELECT id FROM terms WHERE is_current=1 LIMIT 1")->fetch_assoc();
$current_year = $conn->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Enter Student Marks - Kitere CBC Exam System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(120deg, #89f7fe, #66a6ff); min-height: 100vh; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { width: 90%; max-width: 700px; margin: 40px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        h2 { text-align: center; color: #333; margin-bottom: 30px; }
        label { font-weight: bold; color: #555; display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 6px; border: 2px solid #e0e0e0; font-size: 14px; }
        button { width: 100%; padding: 12px; background: #1abc9c; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
        button:hover { background: #16a085; }
        .footer { text-align: center; margin-top: 20px; }
        .footer a { color: #1abc9c; text-decoration: none; font-weight: bold; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; text-align: center; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<header><h2>📚 Kitere CBC Exam System</h2><p>Enter Student Marks</p></header>
<div class="container">
    <h2>Enter New Marks</h2>
    <?php
    if (isset($_SESSION['message'])) {
        echo "<div class='message " . $_SESSION['message_type'] . "'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <form action="enter-marks-process.php" method="POST">
        <label>Select Student</label>
        <select name="student_id" required>
            <option value="">-- Select Student --</option>
            <?php while($s = $students->fetch_assoc()): ?>
                <option value="<?php echo $s['id']; ?>"><?php echo $s['admission_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['class'] . ')'; ?></option>
            <?php endwhile; ?>
        </select>

        <label>Select Subject</label>
        <select name="subject_id" required>
            <option value="">-- Select Subject --</option>
            <?php while($sub = $subjects->fetch_assoc()): ?>
                <option value="<?php echo $sub['id']; ?>"><?php echo $sub['subject_code'] . ' - ' . $sub['subject_name']; ?></option>
            <?php endwhile; ?>
        </select>

        <label>Select Term</label>
        <select name="term_id" required>
            <option value="">-- Select Term --</option>
            <?php while($t = $terms->fetch_assoc()): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo ($current_term && $current_term['id'] == $t['id']) ? 'selected' : ''; ?>><?php echo $t['term_name']; ?></option>
            <?php endwhile; ?>
        </select>

        <label>Academic Year</label>
        <select name="academic_year_id" required>
            <option value="">-- Select Year --</option>
            <?php while($y = $years->fetch_assoc()): ?>
                <option value="<?php echo $y['id']; ?>" <?php echo ($current_year && $current_year['id'] == $y['id']) ? 'selected' : ''; ?>><?php echo $y['year']; ?></option>
            <?php endwhile; ?>
        </select>

        <label>Exam Type</label>
        <select name="exam_type" required>
            <option value="CAT1">CAT 1</option>
            <option value="CAT2">CAT 2</option>
            <option value="CAT3">CAT 3</option>
            <option value="End Term">End Term Exam</option>
            <option value="Assignment">Assignment</option>
            <option value="Project">Project</option>
        </select>

        <label>Marks (0 - 100)</label>
        <input type="number" name="marks" min="0" max="100" step="0.01" required>

        <button type="submit">Save Marks</button>
    </form>
    <div class="footer"><a href="view-marks.php">📊 View All Marks</a> | <a href="dashboard.php">🏠 Dashboard</a></div>
</div>
</body>
</html>