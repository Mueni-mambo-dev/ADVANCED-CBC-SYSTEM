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
$exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : 'End Term';
$subject_filter = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

// Get current active term/year if not specified
if (!$term_id) {
    $current_term = $conn->query("SELECT id FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $term_id = $current_term ? $current_term['id'] : 0;
}
if (!$year_id) {
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $year_id = $current_year ? $current_year['id'] : 0;
}

// If still no term/year, get any available
if (!$term_id) {
    $any_term = $conn->query("SELECT id FROM terms LIMIT 1")->fetch_assoc();
    $term_id = $any_term ? $any_term['id'] : 0;
}
if (!$year_id) {
    $any_year = $conn->query("SELECT id FROM academic_years LIMIT 1")->fetch_assoc();
    $year_id = $any_year ? $any_year['id'] : 0;
}

// Fetch all available data for filters
$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
$exam_types = ['CAT1', 'CAT2', 'CAT3', 'End Term', 'Assignment', 'Project', 'Mid Term'];
$subjects = $conn->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name");

$term_info = $conn->query("SELECT * FROM terms WHERE id = $term_id")->fetch_assoc();
$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();

// Check if we have marks
$check_marks = $conn->query("SELECT COUNT(*) as total FROM marks WHERE term_id = $term_id AND academic_year_id = $year_id")->fetch_assoc();
$has_marks = $check_marks['total'] > 0;

// Build the merit list query
if ($subject_filter) {
    // For single subject merit
    $sql = "SELECT 
                s.id, 
                s.admission_number, 
                s.first_name, 
                s.last_name, 
                s.class, 
                s.stream,
                COALESCE(m.marks, 0) as marks, 
                COALESCE(m.grade, 'N/A') as grade,
                sub.subject_name
            FROM students s
            LEFT JOIN marks m ON s.id = m.student_id 
                AND m.subject_id = $subject_filter
                AND m.term_id = $term_id 
                AND m.academic_year_id = $year_id 
                AND m.exam_type = '$exam_type'
            LEFT JOIN subjects sub ON m.subject_id = sub.id
            WHERE s.status = 'Active' " . ($class ? "AND s.class = '$class'" : "") . "
            ORDER BY marks DESC";
} else {
    // For overall performance (all subjects combined)
    $sql = "SELECT 
                s.id, 
                s.admission_number, 
                s.first_name, 
                s.last_name, 
                s.class, 
                s.stream,
                COUNT(m.id) as subjects_taken,
                COALESCE(SUM(m.marks), 0) as total_marks,
                ROUND(COALESCE(AVG(m.marks), 0), 2) as average_marks,
                COALESCE(MAX(m.marks), 0) as highest_mark,
                COALESCE(MIN(m.marks), 0) as lowest_mark,
                SUM(CASE WHEN m.grade = 'EE' THEN 1 ELSE 0 END) as ee_count,
                SUM(CASE WHEN m.grade = 'ME' THEN 1 ELSE 0 END) as me_count,
                SUM(CASE WHEN m.grade = 'AE' THEN 1 ELSE 0 END) as ae_count,
                SUM(CASE WHEN m.grade = 'BE' THEN 1 ELSE 0 END) as be_count
            FROM students s
            LEFT JOIN marks m ON s.id = m.student_id 
                AND m.term_id = $term_id 
                AND m.academic_year_id = $year_id 
                AND m.exam_type = '$exam_type'
            WHERE s.status = 'Active' " . ($class ? "AND s.class = '$class'" : "") . "
            GROUP BY s.id
            ORDER BY average_marks DESC, total_marks DESC";
}

$merit_result = $conn->query($sql);
$total_students = $merit_result ? $merit_result->num_rows : 0;

// Get statistics
$overall_avg = 0;
$top_student = null;

if ($total_students > 0 && !$subject_filter) {
    $merit_result->data_seek(0);
    $top_student = $merit_result->fetch_assoc();
    $merit_result->data_seek(0);
    
    // Calculate overall average
    $temp_result = clone $merit_result;
    $avg_sum = 0;
    while($row = $temp_result->fetch_assoc()) {
        $avg_sum += $row['average_marks'];
    }
    $overall_avg = $total_students > 0 ? round($avg_sum / $total_students, 2) : 0;
    $merit_result->data_seek(0);
}

// Get class performance comparison
$class_performance = [];
if (!$subject_filter && !$class && $term_id && $year_id && $has_marks) {
    $class_sql = "SELECT 
                    s.class,
                    COUNT(DISTINCT s.id) as student_count,
                    ROUND(COALESCE(AVG(m.marks), 0), 2) as class_average,
                    COALESCE(MAX(m.marks), 0) as highest_score,
                    COALESCE(MIN(m.marks), 0) as lowest_score,
                    ROUND((SUM(CASE WHEN m.marks >= 50 THEN 1 ELSE 0 END) / NULLIF(COUNT(m.id), 0)) * 100, 2) as pass_rate
                  FROM students s
                  LEFT JOIN marks m ON s.id = m.student_id 
                      AND m.term_id = $term_id 
                      AND m.academic_year_id = $year_id 
                      AND m.exam_type = '$exam_type'
                  WHERE s.status = 'Active'
                  GROUP BY s.class
                  ORDER BY class_average DESC";
    $class_result = $conn->query($class_sql);
    if ($class_result) {
        while($row = $class_result->fetch_assoc()) {
            $class_performance[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Merit List - Kitere CBC Exam System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
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
        .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn { padding: 10px 25px; background: #1abc9c; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #16a085; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: bold; }
        .stat-card .label { font-size: 13px; margin-top: 5px; opacity: 0.9; }
        
        /* Class Table */
        .class-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .class-table th, .class-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .class-table th { background: #002147; color: white; }
        .class-table tr:hover { background: #f5f5f5; }
        
        /* Merit Table */
        .merit-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .merit-table th, .merit-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .merit-table th { background: #002147; color: white; }
        .merit-table tr:hover { background: #f5f5f5; }
        .rank-1 { background: #ffd700; }
        .rank-2 { background: #c0c0c0; }
        .rank-3 { background: #cd7f32; }
        .rank-badge { display: inline-block; width: 35px; height: 35px; line-height: 35px; text-align: center; border-radius: 50%; font-weight: bold; }
        .rank-badge-1 { background: #ffd700; color: #333; }
        .rank-badge-2 { background: #c0c0c0; color: #333; }
        .rank-badge-3 { background: #cd7f32; color: white; }
        
        /* Grade Colors */
        .grade-EE { color: #27ae60; font-weight: bold; }
        .grade-ME { color: #f39c12; font-weight: bold; }
        .grade-AE { color: #e67e22; font-weight: bold; }
        .grade-BE { color: #e74c3c; font-weight: bold; }
        
        .no-data { text-align: center; padding: 50px; background: #f8f9fa; border-radius: 12px; }
        .no-data h3 { color: #666; margin-bottom: 15px; }
        .no-data p { color: #999; margin-bottom: 20px; }
        
        .alert { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
        
        .actions { margin-top: 30px; text-align: center; }
        .btn-print { background: #3498db; }
        .btn-print:hover { background: #2980b9; }
        .btn-excel { background: #27ae60; }
        .btn-excel:hover { background: #229954; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .merit-table { font-size: 12px; }
            .merit-table th, .merit-table td { padding: 8px; }
            .filter-group { min-width: 100%; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
    </style>
</head>
<body>
<header>
    <h2>📚 Kitere CBC Exam System</h2>
    <p>Student Merit List & Performance Ranking</p>
</header>

<div class="container">
    <h2>🏆 Student Merit List</h2>
    <div class="subtitle">
        <?php if ($term_info && $year_info): ?>
            <?php echo $term_info['term_name'] . ' - ' . $year_info['year']; ?>
        <?php else: ?>
            Select filters and click Generate Merit List
        <?php endif; ?>
        <?php if ($class) echo " | Class: $class"; ?>
        <?php if ($subject_filter) {
            $subject_info = $conn->query("SELECT subject_name FROM subjects WHERE id = $subject_filter")->fetch_assoc();
            echo " | Subject: " . ($subject_info ? $subject_info['subject_name'] : '');
        } ?>
        <?php if ($exam_type) echo " | Exam: $exam_type"; ?>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Class</label>
                <select name="class">
                    <option value="">All Classes</option>
                    <?php 
                    if ($classes && $classes->num_rows > 0) {
                        $classes->data_seek(0);
                        while($c = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $c['class']; ?>" <?php echo $class == $c['class'] ? 'selected' : ''; ?>>
                                <?php echo $c['class']; ?>
                            </option>
                        <?php endwhile;
                    } ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Term</label>
                <select name="term_id">
                    <?php 
                    if ($terms && $terms->num_rows > 0) {
                        $terms->data_seek(0);
                        while($t = $terms->fetch_assoc()): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $term_id == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo $t['term_name']; ?>
                            </option>
                        <?php endwhile;
                    } ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Academic Year</label>
                <select name="year_id">
                    <?php 
                    if ($years && $years->num_rows > 0) {
                        $years->data_seek(0);
                        while($y = $years->fetch_assoc()): ?>
                            <option value="<?php echo $y['id']; ?>" <?php echo $year_id == $y['id'] ? 'selected' : ''; ?>>
                                <?php echo $y['year']; ?>
                            </option>
                        <?php endwhile;
                    } ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Exam Type</label>
                <select name="exam_type">
                    <?php foreach($exam_types as $et): ?>
                        <option value="<?php echo $et; ?>" <?php echo $exam_type == $et ? 'selected' : ''; ?>>
                            <?php echo $et; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Subject (Optional)</label>
                <select name="subject_id">
                    <option value="">All Subjects</option>
                    <?php 
                    if ($subjects && $subjects->num_rows > 0) {
                        $subjects->data_seek(0);
                        while($sub = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo $subject_filter == $sub['id'] ? 'selected' : ''; ?>>
                                <?php echo $sub['subject_name']; ?>
                            </option>
                        <?php endwhile;
                    } ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn">📊 Generate Merit List</button>
            </div>
        </form>
    </div>
    
    <!-- Alert if no marks -->
    <?php if (!$has_marks && ($term_id && $year_id)): ?>
        <div class="alert">
            ⚠️ No marks found for Term: <?php echo $term_info['term_name']; ?> - Year: <?php echo $year_info['year']; ?>
            <br><br>
            <a href="enter-marks.php" style="color: #856404; font-weight: bold;">➕ Click here to enter marks</a>
        </div>
    <?php endif; ?>
    
    <?php if ($merit_result && $total_students > 0): ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_students; ?></div>
            <div class="label">Total Students</div>
        </div>
        <?php if (!$subject_filter): ?>
        <div class="stat-card">
            <div class="number"><?php echo $overall_avg; ?>%</div>
            <div class="label">Overall Average</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $top_student ? $top_student['average_marks'] : 0; ?>%</div>
            <div class="label">Top Score</div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Class Performance Comparison -->
    <?php if (!empty($class_performance)): ?>
    <h3>📊 Class Performance Comparison</h3>
    <div style="overflow-x: auto;">
        <table class="class-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Class</th>
                    <th>Students</th>
                    <th>Average Score</th>
                    <th>Highest Score</th>
                    <th>Lowest Score</th>
                    <th>Pass Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $class_rank = 1;
                foreach($class_performance as $cp): ?>
                <tr>
                    <td><strong>#<?php echo $class_rank; ?></strong></td>
                    <td><strong><?php echo $cp['class']; ?></strong></td>
                    <td><?php echo $cp['student_count']; ?></td>
                    <td><strong><?php echo $cp['class_average']; ?>%</strong></td>
                    <td><?php echo $cp['highest_score']; ?>%</td>
                    <td><?php echo $cp['lowest_score']; ?>%</td>
                    <td><?php echo $cp['pass_rate']; ?>%</td>
                </tr>
                <?php $class_rank++; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Merit List Table -->
    <h3>📋 Merit List</h3>
    <div style="overflow-x: auto;">
        <table class="merit-table">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Stream</th>
                    <?php if ($subject_filter): ?>
                        <th>Subject</th>
                        <th>Marks (%)</th>
                        <th>Grade</th>
                    <?php else: ?>
                        <th>Subjects</th>
                        <th>Total Marks</th>
                        <th>Average (%)</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Grade Distribution</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                $prev_score = null;
                $display_rank = 1;
                $counter = 0;
                
                while($student = $merit_result->fetch_assoc()): 
                    $current_score = $subject_filter ? $student['marks'] : $student['average_marks'];
                    
                    if ($prev_score !== null && $current_score == $prev_score) {
                        $display_rank = $rank;
                    } else {
                        $display_rank = $counter + 1;
                        $rank = $counter + 1;
                    }
                    
                    $row_class = '';
                    if ($display_rank == 1) $row_class = 'rank-1';
                    elseif ($display_rank == 2) $row_class = 'rank-2';
                    elseif ($display_rank == 3) $row_class = 'rank-3';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="rank-cell">
                        <span class="rank-badge rank-badge-<?php echo $display_rank <= 3 ? $display_rank : 'other'; ?>">
                            <?php echo $display_rank; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                    <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                    <td><?php echo $student['class']; ?></td>
                    <td><?php echo $student['stream']; ?></td>
                    
                    <?php if ($subject_filter): ?>
                        <td><?php echo $student['subject_name']; ?></td>
                        <td><strong><?php echo $student['marks']; ?>%</strong></td>
                        <td class="grade-<?php echo $student['grade']; ?>"><?php echo $student['grade']; ?></td>
                    <?php else: ?>
                        <td><?php echo $student['subjects_taken']; ?></td>
                        <td><?php echo $student['total_marks']; ?></td>
                        <td><strong><?php echo $student['average_marks']; ?>%</strong></td>
                        <td><?php echo $student['highest_mark']; ?>%</td>
                        <td><?php echo $student['lowest_mark']; ?>%</td>
                        <td>
                            <span class="grade-EE">EE:<?php echo $student['ee_count']; ?></span> |
                            <span class="grade-ME">ME:<?php echo $student['me_count']; ?></span> |
                            <span class="grade-AE">AE:<?php echo $student['ae_count']; ?></span> |
                            <span class="grade-BE">BE:<?php echo $student['be_count']; ?></span>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php 
                    $prev_score = $current_score;
                    $counter++;
                endwhile; 
                ?>
            </tbody>
         </table>
    </div>
    
    <div class="actions">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Merit List</button>
        <button onclick="exportToExcel()" class="btn btn-excel">📊 Export to Excel</button>
    </div>
    
    <?php elseif ($term_id && $year_id): ?>
        <div class="no-data">
            <h3>📭 No data found</h3>
            <p>No students or marks match your selected filters.</p>
            <p><strong>Possible reasons:</strong></p>
            <ul style="text-align: left; display: inline-block; margin-top: 10px;">
                <li>No marks entered for the selected term/year/exam type</li>
                <li>No students found in the selected class</li>
                <li>The selected filters don't match any records</li>
            </ul>
            <br><br>
            <a href="enter-marks.php" class="btn">➕ Enter Marks</a>
            <a href="?class=&term_id=&year_id=" class="btn">🔄 Reset Filters</a>
        </div>
    <?php else: ?>
        <div class="no-data">
            <h3>📭 Select filters and click Generate Merit List</h3>
            <p>Please select a term, year, and optional class to view the merit list.</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="view-marks.php">📊 View Marks</a>
        <a href="ranking.php">🏆 Rankings</a>
        <a href="analytics.php">📈 Analytics</a>
    </div>
</div>

<script>
function exportToExcel() {
    var table = document.querySelector('.merit-table');
    if (table) {
        var html = table.outerHTML;
        var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
        var link = document.createElement('a');
        link.download = 'merit_list.xls';
        link.href = url;
        link.click();
    } else {
        alert('No data to export');
    }
}
</script>
</body>
</html>
