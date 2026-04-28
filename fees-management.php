<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: LOGIN.html");
    exit();
}
include "db.php";

// Set fee structure for a class
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_fees'])) {
    $class = $_POST['class'];
    $academic_year_id = $_POST['academic_year_id'];
    $total_fees = $_POST['total_fees'];
    
    $students = $conn->query("SELECT id FROM students WHERE class='$class'");
    $inserted = 0;
    while($s = $students->fetch_assoc()) {
        $check = $conn->query("SELECT id FROM fees WHERE student_id={$s['id']} AND academic_year_id=$academic_year_id");
        if($check->num_rows == 0) {
            $conn->query("INSERT INTO fees (student_id, academic_year_id, total_fees, amount_paid, balance, status) 
                         VALUES ({$s['id']}, $academic_year_id, $total_fees, 0, $total_fees, 'Unpaid')");
            $inserted++;
        } else {
            // Update existing fee structure
            $conn->query("UPDATE fees SET total_fees = $total_fees, balance = total_fees - amount_paid 
                         WHERE student_id={$s['id']} AND academic_year_id=$academic_year_id");
        }
    }
    $_SESSION['message'] = "Fee structure set for $class ($inserted students updated)";
    $_SESSION['message_type'] = "success";
    header("Location: fees-management.php");
    exit();
}

// Record payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_payment'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $reference_number = isset($_POST['reference_number']) ? $_POST['reference_number'] : '';
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Generate unique receipt number
    $receipt_number = "RCP" . date('Ymd') . rand(100, 999) . $student_id;
    
    // Get current academic year
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $academic_year_id = $current_year ? $current_year['id'] : 0;
    
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_date, payment_method, reference_number, receipt_number, notes, recorded_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsssssi", $student_id, $amount, $payment_date, $payment_method, $reference_number, $receipt_number, $notes, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        // Update fees table
        $update_sql = "UPDATE fees 
                      SET amount_paid = amount_paid + $amount,
                          balance = total_fees - (amount_paid + $amount),
                          last_payment_date = '$payment_date',
                          status = CASE 
                              WHEN total_fees - (amount_paid + $amount) <= 0 THEN 'Paid'
                              ELSE 'Partial'
                          END
                      WHERE student_id = $student_id AND academic_year_id = $academic_year_id";
        
        if ($conn->query($update_sql)) {
            $_SESSION['message'] = "Payment recorded successfully! Receipt No: $receipt_number";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Payment recorded but fee balance update failed: " . $conn->error;
            $_SESSION['message_type'] = "warning";
        }
    } else {
        $_SESSION['message'] = "Error recording payment: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    header("Location: fees-management.php");
    exit();
}

// Get academic years
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
$current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_year_id = $current_year ? $current_year['id'] : 0;

