<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: LOGIN.html");
    exit();
}
include "db.php";

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

$where = "WHERE DATE(l.created_at) BETWEEN '$date_from' AND '$date_to'";
if ($status_filter) {
    $where .= " AND l.status = '$status_filter'";
}

$logs = $conn->query("SELECT l.*, s.first_name, s.last_name, s.admission_number, s.class 
                      FROM sms_log l
                      JOIN students s ON l.student_id = s.id
                      $where
                      ORDER BY l.created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>SMS Log - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        h2 { color: #002147; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        .filter-box { margin-bottom: 20px; }
        input, select, button { padding: 8px; margin: 5px; }
        .btn { background: #1abc9c; color: white; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; }
        .status-pending { color: #f39c12; font-weight: bold; }
        .status-sent { color: #27ae60; font-weight: bold; }
        .status-delivered { color: #1abc9c; font-weight: bold; }
        .status-failed { color: #e74c3c; font-weight: bold; }
        .footer { text-align: center; margin-top: 20px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 10px; }
    </style>
</head>
<body>
<header><h2>📱 SMS Message Log</h2></header>
<div class="container">
    <h2>SMS History - SMSLeopard</h2>
    
    <form method="GET" class="filter-box">
        <input type="date" name="date_from" value="<?php echo $date_from; ?>"> to
        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Sent</option>
            <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
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
                    <th>Phone</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Credits</th>
                </tr>
            </thead>
            <tbody>
                <?php while($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d-m-Y H:i', strtotime($log['created_at'])); ?></td>
                    <td><?php echo $log['first_name'] . ' ' . $log['last_name']; ?><br><small><?php echo $log['admission_number']; ?></small></td>
                    <td><?php echo $log['parent_phone']; ?></td>
                    <td><?php echo substr($log['message'], 0, 80) . '...'; ?></td>
                    <td class="status-<?php echo $log['status']; ?>"><?php echo ucfirst($log['status']); ?></td>
                    <td><?php echo $log['credits_used']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No SMS records found.</p>
    <?php endif; ?>
    
    <div class="footer">
        <a href="sent-results.php">📧 Send Results</a>
        <a href="dashboard.php">🏠 Dashboard</a>
    </div>
</div>
</body>
</html>