<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

// Get parameters
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$certificate_type = isset($_GET['type']) ? $_GET['type'] : 'standard'; // standard, merit, transfer
$generate = isset($_GET['generate']) ? $_GET['generate'] : '';

// Get all active students
$students = $conn->query("SELECT id, admission_number, first_name, last_name, class FROM students WHERE status='Active' ORDER BY first_name");

// If generate is true, show certificate
if ($generate && $student_id) {
    // Get student details
    $student_sql = "SELECT * FROM students WHERE id = $student_id";
    $student_result = $conn->query($student_sql);
    $student = $student_result->fetch_assoc();
    
    // Get academic performance summary
    $performance_sql = "SELECT 
                           ROUND(AVG(m.marks), 2) as overall_average,
                           COUNT(DISTINCT m.subject_id) as subjects_taken,
                           SUM(CASE WHEN m.grade = 'EE' THEN 1 ELSE 0 END) as ee_count,
                           SUM(CASE WHEN m.grade = 'ME' THEN 1 ELSE 0 END) as me_count,
                           SUM(CASE WHEN m.grade = 'AE' THEN 1 ELSE 0 END) as ae_count,
                           SUM(CASE WHEN m.grade = 'BE' THEN 1 ELSE 0 END) as be_count,
                           MAX(m.marks) as highest_score,
                           MIN(m.marks) as lowest_score
                       FROM marks m
                       WHERE m.student_id = $student_id";
    $performance_result = $conn->query($performance_sql);
    $performance = $performance_result->fetch_assoc();
    
    // Get subject-wise performance
    $subjects_sql = "SELECT 
                        sub.subject_name,
                        ROUND(AVG(m.marks), 2) as avg_marks,
                        m.grade
                    FROM marks m
                    JOIN subjects sub ON m.subject_id = sub.id
                    WHERE m.student_id = $student_id
                    GROUP BY sub.subject_name
                    ORDER BY avg_marks DESC";
    $subjects_result = $conn->query($subjects_sql);
    
    // Get attendance summary
    $attendance_sql = "SELECT 
                          COUNT(*) as total_days,
                          SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as days_present,
                          SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as days_absent,
                          SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as days_late,
                          ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
                      FROM attendance
                      WHERE student_id = $student_id";
    $attendance_result = $conn->query($attendance_sql);
    $attendance = $attendance_result->fetch_assoc();
    
    // Determine overall grade
    if ($performance['overall_average'] >= 75) {
        $overall_grade = "EE (Excellent)";
        $grade_color = "#27ae60";
        $recommendation = "Highly recommended for advanced studies and leadership positions.";
    } elseif ($performance['overall_average'] >= 50) {
        $overall_grade = "ME (Good)";
        $grade_color = "#f39c12";
        $recommendation = "Recommended for further education and skill development.";
    } elseif ($performance['overall_average'] >= 25) {
        $overall_grade = "AE (Average)";
        $grade_color = "#e67e22";
        $recommendation = "Shows potential with additional support and guidance.";
    } else {
        $overall_grade = "BE (Below Expectations)";
        $grade_color = "#e74c3c";
        $recommendation = "Requires additional support and remedial programs.";
    }
    
    // Generate certificate number
    $certificate_number = "KIT/CBC/" . date('Y') . "/" . str_pad($student_id, 5, '0', STR_PAD_LEFT);
    $issue_date = date('d F, Y');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leaving Certificate - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Times New Roman', Georgia, serif; 
            background: #e0e0e0; 
            padding: 40px;
        }
        
        /* Certificate Container */
        .certificate-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border-radius: 12px;
        }
        
        /* Certificate Border */
        .certificate-border {
            border: 8px double #002147;
            padding: 40px;
            position: relative;
            background: white;
        }
        
        /* Header Section */
        .certificate-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #002147;
            padding-bottom: 20px;
        }
        
        .school-logo {
            width: 100px;
            height: 100px;
            background: #002147;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
        }
        
        .school-name {
            font-size: 32px;
            font-weight: bold;
            color: #002147;
            letter-spacing: 2px;
        }
        
        .school-motto {
            font-size: 14px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        .certificate-title {
            font-size: 28px;
            font-weight: bold;
            color: #002147;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        
        /* Certificate Number */
        .certificate-number {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-bottom: 20px;
        }
        
        /* Content Section */
        .certificate-content {
            margin: 40px 0;
            line-height: 2;
        }
        
        .student-name {
            font-size: 28px;
            font-weight: bold;
            color: #002147;
            text-align: center;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .certificate-text {
            font-size: 16px;
            text-align: justify;
            margin: 20px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #ddd;
            padding: 8px 0;
        }
        
        .info-label {
            font-weight: bold;
            color: #002147;
        }
        
        .info-value {
            color: #333;
        }
        
        /* Performance Table */
        .performance-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .performance-table th,
        .performance-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        .performance-table th {
            background: #002147;
            color: white;
            font-weight: bold;
        }
        
        .performance-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        /* Grade Indicators */
        .grade-excellent { color: #27ae60; font-weight: bold; }
        .grade-good { color: #f39c12; font-weight: bold; }
        .grade-average { color: #e67e22; font-weight: bold; }
        .grade-poor { color: #e74c3c; font-weight: bold; }
        
        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        /* Recommendation Section */
        .recommendation {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #1abc9c;
        }
        
        /* Footer Section */
        .certificate-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #002147;
            display: flex;
            justify-content: space-between;
        }
        
        .signature {
            text-align: center;
            margin-top: 30px;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            margin: 10px auto;
        }
        
        .school-seal {
            text-align: center;
        }
        
        .seal {
            width: 100px;
            height: 100px;
            border: 2px solid #002147;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 12px;
            color: #002147;
        }
        
        /* Action Buttons */
        .action-buttons {
            max-width: 1000px;
            margin: 20px auto;
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background: #1abc9c;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .btn:hover { background: #16a085; }
        .btn-print { background: #3498db; }
        .btn-print:hover { background: #2980b9; }
        .btn-pdf { background: #e74c3c; }
        .btn-pdf:hover { background: #c0392b; }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .action-buttons, .filter-section {
                display: none;
            }
            .certificate-container {
                padding: 20px;
                box-shadow: none;
            }
            .certificate-border {
                border: 4px double #002147;
            }
        }
        
        @media (max-width: 768px) {
            .certificate-container { padding: 20px; }
            .certificate-border { padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-box { grid-template-columns: repeat(2, 1fr); }
            .school-name { font-size: 24px; }
            .student-name { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php if (!$generate): ?>
    <!-- Selection Form -->
    <div class="certificate-container" style="max-width: 600px;">
        <div class="certificate-border">
            <div class="certificate-header">
                <div class="school-logo">📚</div>
                <div class="school-name">KITERE CBC EXAM SYSTEM</div>
                <div class="school-motto">Excellence in Education</div>
                <div class="certificate-title">Leaving Certificate</div>
            </div>
            
            <div class="certificate-content">
                <form method="GET" action="">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Select Student:</label>
                        <select name="student_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">-- Select Student --</option>
                            <?php while($s = $students->fetch_assoc()): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo $s['admission_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['class'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Certificate Type:</label>
                        <select name="type" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="standard">Standard Leaving Certificate</option>
                            <option value="merit">Merit Certificate</option>
                            <option value="transfer">Transfer Certificate</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="generate" value="1">
                    
                    <button type="submit" class="btn" style="width: 100%;">Generate Certificate</button>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Certificate Display -->
    <div class="certificate-container">
        <div class="certificate-border">
            <div class="certificate-number">
                Certificate No: <?php echo $certificate_number; ?>
            </div>
            
            <div class="certificate-header">
                <div class="school-logo">📚</div>
                <div class="school-name">KITERE CBC EXAM SYSTEM</div>
                <div class="school-motto">"Striving for Excellence, Building Character"</div>
                <div class="certificate-title">
                    <?php 
                    if ($certificate_type == 'merit') echo "MERIT CERTIFICATE";
                    elseif ($certificate_type == 'transfer') echo "TRANSFER CERTIFICATE";
                    else echo "LEAVING CERTIFICATE";
                    ?>
                </div>
            </div>
            
            <div class="certificate-content">
                <div class="student-name">
                    <?php echo strtoupper($student['first_name'] . ' ' . $student['last_name']); ?>
                </div>
                
                <div class="certificate-text">
                    This is to certify that the above-named student was a bonafide student of 
                    <strong>Kitere CBC Exam System</strong> and has successfully completed the course of study 
                    prescribed for the academic year(s) with satisfactory conduct and performance.
                </div>
                
                <!-- Student Information -->
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Admission Number:</span>
                        <span class="info-value"><?php echo $student['admission_number']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?php echo date('d F, Y', strtotime($student['date_of_birth'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo $student['gender']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Class:</span>
                        <span class="info-value"><?php echo $student['class'] . ' ' . $student['stream']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Parent/Guardian:</span>
                        <span class="info-value"><?php echo $student['parent_name']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Parent Contact:</span>
                        <span class="info-value"><?php echo $student['parent_phone']; ?></span>
                    </div>
                </div>
                
                <!-- Academic Performance -->
                <?php if ($performance['subjects_taken'] > 0): ?>
                <h3 style="color: #002147; margin-top: 20px;">Academic Performance Summary</h3>
                
                <div class="summary-box">
                    <div class="summary-item">
                        <div class="summary-label">Overall Average</div>
                        <div class="summary-value"><?php echo $performance['overall_average']; ?>%</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Subjects Taken</div>
                        <div class="summary-value"><?php echo $performance['subjects_taken']; ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Highest Score</div>
                        <div class="summary-value"><?php echo $performance['highest_score']; ?>%</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Overall Grade</div>
                        <div class="summary-value" style="color: <?php echo $grade_color; ?>"><?php echo $overall_grade; ?></div>
                    </div>
                </div>
                
                <!-- Subject Performance Table -->
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Average Score</th>
                            <th>Grade</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($sub = $subjects_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $sub['subject_name']; ?></td>
                            <td><?php echo $sub['avg_marks']; ?>%</td>
                            <td class="grade-<?php echo strtolower($sub['grade']); ?>"><?php echo $sub['grade']; ?></td>
                            <td>
                                <?php 
                                if ($sub['avg_marks'] >= 75) echo "Excellent";
                                elseif ($sub['avg_marks'] >= 50) echo "Good";
                                elseif ($sub['avg_marks'] >= 25) echo "Average";
                                else echo "Needs Improvement";
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <!-- Grade Distribution -->
                <?php if ($performance['subjects_taken'] > 0): ?>
                <div style="margin: 20px 0;">
                    <h4>Grade Distribution:</h4>
                    <div style="display: flex; gap: 20px; margin-top: 10px;">
                        <div><span style="color:#27ae60;">●</span> EE: <?php echo $performance['ee_count']; ?></div>
                        <div><span style="color:#f39c12;">●</span> ME: <?php echo $performance['me_count']; ?></div>
                        <div><span style="color:#e67e22;">●</span> AE: <?php echo $performance['ae_count']; ?></div>
                        <div><span style="color:#e74c3c;">●</span> BE: <?php echo $performance['be_count']; ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Attendance Summary -->
                <?php if ($attendance && $attendance['total_days'] > 0): ?>
                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h4>Attendance Record:</h4>
                    <div style="display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap;">
                        <div>📅 Total Days: <?php echo $attendance['total_days']; ?></div>
                        <div>✅ Present: <?php echo $attendance['days_present']; ?></div>
                        <div>❌ Absent: <?php echo $attendance['days_absent']; ?></div>
                        <div>⏰ Late: <?php echo $attendance['days_late']; ?></div>
                        <div>📊 Attendance Rate: <strong><?php echo $attendance['attendance_percentage']; ?>%</strong></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recommendation -->
                <div class="recommendation">
                    <strong>📝 Principal's Recommendation:</strong><br>
                    <?php echo $recommendation; ?>
                    <br><br>
                    The student is hereby granted permission to pursue further education or employment 
                    as per the regulations of the education ministry.
                </div>
                
                <!-- Character Assessment -->
                <div style="margin: 20px 0;">
                    <h4>Character Assessment:</h4>
                    <p>The student has demonstrated <?php 
                        if ($performance['overall_average'] >= 50) echo "commendable";
                        else echo "satisfactory";
                    ?> conduct, <?php 
                        if ($attendance['attendance_percentage'] >= 80) echo "excellent";
                        else echo "adequate";
                    ?> attendance, and <?php 
                        if ($performance['overall_average'] >= 60) echo "strong";
                        else echo "developing";
                    ?> academic commitment during their time at the institution.</p>
                </div>
            </div>
            
            <!-- Footer with Signatures -->
            <div class="certificate-footer">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Class Teacher</div>
                    <div style="font-size: 12px; color: #666;">Name: _________________</div>
                </div>
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Principal / Head of School</div>
                    <div style="font-size: 12px; color: #666;">Dr. James M. Otieno</div>
                </div>
                <div class="school-seal">
                    <div class="seal">
                        SCHOOL<br>SEAL
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
                Issued on: <?php echo $issue_date; ?> | This certificate is electronically generated and valid without signature
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Certificate</button>
        <button onclick="generatePDF()" class="btn btn-pdf">📄 Save as PDF</button>
        <a href="leaving-certificate.php" class="btn">🔄 Generate Another</a>
        <a href="dashboard.php" class="btn">🏠 Dashboard</a>
    </div>
<?php endif; ?>

<script>
function generatePDF() {
    window.print();
}

// Auto-hide action buttons when printing
window.onbeforeprint = function() {
    var buttons = document.querySelector('.action-buttons');
    if (buttons) buttons.style.display = 'none';
}

window.onafterprint = function() {
    var buttons = document.querySelector('.action-buttons');
    if (buttons) buttons.style.display = 'block';
}
</script>
</body>
</html>