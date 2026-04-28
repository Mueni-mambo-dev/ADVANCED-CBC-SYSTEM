<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$class = isset($_GET['class']) ? $_GET['class'] : '';
$term_id = isset($_GET['term_id']) ? $_GET['term_id'] : '';
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : '';

// Get current if not specified
if (!$term_id) {
    $current_term = $conn->query("SELECT id FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $term_id = $current_term ? $current_term['id'] : 0;
}
if (!$year_id) {
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $year_id = $current_year ? $current_year['id'] : 0;
}

$term_info = $conn->query("SELECT * FROM terms WHERE id = $term_id")->fetch_assoc();
$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();

// Get students for the class
if ($class && !$student_id) {
    $students_list = $conn->query("SELECT id, admission_number, first_name, last_name FROM students WHERE class='$class' ORDER BY first_name");
} elseif ($student_id) {
    $students_list = $conn->query("SELECT id, admission_number, first_name, last_name FROM students WHERE id=$student_id");
} else {
    $students_list = $conn->query("SELECT id, admission_number, first_name, last_name FROM students ORDER BY class, first_name LIMIT 50");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Report Cards - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1000px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { font-weight: bold; font-size: 12px; }
        .filter-group select, .filter-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 20px; background: #1abc9c; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        /* Report Card */
        .report-card { background: white; border: 1px solid #ddd; border-radius: 12px; margin-bottom: 30px; overflow: hidden; page-break-after: always; }
        .report-header { background: #002147; color: white; padding: 20px; text-align: center; }
        .report-body { padding: 20px; }
        .student-info { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .student-info table { width: 100%; }
        .student-info td { padding: 5px; }
        .marks-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .marks-table th, .marks-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .marks-table th { background: #002147; color: white; }
        .summary { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 15px; }
        .summary-item { text-align: center; }
        .summary-item .label { font-size: 12px; color: #666; }
        .summary-item .value { font-size: 24px; font-weight: bold; color: #1abc9c; }
        .grade-EE { color: #27ae60; }
        .grade-ME { color: #f39c12; }
        .grade-AE { color: #e67e22; }
        .grade-BE { color: #e74c3c; }
        .footer-note { margin-top: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
        
        @media print {
            .filter-section, .btn, .actions { display: none; }
            body { background: white; }
            .container { margin: 0; padding: 0; }
            .report-card { page-break-after: always; margin-bottom: 0; }
        }
        
        .actions { margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
<header><h2>📄 Student Report Cards</h2></header>
<div class="container">
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Select Class</label>
                <select name="class">
                    <option value="">-- Select Class --</option>
                    <?php
                    $classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
                    while($c = $classes->fetch_assoc()): ?>
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
                    $terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
                    while($t = $terms->fetch_assoc()): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $term_id == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo $t['term_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Year</label>
                <select name="year_id">
                    <?php
                    $years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
                    while($y = $years->fetch_assoc()): ?>
                        <option value="<?php echo $y['id']; ?>" <?php echo $year_id == $y['id'] ? 'selected' : ''; ?>>
                            <?php echo $y['year']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn">Generate Report Cards</button>
            </div>
        </form>
    </div>
    
    <?php if ($class): ?>
        <div class="actions">
            <button onclick="window.print()" class="btn">🖨️ Print All Report Cards</button>
        </div>
        
        <?php while($student = $students_list->fetch_assoc()):
            // Get student details
            $student_detail = $conn->query("SELECT * FROM students WHERE id = {$student['id']}")->fetch_assoc();
            
            // Get marks for this student
            $marks_sql = "SELECT 
                            sub.subject_code, sub.subject_name,
                            m.marks, m.grade, m.exam_type
                         FROM marks m
                         JOIN subjects sub ON m.subject_id = sub.id
                         WHERE m.student_id = {$student['id']}
                           AND m.term_id = $term_id
                           AND m.academic_year_id = $year_id
                           AND m.exam_type = 'End Term'
                         ORDER BY sub.subject_name";
            $marks_result = $conn->query($marks_sql);
            
            // Calculate totals
            $total_marks = 0;
            $subject_count = 0;
            $grade_points = 0;
            $grade_map = ['EE' => 12, 'ME' => 8, 'AE' => 4, 'BE' => 1];
            
            while($mark = $marks_result->fetch_assoc()) {
                $total_marks += $mark['marks'];
                $subject_count++;
                $grade_points += $grade_map[$mark['grade']];
            }
            $marks_result->data_seek(0);
            $average = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;
            $mean_grade = $subject_count > 0 ? round($grade_points / $subject_count, 1) : 0;
            
            // Determine overall grade
            if ($average >= 75) $overall_grade = "EE";
            elseif ($average >= 50) $overall_grade = "ME";
            elseif ($average >= 25) $overall_grade = "AE";
            else $overall_grade = "BE";
        ?>
        <div class="report-card">
            <div class="report-header">
                <h2>KITERE CBC EXAM SYSTEM</h2>
                <p>Student Academic Report Card</p>
                <p><?php echo $term_info['term_name'] . ' - ' . $year_info['year']; ?></p>
            </div>
            <div class="report-body">
                <div class="student-info">
                    <table>
                        <tr>
                            <td width="50%"><strong>Student Name:</strong> <?php echo $student_detail['first_name'] . ' ' . $student_detail['last_name']; ?></td>
                            <td><strong>Admission No:</strong> <?php echo $student_detail['admission_number']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Class:</strong> <?php echo $student_detail['class']; ?></td>
                            <td><strong>Stream:</strong> <?php echo $student_detail['stream']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Gender:</strong> <?php echo $student_detail['gender']; ?></td>
                            <td><strong>Date of Birth:</strong> <?php echo $student_detail['date_of_birth']; ?></td>
                        </tr>
                    </table>
                </div>
                
                <h3>Subject Performance</h3>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Marks (%)</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($mark = $marks_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $mark['subject_code']; ?></td>
                            <td><?php echo $mark['subject_name']; ?></td>
                            <td><?php echo $mark['marks']; ?>%</td>
                            <td class="grade-<?php echo $mark['grade']; ?>"><?php echo $mark['grade']; ?></td>
                            <td>
                                <?php 
                                if ($mark['grade'] == 'EE') echo "Excellent";
                                elseif ($mark['grade'] == 'ME') echo "Good";
                                elseif ($mark['grade'] == 'AE') echo "Average";
                                else echo "Needs Improvement";
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="summary">
                    <h3>Performance Summary</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="label">Total Subjects</div>
                            <div class="value"><?php echo $subject_count; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Average Score</div>
                            <div class="value"><?php echo $average; ?>%</div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Overall Grade</div>
                            <div class="value grade-<?php echo $overall_grade; ?>"><?php echo $overall_grade; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Mean Grade</div>
                            <div class="value"><?php echo $mean_grade; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Position in Class</div>
                            <div class="value">
                                <?php
                                // Calculate class position
                                $position_sql = "SELECT COUNT(DISTINCT s.id) as better_students
                                                FROM students s
                                                JOIN marks m ON s.id = m.student_id
                                                WHERE s.class = '{$student_detail['class']}'
                                                  AND m.term_id = $term_id
                                                  AND m.academic_year_id = $year_id
                                                GROUP BY s.id
                                                HAVING AVG(m.marks) > $average";
                                $position_result = $conn->query($position_sql);
                                $position = $position_result->num_rows + 1;
                                echo $position . "/" . $subject_count;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="footer-note">
                    <p><strong>Grading Scale:</strong> EE (75-100%) - Excellent | ME (50-74%) - Good | AE (25-49%) - Average | BE (0-24%) - Needs Improvement</p>
                    <p>This is a computer-generated report card and does not require a signature.</p>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php elseif($student_id): ?>
        <!-- Single student report -->
        <?php 
        $student_detail = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
        $marks_sql = "SELECT sub.subject_code, sub.subject_name, m.marks, m.grade FROM marks m JOIN subjects sub ON m.subject_id = sub.id WHERE m.student_id = $student_id AND m.term_id = $term_id AND m.academic_year_id = $year_id ORDER BY sub.subject_name";
        $marks_result = $conn->query($marks_sql);
        ?>
        <div class="report-card">
            <!-- Same report card structure as above -->
            <div class="report-header">
                <h2>KITERE CBC EXAM SYSTEM</h2>
                <p>Student Academic Report Card</p>
            </div>
            <div class="report-body">
                <!-- Same content as above -->
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 50px; color: #666;">
            <h3>📭 Select a class to generate report cards</h3>
            <p>Please choose a class from the filter above to view student report cards.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>