// Get fee summary by class
$fee_summary = $conn->query("SELECT s.class, 
                              COUNT(s.id) as student_count, 
                              SUM(f.total_fees) as total_fees, 
                              SUM(f.amount_paid) as paid, 
                              SUM(f.balance) as balance
                              FROM students s
                              JOIN fees f ON s.id = f.student_id
                              WHERE f.academic_year_id = $current_year_id
                              GROUP BY s.class");

// Get all students with fee details
$fees_data = $conn->query("SELECT s.id, s.admission_number, s.first_name, s.last_name, s.class, s.stream,
                           f.total_fees, f.amount_paid, f.balance, f.status, f.last_payment_date
                           FROM students s
                           LEFT JOIN fees f ON s.id = f.student_id AND f.academic_year_id = $current_year_id
                           WHERE s.status = 'Active'
                           ORDER BY s.class, s.first_name");

// Get recent payments
$recent_payments = $conn->query("SELECT p.*, s.first_name, s.last_name, s.admission_number, s.class
                                  FROM payments p
                                  JOIN students s ON p.student_id = s.id
                                  ORDER BY p.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fees Management - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #002147; margin-bottom: 20px; }
        
        .btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 6px; margin: 5px; font-weight: bold; border: none; cursor: pointer; }
        .btn-primary { background: #1abc9c; color: white; }
        .btn-primary:hover { background: #16a085; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #002147; color: white; }
        tr:hover { background: #f5f5f5; }
        
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .paid { color: #27ae60; font-weight: bold; }
        .partial { color: #f39c12; font-weight: bold; }
        .unpaid { color: #e74c3c; font-weight: bold; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; width: 500px; margin: 5% auto; padding: 30px; border-radius: 12px; }
        .close { float: right; font-size: 28px; cursor: pointer; }
        
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stats-card .number { font-size: 28px; font-weight: bold; }
        
        .actions-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
            .modal-content { width: 95%; margin: 10% auto; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
        
        .receipt { font-family: monospace; font-size: 11px; }
    </style>
</head>
<body>
<header><h2>💰 Fees Management System</h2></header>
<div class="container">
    <h2>Fee Management - <?php echo date('Y'); ?> Academic Year</h2>
    
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>
    
    <div class="actions-bar">
        <button class="btn btn-primary" onclick="document.getElementById('feeModal').style.display='block'">💰 Set Fee Structure</button>
        <button class="btn btn-success" onclick="document.getElementById('paymentModal').style.display='block'">💵 Record Payment</button>
        <button class="btn btn-primary" onclick="window.location.href='fees_report.php'">📊 Generate Fee Report</button>
    </div>
    
    <!-- Fee Summary by Class -->
    <h3>📊 Fee Summary by Class</h3>
    <table>
        <thead>
            <tr>
                <th>Class</th>
                <th>Students</th>
                <th>Total Fees (KES)</th>
                <th>Paid (KES)</th>
                <th>Balance (KES)</th>
                <th>Collection %</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $fee_summary->fetch_assoc()): 
                $percentage = $row['total_fees'] > 0 ? round(($row['paid'] / $row['total_fees']) * 100, 2) : 0;
            ?>
            <tr>
                <td><?php echo $row['class']; ?></td>
                <td><?php echo $row['student_count']; ?></td>
                <td>KES <?php echo number_format($row['total_fees'], 2); ?></td>
                <td>KES <?php echo number_format($row['paid'], 2); ?></td>
                <td>KES <?php echo number_format($row['balance'], 2); ?></td>
                <td>
                    <div style="width: 100%; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
                        <div style="width: <?php echo $percentage; ?>%; background: #1abc9c; padding: 5px; text-align: center; color: white; font-size: 11px;">
                            <?php echo $percentage; ?>%
                        </div>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <!-- Student Fee Details -->
    <h3>📋 Student Fee Details</h3>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Admission</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Total Fees</th>
                    <th>Amount Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Last Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($f = $fees_data->fetch_assoc()): 
                    $status_class = '';
                    if ($f['status'] == 'Paid') $status_class = 'paid';
                    elseif ($f['status'] == 'Partial') $status_class = 'partial';
                    else $status_class = 'unpaid';
                ?>
                <tr>
                    <td><?php echo $f['admission_number']; ?></td>
                    <td><?php echo $f['first_name'] . ' ' . $f['last_name']; ?></td>
                    <td><?php echo $f['class'] . ' ' . $f['stream']; ?></td>
                    <td>KES <?php echo number_format($f['total_fees'] ?? 0, 2); ?></td>
                    <td>KES <?php echo number_format($f['amount_paid'] ?? 0, 2); ?></td>
                    <td>KES <?php echo number_format($f['balance'] ?? 0, 2); ?></td>
                    <td class="<?php echo $status_class; ?>"><?php echo $f['status'] ?? 'Unpaid'; ?></td>
                    <td><?php echo $f['last_payment_date'] ? date('d-m-Y', strtotime($f['last_payment_date'])) : '-'; ?></td>
                    <td>
                        <button class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" 
                                onclick="recordPayment(<?php echo $f['id']; ?>, '<?php echo $f['first_name'] . ' ' . $f['last_name']; ?>')">
                            Record Payment
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Recent Payments -->
    <h3>📜 Recent Payments</h3>
    <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Receipt No.</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($p = $recent_payments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                        <td class="receipt"><?php echo $p['receipt_number']; ?></td>
                        <td><?php echo $p['first_name'] . ' ' . $p['last_name']; ?></td>
                        <td><?php echo $p['class']; ?></td>
                        <td>KES <?php echo number_format($p['amount'], 2); ?></td>
                        <td><?php echo $p['payment_method']; ?></td>
                        <td><?php echo $p['reference_number'] ?: '-'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <a href="admin-dashboard.php">🏠 Dashboard</a>
        <a href="fees-management.php">💰 Fees Management</a>
        <a href="fees-report.php">📊 Fee Report</a>
    </div>
</div>

<!-- Set Fee Structure Modal -->
<div id="feeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('feeModal').style.display='none'">&times;</span>
        <h3>Set Fee Structure</h3>
        <form method="POST">
            <div class="form-group">
                <label>Select Class</label>
                <select name="class" required>
                    <option value="">-- Select Class --</option>
                    <?php
                    $classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
                    while($c = $classes->fetch_assoc()) echo "<option value='{$c['class']}'>{$c['class']}</option>";
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <select name="academic_year_id" required>
                    <?php 
                    $years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
                    while($y = $years->fetch_assoc()) {
                        $selected = ($y['is_current'] == 1) ? 'selected' : '';
                        echo "<option value='{$y['id']}' $selected>{$y['year']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Total Fees (KES)</label>
                <input type="number" name="total_fees" step="0.01" required placeholder="e.g., 50000">
            </div>
            <button type="submit" name="set_fees" class="btn btn-primary">Set Fees</button>
        </form>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('paymentModal').style.display='none'">&times;</span>
        <h3>Record Payment</h3>
        <form method="POST" id="paymentForm">
            <div class="form-group">
                <label>Select Student</label>
                <select name="student_id" id="payment_student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php
                    $students = $conn->query("SELECT id, admission_number, first_name, last_name, class FROM students WHERE status='Active' ORDER BY class, first_name");
                    while($s = $students->fetch_assoc()) {
                        echo "<option value='{$s['id']}'>{$s['admission_number']} - {$s['first_name']} {$s['last_name']} ({$s['class']})</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (KES)</label>
                <input type="number" name="amount" step="0.01" required placeholder="Enter amount">
            </div>
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Card">Card</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reference Number (Optional)</label>
                <input type="text" name="reference_number" placeholder="Transaction ID / Cheque No.">
            </div>
            <div class="form-group">
                <label>Notes (Optional)</label>
                <textarea name="notes" rows="2" placeholder="Additional notes"></textarea>
            </div>
            <button type="submit" name="record_payment" class="btn btn-success">Record Payment</button>
        </form>
    </div>
</div>

<script>
function recordPayment(studentId, studentName) {
    document.getElementById('payment_student_id').value = studentId;
    document.getElementById('paymentModal').style.display = 'block';
    // Optional: Add a note showing which student is selected
    var select = document.getElementById('payment_student_id');
    for(var i = 0; i < select.options.length; i++) {
        if(select.options[i].value == studentId) {
            select.selectedIndex = i;
            break;
        }
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>
</body>
</html>