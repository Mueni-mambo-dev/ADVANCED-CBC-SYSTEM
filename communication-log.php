<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: LOGIN.html");
    exit();
}
include "db.php";

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE DATE(rs.sent_date) BETWEEN '$date_from' AND '$date_to'";
if ($status_filter) $where .= " AND rs.status = '$status_filter'";

$logs = $conn->query("SELECT rs.*, s.first_name, s.last_name, s.admission_number, s.class, t.term_name, ay.year
                      FROM results_sent rs
                      JOIN students s ON rs.student_id = s.id
                      JOIN terms t ON rs.term_id = t.id
                      JOIN academic_years ay ON rs.year_id = ay.id
                      $where
                      ORDER BY rs.sent_date DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Communication Log - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        h2 { color: #002147; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .filter-box { margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filter-box input, .filter-box select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #1abc9c; color: white; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; }
        .status-sent { color: #27ae60; font-weight: bold; }
        .status-pending { color: #f39c12; font-weight: bold; }
        .status-failed { color: #e74c3c; font-weight: bold; }
        .badge-sms { background: #3498db; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        .footer { text-align: center; margin-top: 20px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 10px; }
        @media (max-width: 768px) {
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
<header><h2>📋 Communication Log - SMS Results</h2></header>
<div class="container">
    <h2>Results Sent via SMS History</h2>
    
    <form method="GET" class="filter-box">
        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Sent</option>
            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>
    
    <?php if ($logs && $logs->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Admission</th>
                    <th>Class</th>
                    <th>Term/Year</th>
                    <th>Sent To</th>
                    <th>Via</th>
                    <th>Message Preview</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d-m-Y H:i', strtotime($log['sent_date'])); ?></td>
                    <td><?php echo $log['first_name'] . ' ' . $log['last_name']; ?></td>
                    <td><?php echo $log['admission_number']; ?></td>
                    <td><?php echo $log['class']; ?></td>
                    <td><?php echo $log['term_name'] . ' ' . $log['year']; ?></td>
                    <td><?php echo $log['sent_to']; ?></td>
                    <td><span class="badge-sms"><?php echo strtoupper($log['sent_via']); ?></span></td>
                    <td><?php echo substr($log['message'], 0, 80) . '...'; ?></td>
                    <td class="status-<?php echo $log['status']; ?>"><?php echo ucfirst($log['status']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No SMS records found.</p>
    <?php endif; ?>
    
    <div class="footer">
        <a href="send-results.php">📱 Send Results</a>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="update-parent-contacts.php">📞 Update Contacts</a>
    </div>
</div>
</body>
</html>
