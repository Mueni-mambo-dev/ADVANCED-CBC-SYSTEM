<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: LOGIN.html");
    exit();
}
include "db.php";

// Get filter parameters
$class = isset($_GET['class']) ? $_GET['class'] : '';
$term_id = isset($_GET['term_id']) ? $_GET['term_id'] : '';
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : '';

// Get current term/year if not specified
if (!$term_id) {
    $current_term = $conn->query("SELECT id FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $term_id = $current_term ? $current_term['id'] : 0;
}
if (!$year_id) {
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $year_id = $current_year ? $current_year['id'] : 0;
}

// Fetch data for filters
$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");

// Get students based on filters
$where = $class ? "AND s.class = '$class'" : "";
$students_sql = "SELECT s.*, 
                    (SELECT ROUND(AVG(marks), 2) FROM marks WHERE student_id = s.id AND term_id = $term_id AND academic_year_id = $year_id) as avg_marks,
                    (SELECT COUNT(*) FROM marks WHERE student_id = s.id AND term_id = $term_id AND academic_year_id = $year_id) as subjects_count
                 FROM students s
                 WHERE s.status = 'Active' $where
                 ORDER BY s.class, s.first_name";
$students = $conn->query($students_sql);

$term_info = $conn->query("SELECT * FROM terms WHERE id = $term_id")->fetch_assoc();
$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();

// Process CSV generation for SMSLeopard
$csv_file = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_csv'])) {
    $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];
    
    if (empty($student_ids)) {
        $_SESSION['message'] = "Please select at least one student.";
        $_SESSION['message_type'] = "error";
    } else {
        // Generate CSV file
        $csv_data = [];
        $csv_data[] = ['Phone Number', 'Message', 'Student Name', 'Admission No', 'Class'];
        
        foreach ($student_ids as $sid) {
            $student = $conn->query("SELECT * FROM students WHERE id = $sid")->fetch_assoc();
            
            // Get performance
            $performance = $conn->query("SELECT 
                                            sub.subject_name, 
                                            m.marks, 
                                            m.grade
                                         FROM marks m
                                         JOIN subjects sub ON m.subject_id = sub.id
                                         WHERE m.student_id = $sid 
                                           AND m.term_id = $term_id 
                                           AND m.academic_year_id = $year_id
                                         ORDER BY sub.subject_name")->fetch_all(MYSQLI_ASSOC);
            
            // Calculate average
            $total_marks = array_sum(array_column($performance, 'marks'));
            $subject_count = count($performance);
            $average = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;
            
            // Determine grade
            if ($average >= 75) $grade = "EE";
            elseif ($average >= 50) $grade = "ME";
            elseif ($average >= 25) $grade = "AE";
            else $grade = "BE";
            
            // Format phone number for SMSLeopard (254XXXXXXXXX)
            $phone = preg_replace('/[^0-9]/', '', $student['parent_phone']);
            if (substr($phone, 0, 1) == '0') {
                $phone = '254' . substr($phone, 1);
            }
            if (substr($phone, 0, 4) == '+254') {
                $phone = substr($phone, 1);
            }
            
            // Create SMS message (max 160 chars)
            $message = "KITERE CBC: {$student['first_name']} {$student['last_name']} - Avg: {$average}% - Grade: {$grade} - {$term_info['term_name']} {$year_info['year']}";
            $message = substr($message, 0, 158);
            
            if (!empty($phone) && strlen($phone) == 12) {
                $csv_data[] = [
                    $phone,
                    $message,
                    $student['first_name'] . ' ' . $student['last_name'],
                    $student['admission_number'],
                    $student['class']
                ];
                
                // Log to database
                $log_sql = "INSERT INTO results_sent (student_id, term_id, year_id, sent_to, sent_via, sent_date, message, status) 
                            VALUES ($sid, $term_id, $year_id, '$phone', 'sms', NOW(), '" . mysqli_real_escape_string($conn, $message) . "', 'pending')";
                $conn->query($log_sql);
            }
        }
        
        // Create CSV file
        if (count($csv_data) > 1) {
            if (!file_exists('exports')) {
                mkdir('exports', 0777, true);
            }
            
            $filename = 'smsleopard_batch_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = 'exports/' . $filename;
            
            $file = fopen($filepath, 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            foreach ($csv_data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
            
            $_SESSION['csv_file'] = $filename;
            $_SESSION['csv_count'] = count($csv_data) - 1;
            $_SESSION['message'] = "CSV file generated successfully! " . (count($csv_data) - 1) . " contacts ready.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "No valid phone numbers found for selected students.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    header("Location: send_results.php?class=$class&term_id=$term_id&year_id=$year_id");
    exit();
}

$csv_file = isset($_SESSION['csv_file']) ? $_SESSION['csv_file'] : null;
$csv_count = isset($_SESSION['csv_count']) ? $_SESSION['csv_count'] : null;
unset($_SESSION['csv_file']);
unset($_SESSION['csv_count']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Send Results via SMSLeopard - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .filter-section { background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #555; }
        .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 20px; background: #1abc9c; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #1abc9c; }
        .btn-primary:hover { background: #16a085; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .status-cards { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-card { background: white; padding: 15px 20px; border-radius: 8px; flex: 1; min-width: 150px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .status-card .label { font-size: 12px; color: #666; }
        .status-card .value { font-size: 20px; font-weight: bold; color: #1abc9c; }
        
        .students-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .students-table th, .students-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .students-table th { background: #002147; color: white; }
        .students-table tr:hover { background: #f5f5f5; }
        
        .actions-bar { display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap; align-items: center; }
        .select-all { display: flex; align-items: center; gap: 10px; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .csv-download { background: #e8f4f8; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: center; border: 2px dashed #1abc9c; }
        .csv-download .file-name { font-family: monospace; font-size: 16px; font-weight: bold; margin: 10px 0; }
        
        .instructions { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-top: 20px; }
        .instructions ol { margin-left: 20px; }
        .instructions li { margin: 10px 0; }
        
        .contact-missing { color: #e74c3c; font-size: 11px; }
        .contact-ok { color: #27ae60; font-size: 11px; }
        
        @media (max-width: 768px) {
            .students-table { font-size: 12px; }
            .students-table th, .students-table td { padding: 8px; }
            .filter-group { min-width: 100%; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
        
        .preview-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
<header>
    <h2>📱 Kitere CBC Exam System</h2>
    <p>Send Results to Parents via SMSLeopard</p>
</header>

<div class="container">
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>
    
    <!-- CSV Download Section -->
    <?php if ($csv_file): ?>
    <div class="csv-download">
        <h3>✅ CSV File Ready for SMSLeopard!</h3>
        <p class="file-name">📄 File: <?php echo $csv_file; ?></p>
        <p><?php echo $csv_count; ?> contacts ready to send</p>
        <a href="exports/<?php echo $csv_file; ?>" download class="btn btn-success" style="margin-top: 10px;">📥 Download CSV File</a>
        <button onclick="copyInstructions()" class="btn btn-primary" style="margin-top: 10px;">📋 Copy SMSLeopard Instructions</button>
    </div>
    <?php endif; ?>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Select Class</label>
                <select name="class">
                    <option value="">All Classes</option>
                    <?php while($c = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $c['class']; ?>" <?php echo $class == $c['class'] ? 'selected' : ''; ?>>
                            <?php echo $c['class']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Term</label>
                <select name="term_id">
                    <?php 
                    $terms->data_seek(0);
                    while($t = $terms->fetch_assoc()): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $term_id == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo $t['term_name']; ?>
                        </option>
                    <?php endwhile; ?>
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
                <button type="submit" class="btn btn-primary">Load Students</button>
            </div>
        </form>
    </div>
    
    <?php if ($students && $students->num_rows > 0): 
        $total_students = 0;
        $has_phone = 0;
        while($s = $students->fetch_assoc()) {
            $total_students++;
            if (!empty($s['parent_phone'])) $has_phone++;
        }
        $students->data_seek(0);
    ?>
        <!-- Status Cards -->
        <div class="status-cards">
            <div class="status-card">
                <div class="label">Total Students</div>
                <div class="value"><?php echo $total_students; ?></div>
            </div>
            <div class="status-card">
                <div class="label">Have Phone Number</div>
                <div class="value"><?php echo $has_phone; ?></div>
            </div>
        </div>
        
        <form method="POST" id="sendForm">
            <input type="hidden" name="term_id" value="<?php echo $term_id; ?>">
            <input type="hidden" name="year_id" value="<?php echo $year_id; ?>">
            <input type="hidden" name="class" value="<?php echo $class; ?>">
            
            <!-- Actions Bar -->
            <div class="actions-bar">
                <div class="select-all">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                    <label>Select All</label>
                </div>
                <button type="submit" name="generate_csv" class="btn btn-success" onclick="return validateSelection()">
                    📱 Generate CSV for SMSLeopard
                </button>
            </div>
            
            <!-- SMS Message Preview Info -->
            <div class="instructions" style="background: #fff3cd; margin-bottom: 20px;">
                <h4>📱 SMS Message Format (160 characters max):</h4>
                <p><strong>Example:</strong> "KITERE CBC: James Otieno - Avg: 78.5% - Grade: ME - Term 1 2026"</p>
                <p>Each SMS will automatically include: Student Name, Average Score, Grade, Term, and Year.</p>
            </div>
            
            <!-- Students Table -->
            <table class="students-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()"></th>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Parent Phone</th>
                        <th>Average (%)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($s = $students->fetch_assoc()): 
                        $has_phone = !empty($s['parent_phone']);
                        $avg = $s['avg_marks'] ?? 0;
                        $grade_class = $avg >= 75 ? 'grade-EE' : ($avg >= 50 ? 'grade-ME' : ($avg >= 25 ? 'grade-AE' : 'grade-BE'));
                        
                        // Format phone for display
                        $phone_display = $s['parent_phone'];
                        if ($phone_display && substr($phone_display, 0, 3) == '254') {
                            $phone_display = '0' . substr($phone_display, 3);
                        }
                    ?>
                        <tr>
                            <td><input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" class="student-checkbox" <?php echo $has_phone ? '' : 'disabled'; ?>></td>
                            <td><?php echo $s['admission_number']; ?></td>
                            <td><strong><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></strong></td>
                            <td><?php echo $s['class'] . ' ' . $s['stream']; ?></td>
                            <td><?php echo $has_phone ? $phone_display : '<span class="contact-missing">❌ Not set</span>'; ?></td>
                            <td class="<?php echo $grade_class; ?>"><strong><?php echo $avg; ?>%</strong></td>
                            <td>
                                <?php if($has_phone): ?>
                                    <span class="contact-ok">✅ Ready for SMS</span>
                                <?php else: ?>
                                    <span class="contact-missing">⚠️ No phone number</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </form>
        
        <!-- SMS Preview Section -->
        <?php 
        // Get first student for preview
        $students->data_seek(0);
        $preview_student = $students->fetch_assoc();
        if ($preview_student && !empty($preview_student['parent_phone'])):
            $preview_avg = $preview_student['avg_marks'] ?? 0;
            $preview_grade = $preview_avg >= 75 ? 'EE' : ($preview_avg >= 50 ? 'ME' : ($preview_avg >= 25 ? 'AE' : 'BE'));
        ?>
        <div class="instructions">
            <h3>📱 SMS Preview Example</h3>
            <div class="preview-box">
                KITERE CBC: <?php echo $preview_student['first_name'] . ' ' . $preview_student['last_name']; ?> - Avg: <?php echo $preview_avg; ?>% - Grade: <?php echo $preview_grade; ?> - <?php echo $term_info['term_name'] . ' ' . $year_info['year']; ?>
            </div>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">Characters: <span id="charCount">0</span>/160</p>
        </div>
        <?php endif; ?>
        
        <!-- SMSLeopard Instructions -->
        <div class="instructions">
            <h3>📋 How to Send SMS via SMSLeopard:</h3>
            <ol>
                <li>Select the students you want to notify (use "Select All" if needed)</li>
                <li>Click <strong>"Generate CSV for SMSLeopard"</strong></li>
                <li>Download the generated CSV file</li>
                <li>Go to <a href="https://smsleopard.com" target="_blank">https://smsleopard.com</a> and login</li>
                <li>Navigate to <strong>SMS → Send SMS → Bulk SMS</strong></li>
                <li>Upload the CSV file (map Phone Number and Message columns)</li>
                <li>Review and click <strong>"Send Now"</strong></li>
            </ol>
        </div>
        
    <?php else: ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
            <h3>📭 No students found</h3>
            <p>Please select a class or term to load students.</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <a href="DASHBOARD.php">🏠 Dashboard</a>
        <a href="VIEW_MARKS.php">📊 View Marks</a>
        <a href="send_results.php">📱 Send Results</a>
        <a href="communication_log.php">📋 Communication Log</a>
        <a href="update_parent_contacts.php">📞 Update Contacts</a>
    </div>
</div>

<script>
function toggleSelectAll() {
    var checkboxes = document.querySelectorAll('.student-checkbox:enabled');
    var selectAll = document.getElementById('selectAllCheckbox');
    for(var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = selectAll.checked;
    }
}

function validateSelection() {
    var selected = document.querySelectorAll('.student-checkbox:checked');
    if(selected.length === 0) {
        alert('Please select at least one student to generate CSV.');
        return false;
    }
    return confirm('Generate CSV for ' + selected.length + ' student(s)? This will prepare an SMS file for SMSLeopard.');
}

function copyInstructions() {
    var instructions = "SMSLeopard Instructions:\n\n1. Go to https://smsleopard.com and login\n2. Navigate to SMS → Send SMS → Bulk SMS\n3. Upload the CSV file\n4. Map columns: Phone Number and Message\n5. Click 'Send Now'";
    navigator.clipboard.writeText(instructions);
    alert("Instructions copied to clipboard!");
}

// Update character count for preview
function updateCharCount() {
    var previewText = document.querySelector('.preview-box');
    if (previewText) {
        var text = previewText.innerText || previewText.textContent;
        var count = text.length;
        var charSpan = document.getElementById('charCount');
        if (charSpan) {
            charSpan.innerText = count;
            if (count > 160) {
                charSpan.style.color = 'red';
            } else {
                charSpan.style.color = '#666';
            }
        }
    }
}

// Run on load
document.addEventListener('DOMContentLoaded', function() {
    updateCharCount();
});
</script>
</body>
</html>