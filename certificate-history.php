<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

// Create certificates log table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS certificates_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    certificate_type VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    issued_by INT NOT NULL,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
)");

// Log a certificate issuance (call this when generating certificate)
if (isset($_GET['log_id'])) {
    $student_id = $_GET['student_id'];
    $cert_number = "KIT/CBC/" . date('Y') . "/" . str_pad($student_id, 5, '0', STR_PAD_LEFT);
    $type = $_GET['type'];
    $issued_by = $_SESSION['user_id'];
    
    $log_sql = "INSERT INTO certificates_log (student_id, certificate_number, certificate_type, issue_date, issued_by) 
                VALUES ($student_id, '$cert_number', '$type', CURDATE(), $issued_by)";
    $conn->query($log_sql);
}

// Fetch certificate history
$history_sql = "SELECT cl.*, s.first_name, s.last_name, s.admission_number, s.class, u.full_name as issued_by_name
                FROM certificates_log cl
                JOIN students s ON cl.student_id = s.id
                JOIN users u ON cl.issued_by = u.id
                ORDER BY cl.created_at DESC
                LIMIT 100";
$history = $conn->query($history_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Certificate History - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        h2 { color: #002147; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .btn { display: inline-block; padding: 8px 16px; background: #1abc9c; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn-small { padding: 4px 8px; font-size: 12px; }
        .btn-print { background: #3498db; }
        .footer { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
<header><h2>📜 Certificate Issuance History</h2></header>
<div class="container">
    <h2>Issued Certificates Log</h2>
    <a href="leaving-certificate.php" class="btn">➕ Issue New Certificate</a>
    <a href="bulk-certificates.php" class="btn">📄 Bulk Certificates</a>
    
    <?php if ($history && $history->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Certificate No.</th>
                    <th>Student Name</th>
                    <th>Admission No.</th>
                    <th>Class</th>
                    <th>Type</th>
                    <th>Issue Date</th>
                    <th>Issued By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $history->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['certificate_number']; ?></td>
                    <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                    <td><?php echo $row['admission_number']; ?></td>
                    <td><?php echo $row['class']; ?></td>
                    <td><?php echo ucfirst($row['certificate_type']); ?></td>
                    <td><?php echo date('d-m-Y', strtotime($row['issue_date'])); ?></td>
                    <td><?php echo $row['issued_by_name']; ?></td>
                    <td>
                        <a href="leaving-certificate.php?student_id=<?php echo $row['student_id']; ?>&type=<?php echo $row['certificate_type']; ?>&generate=1" class="btn btn-small">View</a>
                        <a href="leaving-certificate.php?student_id=<?php echo $row['student_id']; ?>&type=<?php echo $row['certificate_type']; ?>&generate=1&print=1" class="btn btn-small btn-print">Print</a>
                     </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; padding: 40px;">No certificates have been issued yet.</p>
    <?php endif; ?>
    
    <div class="footer">
        <a href="dashboard.php">🏠 Dashboard</a>
    </div>
</div>
</body>
</html>