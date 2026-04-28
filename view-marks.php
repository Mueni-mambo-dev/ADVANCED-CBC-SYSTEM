<?php
session_start();
include "db.php";

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle delete (admin or teacher can delete their own? Admin can delete any)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($user_role == 'admin') {
        $del = $conn->prepare("DELETE FROM marks WHERE id = ?");
        $del->bind_param("i", $id);
    } else {
        // Teachers can only delete marks they entered
        $del = $conn->prepare("DELETE FROM marks WHERE id = ? AND recorded_by = ?");
        $del->bind_param("ii", $id, $user_id);
    }
    if ($del->execute()) {
        $_SESSION['message'] = "Record deleted successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Delete failed: " . $del->error;
        $_SESSION['message_type'] = "error";
    }
    $del->close();
    header("Location: view-marks.php");
    exit();
}

// Handle edit update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $marks = floatval($_POST['marks']);
    $exam_type = mysqli_real_escape_string($conn, $_POST['exam_type']);
    
    // Recalculate grade
    if ($marks >= 75) $grade = "EE";
    elseif ($marks >= 50) $grade = "ME";
    elseif ($marks >= 25) $grade = "AE";
    else $grade = "BE";
    
    $update = $conn->prepare("UPDATE marks SET marks = ?, exam_type = ?, grade = ? WHERE id = ?");
    $update->bind_param("dssi", $marks, $exam_type, $grade, $id);
    if ($update->execute()) {
        $_SESSION['message'] = "Record updated successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Update failed: " . $update->error;
        $_SESSION['message_type'] = "error";
    }
    $update->close();
    header("Location: VIEW_MARKS.php");
    exit();
}

// Search & filtering
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "";
if (!empty($search)) {
    $where = "AND (s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.admission_number LIKE '%$search%' OR sub.subject_name LIKE '%$search%')";
}

// Role-based query: students see only their own marks, teachers see marks they entered, admin sees all
if ($user_role == 'student') {
    // Find student id from users? For simplicity, assume student's full name matches or we link via email. We'll use a subquery.
    $student_subquery = "SELECT id FROM students WHERE CONCAT(first_name, ' ', last_name) = '{$_SESSION['full_name']}' LIMIT 1";
    $student_res = $conn->query($student_subquery);
    if ($student_res && $student_res->num_rows > 0) {
        $student_row = $student_res->fetch_assoc();
        $student_id = $student_row['id'];
        $role_filter = "AND m.student_id = $student_id";
    } else {
        $role_filter = "AND 1=0"; // no results
    }
} elseif ($user_role == 'teacher') {
    $role_filter = "AND m.recorded_by = $user_id";
} else {
    $role_filter = ""; // admin sees all
}

$sql = "SELECT m.*, 
               s.admission_number, s.first_name, s.last_name, s.class,
               sub.subject_code, sub.subject_name,
               t.term_name,
               ay.year,
               u.full_name as recorded_by_name
        FROM marks m
        JOIN students s ON m.student_id = s.id
        JOIN subjects sub ON m.subject_id = sub.id
        JOIN terms t ON m.term_id = t.id
        JOIN academic_years ay ON m.academic_year_id = ay.id
        JOIN users u ON m.recorded_by = u.id
        WHERE 1=1 $role_filter $where
        ORDER BY m.created_at DESC";
$result = $conn->query($sql);

// For edit modal, fetch a single record if 'edit' parameter is present
$edit_record = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_sql = "SELECT * FROM marks WHERE id = $edit_id";
    $edit_res = $conn->query($edit_sql);
    if ($edit_res && $edit_res->num_rows > 0) {
        $edit_record = $edit_res->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Marks - Kitere CBC Exam System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { width: 95%; max-width: 1400px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .search-box { text-align: center; margin-bottom: 20px; }
        .search-box input { padding: 10px; width: 300px; border: 2px solid #ddd; border-radius: 6px; }
        .search-box button { padding: 10px 20px; background: #1abc9c; color: white; border: none; border-radius: 6px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        tr:hover { background: #f5f5f5; }
        .btn { display: inline-block; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; margin: 0 2px; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-primary { background: #1abc9c; color: white; padding: 10px 20px; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; width: 50%; border-radius: 12px; }
        .close { float: right; font-size: 28px; cursor: pointer; }
        @media (max-width: 768px) { .container { padding: 15px; } table { font-size: 12px; } }
    </style>
</head>
<body>
<header><h2>📚 Kitere CBC Exam System</h2><p>View & Manage Marks</p></header>
<div class="container">
    <h2>Student Marks Records</h2>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>"><?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?></div>
    <?php endif; ?>

    <div class="search-box">
        <form method="GET">
            <input type="text" name="search" placeholder="Search by student name, admission, subject" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">🔍 Search</button>
            <?php if ($search): ?><a href="view-marks.php" style="margin-left:10px;">Clear</a><?php endif; ?>
        </form>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Admission</th><th>Student</th><th>Class</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Exam Type</th><th>Term</th><th>Year</th><th>Recorded By</th><th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['admission_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['class']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td><?php echo $row['marks']; ?></td>
                        <td style="background:<?php echo ($row['grade']=='EE'?'#d4edda':($row['grade']=='ME'?'#fff3cd':($row['grade']=='AE'?'#ffe5b4':'#f8d7da'))); ?>"><?php echo $row['grade']; ?></td>
                        <td><?php echo $row['exam_type']; ?></td>
                        <td><?php echo $row['term_name']; ?></td>
                        <td><?php echo $row['year']; ?></td>
                        <td><?php echo htmlspecialchars($row['recorded_by_name']); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align:center;">No marks records found.</p>
    <?php endif; ?>
    <div style="margin-top:20px; text-align:center;">
        <a href="enter-marks.php" class="btn btn-primary">➕ Add New Marks</a>
        <a href="dashboard.php" class="btn btn-primary">🏠 Dashboard</a>
    </div>
</div>

<!-- Edit Modal -->
<?php if ($edit_record): ?>
<div id="editModal" style="display:block;">
    <div class="modal-content" style="margin:5% auto; width:60%;">
        <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
        <h3>Edit Marks</h3>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $edit_record['id']; ?>">
            <label>Marks (0-100)</label>
            <input type="number" name="marks" step="0.01" value="<?php echo $edit_record['marks']; ?>" min="0" max="100" required>
            <label>Exam Type</label>
            <select name="exam_type" required>
                <option <?php echo $edit_record['exam_type']=='CAT1'?'selected':''; ?>>CAT1</option>
                <option <?php echo $edit_record['exam_type']=='CAT2'?'selected':''; ?>>CAT2</option>
                <option <?php echo $edit_record['exam_type']=='CAT3'?'selected':''; ?>>CAT3</option>
                <option <?php echo $edit_record['exam_type']=='End Term'?'selected':''; ?>>End Term</option>
                <option <?php echo $edit_record['exam_type']=='Assignment'?'selected':''; ?>>Assignment</option>
                <option <?php echo $edit_record['exam_type']=='Project'?'selected':''; ?>>Project</option>
            </select>
            <button type="submit" name="update" style="margin-top:10px; background:#1abc9c; color:white; padding:10px; border:none; border-radius:6px;">Update</button>
        </form>
    </div>
</div>
<?php endif; ?>
<script>
    // Close modal if user clicks outside
    window.onclick = function(event) {
        let modal = document.getElementById('editModal');
        if (event.target == modal) modal.style.display = 'none';
    }
</script>
</body>
</html>