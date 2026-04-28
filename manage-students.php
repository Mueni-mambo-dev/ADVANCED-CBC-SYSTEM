<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    header("Location: login.html");
    exit();
}
include "db.php";

// Handle Add/Edit/Delete
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $admission = mysqli_real_escape_string($conn, $_POST['admission_number']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $dob = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $class = $_POST['class'];
        $stream = $_POST['stream'];
        $parent_name = mysqli_real_escape_string($conn, $_POST['parent_name']);
        $parent_phone = $_POST['parent_phone'];
        $parent_email = $_POST['parent_email'];
        $enrollment_date = $_POST['enrollment_date'];
        
        $stmt = $conn->prepare("INSERT INTO students (admission_number, first_name, last_name, date_of_birth, gender, class, stream, parent_name, parent_phone, parent_email, enrollment_date) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssssss", $admission, $first_name, $last_name, $dob, $gender, $class, $stream, $parent_name, $parent_phone, $parent_email, $enrollment_date);
        if ($stmt->execute()) $_SESSION['message'] = "Student added successfully!";
        else $_SESSION['message'] = "Error: " . $stmt->error;
        $stmt->close();
    }
    elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $class = $_POST['class'];
        $stream = $_POST['stream'];
        $parent_phone = $_POST['parent_phone'];
        
        $stmt = $conn->prepare("UPDATE students SET first_name=?, last_name=?, class=?, stream=?, parent_phone=? WHERE id=?");
        $stmt->bind_param("sssssi", $first_name, $last_name, $class, $stream, $parent_phone, $id);
        $stmt->execute();
        $_SESSION['message'] = "Student updated!";
        $stmt->close();
    }
    header("Location: manage-students.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM students WHERE id=$id");
    $_SESSION['message'] = "Student deleted!";
    header("Location: manage-students.php");
    exit();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = $search ? "WHERE first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR admission_number LIKE '%$search%'" : "";
$students = $conn->query("SELECT * FROM students $where ORDER BY class, first_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Students - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        h2 { margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn-primary { background: #1abc9c; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #d4edda; color: #155724; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; width: 50%; margin: 5% auto; padding: 20px; border-radius: 12px; }
    </style>
</head>
<body>
<header><h2>📚 Kitere CBC Exam System - Student Management</h2></header>
<div class="container">
    <h2>Manage Students</h2>
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">➕ Add New Student</button>
    <div class="search-box" style="margin: 20px 0;">
        <form method="GET"><input type="text" name="search" placeholder="Search by name or admission" value="<?php echo $search; ?>"><button type="submit" class="btn btn-primary">Search</button></form>
    </div>
    
    <table>
        <thead><tr><th>Admission</th><th>Full Name</th><th>Class</th><th>Stream</th><th>Parent Phone</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php while($s = $students->fetch_assoc()): ?>
            <tr>
                <td><?php echo $s['admission_number']; ?></td>
                <td><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></td>
                <td><?php echo $s['class']; ?></td>
                <td><?php echo $s['stream']; ?></td>
                <td><?php echo $s['parent_phone']; ?></td>
                <td><?php echo $s['status']; ?></td>
                <td>
                    <button class="btn btn-warning" onclick="editStudent(<?php echo $s['id']; ?>, '<?php echo $s['first_name']; ?>', '<?php echo $s['last_name']; ?>', '<?php echo $s['class']; ?>', '<?php echo $s['stream']; ?>', '<?php echo $s['parent_phone']; ?>')">Edit</button>
                    <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete?')">Delete</a>
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
        <h3>Add New Student</h3>
        <form method="POST">
            <div class="form-group"><input type="text" name="admission_number" placeholder="Admission Number" required></div>
            <div class="form-group"><input type="text" name="first_name" placeholder="First Name" required></div>
            <div class="form-group"><input type="text" name="last_name" placeholder="Last Name" required></div>
            <div class="form-group"><input type="date" name="date_of_birth" required></div>
            <div class="form-group"><select name="gender"><option>Male</option><option>Female</option></select></div>
            <div class="form-group"><input type="text" name="class" placeholder="Class (e.g., Grade 7)" required></div>
            <div class="form-group"><input type="text" name="stream" placeholder="Stream"></div>
            <div class="form-group"><input type="text" name="parent_name" placeholder="Parent Name"></div>
            <div class="form-group"><input type="text" name="parent_phone" placeholder="Parent Phone"></div>
            <div class="form-group"><input type="email" name="parent_email" placeholder="Parent Email"></div>
            <div class="form-group"><input type="date" name="enrollment_date" required></div>
            <button type="submit" name="add" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span onclick="document.getElementById('editModal').style.display='none'" style="float:right; cursor:pointer;">&times;</span>
        <h3>Edit Student</h3>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group"><input type="text" name="first_name" id="edit_first" required></div>
            <div class="form-group"><input type="text" name="last_name" id="edit_last" required></div>
            <div class="form-group"><input type="text" name="class" id="edit_class" required></div>
            <div class="form-group"><input type="text" name="stream" id="edit_stream"></div>
            <div class="form-group"><input type="text" name="parent_phone" id="edit_phone"></div>
            <button type="submit" name="edit" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>

<script>
function editStudent(id, first, last, classname, stream, phone) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_first').value = first;
    document.getElementById('edit_last').value = last;
    document.getElementById('edit_class').value = classname;
    document.getElementById('edit_stream').value = stream;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('editModal').style.display = 'block';
}
</script>
</body>
</html>