<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_contacts'])) {
    foreach ($_POST['parent_email'] as $id => $email) {
        $phone = $_POST['parent_phone'][$id];
        $conn->query("UPDATE students SET parent_email = '$email', parent_phone = '$phone' WHERE id = $id");
    }
    $_SESSION['message'] = "Parent contacts updated successfully!";
    header("Location: update_parent_contacts.php");
    exit();
}

$students = $conn->query("SELECT id, admission_number, first_name, last_name, class, parent_email, parent_phone FROM students ORDER BY class, first_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Parent Contacts - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        input { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #1abc9c; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; margin-top: 20px; }
        .message { padding: 10px; margin-bottom: 20px; background: #d4edda; color: #155724; border-radius: 4px; }
    </style>
</head>
<body>
<header><h2>📞 Update Parent Contact Information</h2></header>
<div class="container">
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <table>
            <thead>
                <tr><th>Admission</th><th>Student Name</th><th>Class</th><th>Parent Email</th><th>Parent Phone</th></tr>
            </thead>
            <tbody>
                <?php while($s = $students->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $s['admission_number']; ?></td>
                    <td><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></td>
                    <td><?php echo $s['class']; ?></td>
                    <td><input type="email" name="parent_email[<?php echo $s['id']; ?>]" value="<?php echo $s['parent_email']; ?>"></td>
                    <td><input type="text" name="parent_phone[<?php echo $s['id']; ?>]" value="<?php echo $s['parent_phone']; ?>"></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <button type="submit" name="update_contacts" class="btn">💾 Save All Updates</button>
    </form>
</div>
</body>
</html>