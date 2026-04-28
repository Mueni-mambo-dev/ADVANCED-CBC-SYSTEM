<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: LOGIN.html");
    exit();
}
include "db.php";

// Get filter parameters
$class = isset($_GET['class']) ? $_GET['class'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get current year if not specified
if (!$year_id) {
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $year_id = $current_year ? $current_year['id'] : 0;
}

// Fetch classes for filter
$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");

$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();

// Build WHERE clause
$where = "f.academic_year_id = $year_id";
if ($class) $where .= " AND s.class = '$class'";
if ($status) $where .= " AND f.status = '$status'";

// Get fee summary by class
$class_summary = $conn->query("SELECT 
                                s.class,
                                COUNT(DISTINCT s.id) as total_students,
                                SUM(f.total_fees) as total_fees,
                                SUM(f.amount_paid) as total_paid,
                                SUM(f.balance) as total_balance,
                                ROUND((SUM(f.amount_paid) / NULLIF(SUM(f.total_fees), 0)) * 100, 2) as collection_rate
                              FROM students s
                              JOIN fees f ON s.id = f.student_id
                              WHERE f.academic_year_id = $year_id
                              GROUP BY s.class
                              ORDER BY s.class");

// Get detailed fee report
$detailed_report = $conn->query("SELECT 
                                  s.id,
                                  s.admission_number,
                                  s.first_name,
                                  s.last_name,
                                  s.class,
                                  s.stream,
                                  s.parent_name,
                                  s.parent_phone,
                                  s.parent_email,
                                  f.total_fees,
                                  f.amount_paid,
                                  f.balance,
                                  f.status,
                                  f.last_payment_date
                                FROM students s
                                JOIN fees f ON s.id = f.student_id
                                WHERE $where
                                ORDER BY s.class, s.first_name");

// Get payment summary
$payment_summary = $conn->query("SELECT 
                                  COUNT(*) as total_payments,
                                  SUM(amount) as total_amount,
                                  payment_method,
                                  DATE_FORMAT(payment_date, '%M %Y') as month_year
                                FROM payments p
                                JOIN fees f ON p.student_id = f.student_id
                                WHERE f.academic_year_id = $year_id
                                GROUP BY payment_method, month_year
                                ORDER BY payment_date DESC
                                LIMIT 20");

// Get outstanding balance summary
$outstanding_summary = $conn->query("SELECT 
                                      COUNT(*) as students_with_balance,
                                      SUM(balance) as total_outstanding,
                                      AVG(balance) as avg_balance
                                    FROM fees f
                                    WHERE f.academic_year_id = $year_id AND f.balance > 0");

// Get collection trend (last 6 months)
$collection_trend = $conn->query("SELECT 
                                   DATE_FORMAT(payment_date, '%M %Y') as month,
                                   SUM(amount) as amount_collected,
                                   COUNT(*) as payment_count
                                 FROM payments p
                                 JOIN fees f ON p.student_id = f.student_id
                                 WHERE f.academic_year_id = $year_id
                                   AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                 GROUP BY month
                                 ORDER BY payment_date ASC");

$stats = $outstanding_summary->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fees Report - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #002147; margin-bottom: 10px; }
        h3 { color: #002147; margin: 20px 0 15px 0; border-bottom: 2px solid #1abc9c; padding-bottom: 8px; }
        .subtitle { color: #666; margin-bottom: 20px; text-align: center; }
        
        /* Filter Section */
        .filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #555; }
        .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 20px; background: #1abc9c; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #16a085; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card .number { font-size: 28px; font-weight: bold; margin: 10px 0; }
        .stat-card .label { font-size: 12px; opacity: 0.9; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        
        /* Status Colors */
        .paid { color: #27ae60; font-weight: bold; }
        .partial { color: #f39c12; font-weight: bold; }
        .unpaid { color: #e74c3c; font-weight: bold; }
        
        /* Progress Bar */
        .progress-bar { width: 100%; background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 20px; }
        .progress-fill { height: 20px; background: #1abc9c; color: white; text-align: center; font-size: 11px; line-height: 20px; }
        
        /* Action Buttons */
        .actions { display: flex; gap: 15px; margin-top: 30px; justify-content: center; flex-wrap: wrap; }
        .btn-print { background: #3498db; }
        .btn-print:hover { background: #2980b9; }
        .btn-excel { background: #27ae60; }
        .btn-excel:hover { background: #229954; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
            .filter-group { min-width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
        
        .total-row { background: #f8f9fa; font-weight: bold; }
        .total-row td { border-top: 2px solid #002147; }
    </style>
</head>
<body>
<header>
    <h2>📊 Kitere CBC Exam System</h2>
    <p>Fees Collection Report</p>
</header>

<div class="container">
    <h2>💰 Fees Report</h2>
    <div class="subtitle">
        Academic Year: <?php echo $year_info['year']; ?>
        <?php if ($class) echo " | Class: $class"; ?>
        <?php if ($status) echo " | Status: $status"; ?>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Report Type</label>
                <select name="report_type">
                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                    <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>Detailed Report</option>
                    <option value="payment" <?php echo $report_type == 'payment' ? 'selected' : ''; ?>>Payment History</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Class</label>
                <select name="class">
                    <option value="">All Classes</option>
                    <?php 
                    $classes->data_seek(0);
                    while($c = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $c['class']; ?>" <?php echo $class == $c['class'] ? 'selected' : ''; ?>>
                            <?php echo $c['class']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Fee Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="Paid" <?php echo $status == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Partial" <?php echo $status == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="Unpaid" <?php echo $status == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Academic Year</label>
                <select name="year_id">
                    <?php 
                    $years->data_seek(0);
                    while($y = $years->fetch_assoc()): ?>
                        <option value="<?php echo $y['id']; ?>" <?php echo $year_id == $y['id'] ? 'selected' : ''; ?>>
                            <?php echo $y['year']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn">📊 Generate Report</button>
            </div>
        </form>
    </div>
    
    <?php if ($report_type == 'summary'): ?>
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number">KES <?php echo number_format($class_summary ? array_sum(array_column($class_summary->fetch_all(MYSQLI_ASSOC), 'total_fees')) : 0, 0); $class_summary->data_seek(0); ?></div>
                <div class="label">Total Fees</div>
            </div>
            <div class="stat-card">
                <div class="number">KES <?php echo number_format($class_summary ? array_sum(array_column($class_summary->fetch_all(MYSQLI_ASSOC), 'total_paid')) : 0, 0); $class_summary->data_seek(0); ?></div>
                <div class="label">Total Collected</div>
            </div>
            <div class="stat-card">
                <div class="number">KES <?php echo number_format($stats['total_outstanding'] ?? 0, 0); ?></div>
                <div class="label">Outstanding Balance</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['students_with_balance'] ?? 0; ?></div>
                <div class="label">Students with Balance</div>
            </div>
        </div>
        
        <!-- Class Summary Table -->
        <h3>📊 Fee Collection by Class</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Students</th>
                        <th>Total Fees (KES)</th>
                        <th>Collected (KES)</th>
                        <th>Balance (KES)</th>
                        <th>Collection Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_students = 0;
                    $total_fees = 0;
                    $total_collected = 0;
                    $total_balance = 0;
                    while($row = $class_summary->fetch_assoc()): 
                        $total_students += $row['total_students'];
                        $total_fees += $row['total_fees'];
                        $total_collected += $row['total_paid'];
                        $total_balance += $row['total_balance'];
                    ?>
                    <tr>
                        <td><strong><?php echo $row['class']; ?></strong></td>
                        <td><?php echo $row['total_students']; ?></td>
                        <td>KES <?php echo number_format($row['total_fees'], 2); ?></td>
                        <td>KES <?php echo number_format($row['total_paid'], 2); ?></td>
                        <td>KES <?php echo number_format($row['total_balance'], 2); ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $row['collection_rate']; ?>%;">
                                    <?php echo $row['collection_rate']; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td><strong>TOTAL</strong></td>
                        <td><strong><?php echo $total_students; ?></strong></td>
                        <td><strong>KES <?php echo number_format($total_fees, 2); ?></strong></td>
                        <td><strong>KES <?php echo number_format($total_collected, 2); ?></strong></td>
                        <td><strong>KES <?php echo number_format($total_balance, 2); ?></strong></td>
                        <td><strong><?php echo $total_fees > 0 ? round(($total_collected / $total_fees) * 100, 2) : 0; ?>%</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Outstanding Students -->
        <h3>⚠️ Students with Outstanding Balance</h3>
        <?php
        $outstanding_students = $conn->query("SELECT s.admission_number, s.first_name, s.last_name, s.class, f.balance, f.status
                                               FROM students s
                                               JOIN fees f ON s.id = f.student_id
                                               WHERE f.academic_year_id = $year_id AND f.balance > 0
                                               ORDER BY f.balance DESC
                                               LIMIT 20");
        ?>
        <?php if ($outstanding_students && $outstanding_students->num_rows > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Admission</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Outstanding Balance (KES)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($os = $outstanding_students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $os['admission_number']; ?></td>
                                <td><?php echo $os['first_name'] . ' ' . $os['last_name']; ?></td>
                                <td><?php echo $os['class']; ?></td>
                                <td class="unpaid">KES <?php echo number_format($os['balance'], 2); ?></td>
                                <td><?php echo $os['status']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No students with outstanding balance.</p>
        <?php endif; ?>
        
    <?php elseif ($report_type == 'detailed'): ?>
        <!-- Detailed Student Fee Report -->
        <h3>📋 Detailed Student Fee Report</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Admission</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Parent Name</th>
                        <th>Parent Phone</th>
                        <th>Total Fees</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Last Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_fees_detail = 0;
                    $total_paid_detail = 0;
                    $total_balance_detail = 0;
                    while($row = $detailed_report->fetch_assoc()): 
                        $total_fees_detail += $row['total_fees'];
                        $total_paid_detail += $row['amount_paid'];
                        $total_balance_detail += $row['balance'];
                        $status_class = '';
                        if ($row['status'] == 'Paid') $status_class = 'paid';
                        elseif ($row['status'] == 'Partial') $status_class = 'partial';
                        else $status_class = 'unpaid';
                    ?>
                    <tr>
                        <td><?php echo $row['admission_number']; ?></td>
                        <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                        <td><?php echo $row['class'] . ' ' . $row['stream']; ?></td>
                        <td><?php echo $row['parent_name']; ?></td>
                        <td><?php echo $row['parent_phone']; ?></td>
                        <td>KES <?php echo number_format($row['total_fees'], 2); ?></td>
                        <td>KES <?php echo number_format($row['amount_paid'], 2); ?></td>
                        <td class="<?php echo $status_class; ?>">KES <?php echo number_format($row['balance'], 2); ?></td>
                        <td class="<?php echo $status_class; ?>"><?php echo $row['status']; ?></td>
                        <td><?php echo $row['last_payment_date'] ? date('d-m-Y', strtotime($row['last_payment_date'])) : '-'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td colspan="5"><strong>TOTAL</strong></td>
                        <td><strong>KES <?php echo number_format($total_fees_detail, 2); ?></strong></td>
                        <td><strong>KES <?php echo number_format($total_paid_detail, 2); ?></strong></td>
                        <td><strong>KES <?php echo number_format($total_balance_detail, 2); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($report_type == 'payment'): ?>
        <!-- Payment History -->
        <h3>📜 Payment History</h3>
        <?php if ($payment_summary && $payment_summary->num_rows > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Payment Method</th>
                            <th>Number of Payments</th>
                            <th>Total Amount (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_payments_count = 0;
                        $total_payments_amount = 0;
                        while($ps = $payment_summary->fetch_assoc()): 
                            $total_payments_count += $ps['total_payments'];
                            $total_payments_amount += $ps['total_amount'];
                        ?>
                        <tr>
                            <td><?php echo $ps['month_year']; ?></td>
                            <td><?php echo $ps['payment_method']; ?></td>
                            <td><?php echo $ps['total_payments']; ?></td>
                            <td>KES <?php echo number_format($ps['total_amount'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL</strong></td>
                            <td><strong><?php echo $total_payments_count; ?></strong></td>
                            <td><strong>KES <?php echo number_format($total_payments_amount, 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No payment records found.</p>
        <?php endif; ?>
        
        <!-- Collection Trend -->
        <h3>📈 Collection Trend (Last 6 Months)</h3>
        <?php if ($collection_trend && $collection_trend->num_rows > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Payments Count</th>
                            <th>Amount Collected (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($ct = $collection_trend->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $ct['month']; ?></td>
                            <td><?php echo $ct['payment_count']; ?></td>
                            <td>KES <?php echo number_format($ct['amount_collected'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No collection data available.</p>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Action Buttons -->
    <div class="actions">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Report</button>
        <button onclick="exportToExcel()" class="btn btn-excel">📊 Export to Excel</button>
        <a href="fees-management.php" class="btn">💰 Back to Fees Management</a>
    </div>
    
    <div class="footer">
        <a href="admin-dashboard.php">🏠 Dashboard</a>
        <a href="fees-management.php">💰 Fees Management</a>
        <a href="fees-report.php">📊 Fees Report</a>
    </div>
</div>

<script>
function exportToExcel() {
    var tables = document.querySelectorAll('table');
    var html = '<html><head><title>Fees Report</title></head><body>';
    tables.forEach(function(table) {
        html += table.outerHTML + '<br><br>';
    });
    html += '</body></html>';
    
    var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    var link = document.createElement('a');
    link.download = 'fees_report.xls';
    link.href = url;
    link.click();
}
</script>
</body>
</html>