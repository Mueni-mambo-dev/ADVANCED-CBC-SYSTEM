<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.html");
    exit();
}

include "db.php";

// Comprehensive statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM students) as total_students,
                (SELECT COUNT(*) FROM subjects) as total_subjects,
                (SELECT COUNT(*) FROM marks) as total_marks,
                (SELECT COUNT(*) FROM attendance WHERE status = 'Absent') as total_absences,
                (SELECT SUM(amount_paid) FROM fees) as total_fees_collected,
                (SELECT SUM(balance) FROM fees) as total_outstanding,
                (SELECT ROUND(AVG(marks), 2) FROM marks) as overall_avg_marks,
                (SELECT COUNT(DISTINCT class) FROM students) as total_classes,
                (SELECT COUNT(DISTINCT teacher_id) FROM class_subjects) as total_teachers_active,
                (SELECT COUNT(*) FROM certificates_log) as total_certificates_issued,
                (SELECT COUNT(*) FROM payments WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_payments,
                (SELECT COUNT(*) FROM results_sent WHERE sent_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_communications,
                (SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_users,
                (SELECT COUNT(*) FROM timetable WHERE term_id = (SELECT id FROM terms WHERE is_current = 1)) as total_timetable_entries
              FROM dual";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Current term/year info
$current_term = $conn->query("SELECT * FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_year = $conn->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();

// Recent activities
$recent_marks = $conn->query("SELECT m.*, s.first_name, s.last_name, sub.subject_name, u.full_name as recorded_by_name
                               FROM marks m
                               JOIN students s ON m.student_id = s.id
                               JOIN subjects sub ON m.subject_id = sub.id
                               JOIN users u ON m.recorded_by = u.id
                               ORDER BY m.created_at DESC LIMIT 5");

$recent_certificates = $conn->query("SELECT cl.*, s.first_name, s.last_name, s.admission_number
                                      FROM certificates_log cl
                                      JOIN students s ON cl.student_id = s.id
                                      ORDER BY cl.created_at DESC LIMIT 5");

$recent_communications = $conn->query("SELECT rs.*, s.first_name, s.last_name, s.admission_number
                                        FROM results_sent rs
                                        JOIN students s ON rs.student_id = s.id
                                        ORDER BY rs.sent_date DESC LIMIT 5");

$recent_payments = $conn->query("SELECT p.*, s.first_name, s.last_name, s.admission_number
                                  FROM payments p
                                  JOIN students s ON p.student_id = s.id
                                  ORDER BY p.created_at DESC LIMIT 5");

$recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

// Fee summary by class
$fee_summary = $conn->query("SELECT s.class, COUNT(s.id) as student_count, 
                              SUM(f.total_fees) as total_fees, 
                              SUM(f.amount_paid) as paid, 
                              SUM(f.balance) as balance
                              FROM students s
                              JOIN fees f ON s.id = f.student_id
                              WHERE f.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)
                              GROUP BY s.class");

// Top performing class
$top_class = $conn->query("SELECT s.class, ROUND(AVG(m.marks), 2) as avg_score
                            FROM students s
                            JOIN marks m ON s.id = m.student_id
                            WHERE m.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)
                            GROUP BY s.class
                            ORDER BY avg_score DESC LIMIT 1")->fetch_assoc();

// System health check
$system_health = [];
$system_health['db_size'] = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                           FROM information_schema.tables 
                                           WHERE table_schema = 'kitere_cbc_exam_system'")->fetch_assoc();
$system_health['total_records'] = $conn->query("SELECT SUM(TABLE_ROWS) as total 
                                                  FROM information_schema.tables 
                                                  WHERE TABLE_SCHEMA = 'kitere_cbc_exam_system'")->fetch_assoc();

// Get pending approvals (if any)
$pending_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 0")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Kitere CBC Exam System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        /* Header */
        header { background: #002147; color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h2 { font-size: 24px; margin-bottom: 5px; }
        .admin-info { text-align: right; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin-left: 15px; font-size: 14px; }
        
        /* Container */
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        /* Welcome Card */
        .welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #1abc9c; margin: 10px 0; }
        .stat-card .label { color: #666; font-size: 12px; }
        
        /* Section */
        .section { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { color: #002147; margin-bottom: 20px; border-bottom: 2px solid #1abc9c; padding-bottom: 10px; }
        
        /* Actions Grid */
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-top: 20px; }
        .action-card { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; text-decoration: none; color: #333; transition: all 0.3s; border: 1px solid #e0e0e0; }
        .action-card:hover { background: #1abc9c; color: white; transform: translateY(-3px); }
        .action-card .action-icon { font-size: 28px; margin-bottom: 8px; display: block; }
        .action-card .action-title { font-weight: bold; font-size: 13px; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; color: #002147; }
        tr:hover { background: #f5f5f5; }
        
        /* Two Columns */
        .two-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px; }
        
        /* System Health */
        .health-card { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; border-radius: 10px; margin-top: 20px; }
        
        /* Badges */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        @media (max-width: 768px) {
            .header-content { flex-direction: column; text-align: center; }
            .stats-grid { grid-template-columns: 1fr; }
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
            .two-columns { grid-template-columns: 1fr; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">
            <h2>📚 Kitere CBC Exam System</h2>
            <p>Administrator Control Panel</p>
        </div>
        <div class="admin-info">
            <div>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> (Admin)</div>
            <div style="font-size: 12px; opacity: 0.9;">Last login: <?php echo date('d-m-Y H:i'); ?></div>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </div>
</header>

<div class="container">
    <!-- Welcome Card -->
    <div class="welcome-card">
        <h1>System Administration Dashboard</h1>
        <p>Current Term: <?php echo $current_term['term_name'] ?? 'Not set'; ?> | Academic Year: <?php echo $current_year['year'] ?? 'Not set'; ?></p>
        <p>🏆 Top Performing Class: <strong><?php echo $top_class['class'] ?? 'N/A'; ?></strong> (Avg: <?php echo $top_class['avg_score'] ?? 0; ?>%)</p>
        <p>📧 Communications Sent (30 days): <?php echo $stats['recent_communications']; ?> | 👥 Active Users (7 days): <?php echo $stats['active_users']; ?></p>
        <?php if ($pending_teachers['count'] > 0): ?>
        <p style="margin-top: 10px; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px;">⚠️ Pending Approvals: <?php echo $pending_teachers['count']; ?> teacher(s) awaiting account activation</p>
        <?php endif; ?>
    </div>
    
    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='manage-users.php'">
            <div class="number"><?php echo $stats['total_users']; ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-card" onclick="location.href='manage-students.php'">
            <div class="number"><?php echo $stats['total_students']; ?></div>
            <div class="label">Students</div>
        </div>
        <div class="stat-card" onclick="location.href='manage-subjects.php'">
            <div class="number"><?php echo $stats['total_subjects']; ?></div>
            <div class="label">Subjects</div>
        </div>
        <div class="stat-card" onclick="location.href='view-marks.php'">
            <div class="number"><?php echo number_format($stats['total_marks']); ?></div>
            <div class="label">Marks Records</div>
        </div>
        <div class="stat-card" onclick="location.href='fees-management.php'">
            <div class="number">KES <?php echo number_format($stats['total_fees_collected'] ?? 0, 0); ?></div>
            <div class="label">Fees Collected</div>
        </div>
        <div class="stat-card" onclick="location.href='certificate-history.php'">
            <div class="number"><?php echo $stats['total_certificates_issued']; ?></div>
            <div class="label">Certificates Issued</div>
        </div>
        <div class="stat-card" onclick="location.href='communication-log.php'">
            <div class="number"><?php echo $stats['recent_communications']; ?></div>
            <div class="label">Communications (30d)</div>
        </div>
        <div class="stat-card" onclick="location.href='manage-timetable.php'">
            <div class="number"><?php echo $stats['total_timetable_entries']; ?></div>
            <div class="label">Timetable Entries</div>
        </div>
    </div>
    
    <!-- Main Management Sections -->
    <div class="section">
        <h2>🔧 System Management</h2>
        <div class="actions-grid">
            <a href="manage-students.php" class="action-card"><span class="action-icon">👨‍🎓</span><span class="action-title">Manage Students</span></a>
            <a href="manage-users.php" class="action-card"><span class="action-icon">👥</span><span class="action-title">Manage Users</span></a>
            <a href="update-parent-contacts.php" class="action-card"><span class="action-icon">📞</span><span class="action-title">Parent Contacts</span></a>
            <a href="manage-subjects.php" class="action-card"><span class="action-icon">📖</span><span class="action-title">Manage Subjects</span></a>
            <a href="manage-classes.php" class="action-card"><span class="action-icon">🏫</span><span class="action-title">Manage Classes</span></a>
            <a href="manage-terms.php" class="action-card"><span class="action-icon">📅</span><span class="action-title">Terms & Years</span></a>
            <a href="fees-management.php" class="action-card"><span class="action-icon">💰</span><span class="action-title">Fees Management</span></a>
            <a href="take-attendance.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Take Attendance</span></a>
            <a href="view-attendance.php" class="action-card"><span class="action-icon">📊</span><span class="action-title">View Attendance</span></a>
            <a href="manage-timetable.php" class="action-card"><span class="action-icon">📅</span><span class="action-title">Manage Timetable</span></a>
       <!-- Add to System Management section -->
<a href="sms-log.php" class="action-card"><span class="action-icon">📱</span><span class="action-title">SMS History</span></a>
       
        </div>
    </div>
    
    <div class="section">
        <h2>📊 Reports & Analytics</h2>
        <div class="actions-grid">
            <a href="view-marks.php" class="action-card"><span class="action-icon">📊</span><span class="action-title">All Marks</span></a>
            <a href="ranking.php" class="action-card"><span class="action-icon">🏆</span><span class="action-title">Student Rankings</span></a>
            <a href="merit-list.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Merit List</span></a>
            <a href="analytics.php" class="action-card"><span class="action-icon">📈</span><span class="action-title">Advanced Analytics</span></a>
            <a href="reports.php" class="action-card"><span class="action-icon">📄</span><span class="action-title">Generate Reports</span></a>
            <a href="attendance-report.php" class="action-card"><span class="action-icon">📅</span><span class="action-title">Attendance Report</span></a>
        </div>
    </div>
    
    <div class="section">
        <h2>📧 Parent Communication</h2>
        <div class="actions-grid">
            <a href="send-results.php" class="action-card"><span class="action-icon">📧</span><span class="action-title">Send Results</span></a>
            <a href="communication-log.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Communication Log</span></a>
            <a href="update-parent-contacts.php" class="action-card"><span class="action-icon">📞</span><span class="action-title">Bulk Contact Update</span></a>
        </div>
    </div>
    
    <div class="section">
        <h2>📜 Certificate Management</h2>
        <div class="actions-grid">
            <a href="leaving-certificate.php" class="action-card"><span class="action-icon">📜</span><span class="action-title">Issue Certificate</span></a>
            <a href="bulk-certificates.php" class="action-card"><span class="action-icon">📄</span><span class="action-title">Bulk Certificates</span></a>
            <a href="certificate-history.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Certificate History</span></a>
        </div>
    </div>
    
    <div class="section">
        <h2>⚙️ System Tools</h2>
        <div class="actions-grid">
            <a href="backup.php" class="action-card"><span class="action-icon">💾</span><span class="action-title">Database Backup</span></a>
            <a href="system-logs.php" class="action-card"><span class="action-icon">📝</span><span class="action-title">System Logs</span></a>
            <a href="announcements.php" class="action-card"><span class="action-icon">📢</span><span class="action-title">Announcements</span></a>
            <a href="settings.php" class="action-card"><span class="action-icon">⚙️</span><span class="action-title">System Settings</span></a>
        </div>
    </div>
    
    <!-- Recent Activity Section -->
    <div class="two-columns">
        <div class="section">
            <h2>📝 Recent Marks Entered</h2>
            <?php if ($recent_marks && $recent_marks->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Student</th><th>Subject</th><th>Marks</th><th>Recorded By</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php while($row = $recent_marks->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td><span class="badge <?php echo $row['marks'] >= 75 ? 'badge-success' : ($row['marks'] >= 50 ? 'badge-warning' : 'badge-danger'); ?>"><?php echo $row['marks']; ?>%</span></td>
                                <td><?php echo htmlspecialchars($row['recorded_by_name']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p>No recent marks.</p>"; endif; ?>
        </div>
        
        <div class="section">
            <h2>📧 Recent Communications</h2>
            <?php if ($recent_communications && $recent_communications->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Student</th><th>Sent To</th><th>Via</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php while($row = $recent_communications->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo $row['sent_to']; ?></td>
                                <td><span class="badge badge-success"><?php echo strtoupper($row['sent_via']); ?></span></td>
                                <td><?php echo date('d-m-Y H:i', strtotime($row['sent_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p>No recent communications.</p>"; endif; ?>
        </div>
    </div>
    
    <div class="two-columns">
        <div class="section">
            <h2>📜 Recent Certificates</h2>
            <?php if ($recent_certificates && $recent_certificates->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Student</th><th>Certificate No.</th><th>Type</th><th>Issue Date</th></tr></thead>
                        <tbody>
                            <?php while($row = $recent_certificates->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo $row['certificate_number']; ?></td>
                                <td><span class="badge badge-info"><?php echo ucfirst($row['certificate_type']); ?></span></td>
                                <td><?php echo date('d-m-Y', strtotime($row['issue_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p>No certificates issued recently.</p>"; endif; ?>
        </div>
        
        <div class="section">
            <h2>💰 Recent Payments</h2>
            <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Student</th><th>Amount</th><th>Method</th><th>Receipt</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php while($row = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td>KES <?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo $row['payment_method']; ?></td>
                                <td><?php echo $row['receipt_number']; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p>No recent payments.</p>"; endif; ?>
        </div>
    </div>
    
    <div class="two-columns">
        <div class="section">
            <h2>🏫 Fee Summary by Class</h2>
            <?php if ($fee_summary && $fee_summary->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Class</th><th>Students</th><th>Total Fees</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while($row = $fee_summary->fetch_assoc()): 
                                $status = $row['balance'] == 0 ? "Paid" : ($row['balance'] < $row['total_fees'] ? "Partial" : "Unpaid");
                                $status_class = $status == "Paid" ? "badge-success" : ($status == "Partial" ? "badge-warning" : "badge-danger");
                            ?>
                            <tr>
                                <td><?php echo $row['class']; ?></td>
                                <td><?php echo $row['student_count']; ?></td>
                                <td>KES <?php echo number_format($row['total_fees'], 2); ?></td>
                                <td>KES <?php echo number_format($row['paid'], 2); ?></td>
                                <td>KES <?php echo number_format($row['balance'], 2); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p>No fee records for current year.</p>"; endif; ?>
        </div>
        
        <div class="section">
            <h2>👥 Recent User Registrations</h2>
            <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Registered</th></tr></thead>
                        <tbody>
                            <?php while($row = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo $row['username']; ?></td>
                                <td><span class="badge badge-info"><?php echo ucfirst($row['role']); ?></span></td>
                                <td><?php echo $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p>No recent user registrations.</p>"; endif; ?>
        </div>
    </div>
    
    <!-- System Health -->
    <div class="section">
        <h2>🖥️ System Health</h2>
        <div class="health-card">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; opacity: 0.9;">Database Size</div>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $system_health['db_size']['size'] ?? 0; ?> MB</div>
                </div>
                <div>
                    <div style="font-size: 12px; opacity: 0.9;">Total Records</div>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo number_format($system_health['total_records']['total'] ?? 0); ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; opacity: 0.9;">Last Backup</div>
                    <div style="font-size: 16px; font-weight: bold;"><?php echo date('d-m-Y H:i'); ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; opacity: 0.9;">System Status</div>
                    <div style="font-size: 16px; font-weight: bold; color: #27ae60;">✅ Operational</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Navigation -->
    <div class="footer">
        <a href="admin-dashboard.php">🏠 Dashboard</a>
        <a href="manage-students.php">👨‍🎓 Students</a>
        <a href="manage-users.php">👥 Users</a>
        <a href="ranking.php">🏆 Rankings</a>
        <a href="analytics.php">📈 Analytics</a>
        <a href="view-timetable.php">📅 Timetable</a>
        <a href="send-results.php">📧 Parent Comm</a>
        <a href="reports.php">📄 Reports</a>
        <a href="backup.php">💾 Backup</a>
        <a href="logiut.php">🚪 Logout</a>
    </div>
</div>
</body>
</html>