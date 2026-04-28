<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

// Manage Terms
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_term'])) {
        $term_name = $_POST['term_name'];
        $term_number = $_POST['term_number'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $stmt = $conn->prepare("INSERT INTO terms (term_name, term_number, start_date, end_date) VALUES (?,?,?,?)");
        $stmt->bind_param("siss", $term_name, $term_number, $start_date, $end_date);
        $stmt->execute();
        $_SESSION['message'] = "Term added!";
        $stmt->close();
    }
    elseif (isset($_POST['set_current_term'])) {
        $conn->query("UPDATE terms SET is_current = 0");
        $id = $_POST['id'];
        $conn->query("UPDATE terms SET is_current = 1 WHERE id=$id");
        $_SESSION['message'] = "Current term updated";
    }
    elseif (isset($_POST['add_year'])) {
        $year_name = $_POST['year_name'];
        $year = $_POST['year'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $stmt = $conn->prepare("INSERT INTO academic_years (year_name, year, start_date, end_date) VALUES (?,?,?,?)");
        $stmt->bind_param("siss", $year_name, $year, $start_date, $end_date);
        $stmt->execute();
        $_SESSION['message'] = "Academic year added!";
        $stmt->close();
    }
    elseif (isset($_POST['set_current_year'])) {
        $conn->query("UPDATE academic_years SET is_current = 0");
        $id = $_POST['id'];
        $conn->query("UPDATE academic_years SET is_current = 1 WHERE id=$id");
        $_SESSION['message'] = "Current academic year updated";
    }
    header("Location: manage-terms.php");
    exit();
}

$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Terms & Years - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .btn { display: inline-block; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn-primary { background: #1abc9c; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 30px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; background: #d4edda; color: #155724; }
        .section { margin-bottom: 40px; }
        h3 { color: #002147; margin-bottom: 20px; }
    </style>
</head>
<body>
<header><h2>📅 Academic Calendar Management</h2></header>
<div class="container">
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <div class="section">
        <h3>📖 Terms</h3>
        <button class="btn btn-primary" onclick="document.getElementById('termModal').style.display='block'">➕ Add Term</button>
        <table>
            <thead><tr><th>Term</th><th>Start Date</th><th>End Date</th><th>Current</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($t = $terms->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $t['term_name']; ?></td>
                    <td><?php echo $t['start_date']; ?></td>
                    <td><?php echo $t['end_date']; ?></td>
                    <td><?php echo $t['is_current'] ? '✅ Current' : ''; ?></td>
                    <td>
                        <?php if(!$t['is_current']): ?>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <button type="submit" name="set_current_term" class="btn btn-warning">Set as Current</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h3>📅 Academic Years</h3>
        <button class="btn btn-primary" onclick="document.getElementById('yearModal').style.display='block'">➕ Add Academic Year</button>
        <table>
            <thead><tr><th>Year Name</th><th>Year</th><th>Start Date</th><th>End Date</th><th>Current</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($y = $years->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $y['year_name']; ?></td>
                    <td><?php echo $y['year']; ?></td>
                    <td><?php echo $y['start_date']; ?></td>
                    <td><?php echo $y['end_date']; ?></td>
                    <td><?php echo $y['is_current'] ? '✅ Current' : ''; ?></td>
                    <td>
                        <?php if(!$y['is_current']): ?>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $y['id']; ?>">
                            <button type="submit" name="set_current_year" class="btn btn-warning">Set as Current</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Term Modal -->
<div id="termModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:white; width:50%; margin:5% auto; padding:20px; border-radius:12px;">
        <span onclick="document.getElementById('termModal').style.display='none'" style="float:right; cursor:pointer;">&times;</span>
        <h3>Add New Term</h3>
        <form method="POST">
            <div class="form-group"><input type="text" name="term_name" placeholder="Term Name (e.g., Term 1)" required></div>
            <div class="form-group"><input type="number" name="term_number" placeholder="Term Number (1,2,3)" required></div>
            <div class="form-group"><input type="date" name="start_date" required></div>
            <div class="form-group"><input type="date" name="end_date" required></div>
            <button type="submit" name="add_term" class="btn btn-primary">Add Term</button>
        </form>
    </div>
</div>

<!-- Year Modal -->
<div id="yearModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:white; width:50%; margin:5% auto; padding:20px; border-radius:12px;">
        <span onclick="document.getElementById('yearModal').style.display='none'" style="float:right; cursor:pointer;">&times;</span>
        <h3>Add Academic Year</h3>
        <form method="POST">
            <div class="form-group"><input type="text" name="year_name" placeholder="Year Name (e.g., 2024 Academic Year)" required></div>
            <div class="form-group"><input type="number" name="year" placeholder="Year (e.g., 2024)" required></div>
            <div class="form-group"><input type="date" name="start_date" required></div>
            <div class="form-group"><input type="date" name="end_date" required></div>
            <button type="submit" name="add_year" class="btn btn-primary">Add Year</button>
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