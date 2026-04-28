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
$ranking_type = isset($_GET['ranking_type']) ? $_GET['ranking_type'] : 'overall';
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

// Get current term/year if not specified
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

// Debug: Check if we have marks
$check_marks = $conn->query("SELECT COUNT(*) as total FROM marks WHERE term_id = $term_id AND academic_year_id = $year_id")->fetch_assoc();
$has_marks = $check_marks['total'] > 0;

// Different ranking queries based on type
$result = null;
$total_items = 0;

if ($ranking_type == 'overall') {
    // Overall ranking across all subjects
    $sql = "SELECT 
                s.id, 
                s.admission_number, 
                s.first_name, 
                s.last_name, 
                s.class, 
                s.stream,
                COUNT(m.id) as subjects_taken,
                ROUND(COALESCE(AVG(m.marks), 0), 2) as average_marks,
                COALESCE(SUM(m.marks), 0) as total_marks,
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
    
    $result = $conn->query($sql);
    $total_items = $result ? $result->num_rows : 0;
    
} elseif ($ranking_type == 'subject' && $subject_id) {
    // Subject-specific ranking
    $subject_info = $conn->query("SELECT * FROM subjects WHERE id = $subject_id")->fetch_assoc();
    
    $sql = "SELECT 
                s.id, 
                s.admission_number, 
                s.first_name, 
                s.last_name, 
                s.class, 
                s.stream,
                COALESCE(m.marks, 0) as marks,
                COALESCE(m.grade, 'N/A') as grade,
                '{$subject_info['subject_name']}' as subject_name
            FROM students s
            LEFT JOIN marks m ON s.id = m.student_id 
                AND m.subject_id = $subject_id
                AND m.term_id = $term_id 
                AND m.academic_year_id = $year_id 
                AND m.exam_type = '$exam_type'
            WHERE s.status = 'Active' " . ($class ? "AND s.class = '$class'" : "") . "
            ORDER BY marks DESC";
    
    $result = $conn->query($sql);
    $total_items = $result ? $result->num_rows : 0;
    
} elseif ($ranking_type == 'class') {
    // Class vs Class ranking
    $sql = "SELECT 
                s.class,
                COUNT(DISTINCT s.id) as total_students,
                COUNT(m.id) as total_exams_taken,
                ROUND(COALESCE(AVG(m.marks), 0), 2) as average_marks,
                COALESCE(MAX(m.marks), 0) as highest_mark,
                COALESCE(MIN(m.marks), 0) as lowest_mark,
                SUM(CASE WHEN m.marks >= 75 THEN 1 ELSE 0 END) as excellent_count,
                SUM(CASE WHEN m.marks >= 50 AND m.marks < 75 THEN 1 ELSE 0 END) as good_count,
                SUM(CASE WHEN m.marks >= 25 AND m.marks < 50 THEN 1 ELSE 0 END) as average_count,
                SUM(CASE WHEN m.marks < 25 THEN 1 ELSE 0 END) as poor_count,
                ROUND((SUM(CASE WHEN m.marks >= 50 THEN 1 ELSE 0 END) / NULLIF(COUNT(m.id), 0)) * 100, 2) as pass_percentage
            FROM students s
            LEFT JOIN marks m ON s.id = m.student_id 
                AND m.term_id = $term_id 
                AND m.academic_year_id = $year_id 
                AND m.exam_type = '$exam_type'
            WHERE s.status = 'Active'
            GROUP BY s.class
            ORDER BY average_marks DESC";
    
    $result = $conn->query($sql);
    $total_items = $result ? $result->num_rows : 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Ranking - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #002147; margin-bottom: 10px; }
        h3 { color: #002147; margin: 20px 0 10px 0; }
        .subtitle { color: #666; margin-bottom: 20px; text-align: center; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #ddd; flex-wrap: wrap; }
        .tab { padding: 10px 20px; background: #f8f9fa; border: none; border-radius: 8px 8px 0 0; font-weight: bold; text-decoration: none; color: #333; }
        .tab:hover { background: #e9ecef; }
        .tab.active { background: #1abc9c; color: white; }
        
        .filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #555; }
        .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn { padding: 10px 25px; background: #1abc9c; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #16a085; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: bold; }
        .stat-card .label { font-size: 13px; margin-top: 5px; opacity: 0.9; }
        
        .podium { display: flex; justify-content: center; align-items: flex-end; gap: 20px; margin: 30px 0; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; flex-wrap: wrap; }
        .podium-item { text-align: center; background: white; border-radius: 12px; overflow: hidden; }
        .podium-1 { width: 220px; }
        .podium-2 { width: 190px; }
        .podium-3 { width: 190px; }
        .podium-rank { padding: 12px; font-weight: bold; font-size: 22px; color: white; }
        .podium-1 .podium-rank { background: #ffd700; color: #333; }
        .podium-2 .podium-rank { background: #c0c0c0; color: #333; }
        .podium-3 .podium-rank { background: #cd7f32; color: white; }
        .podium-info { padding: 15px; }
        .podium-name { font-weight: bold; font-size: 16px; }
        .podium-score { font-size: 22px; font-weight: bold; color: #1abc9c; margin-top: 5px; }
        .podium-class { font-size: 12px; color: #666; margin-top: 5px; }
        
        .ranking-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .ranking-table th, .ranking-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .ranking-table th { background: #002147; color: white; }
        .ranking-table tr:hover { background: #f5f5f5; }
        .rank-cell { text-align: center; font-weight: bold; width: 70px; }
        .rank-1 { background: #ffd700; }
        .rank-2 { background: #c0c0c0; }
        .rank-3 { background: #cd7f32; }
        .rank-badge { display: inline-block; width: 35px; height: 35px; line-height: 35px; text-align: center; border-radius: 50%; font-weight: bold; }
        .rank-badge-1 { background: #ffd700; color: #333; }
        .rank-badge-2 { background: #c0c0c0; color: #333; }
        .rank-badge-3 { background: #cd7f32; color: white; }
        
        .grade-EE { color: #27ae60; font-weight: bold; }
        .grade-ME { color: #f39c12; font-weight: bold; }
        .grade-AE { color: #e67e22; font-weight: bold; }
        .grade-BE { color: #e74c3c; font-weight: bold; }
        
        .no-data { text-align: center; padding: 50px; background: #f8f9fa; border-radius: 12px; }
        .no-data h3 { color: #666; margin-bottom: 15px; }
        .no-data p { color: #999; margin-bottom: 20px; }
        
        .alert { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .ranking-table { font-size: 12px; }
            .ranking-table th, .ranking-table td { padding: 8px; }
            .filter-group { min-width: 100%; }
            .podium { flex-direction: column; align-items: center; }
            .podium-1, .podium-2, .podium-3 { width: 100%; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
        
        .btn-print { background: #3498db; margin-top: 20px; }
        .btn-print:hover { background: #2980b9; }
    </style>
</head>
<body>
<header>
    <h2>📚 Kitere CBC Exam System</h2>
    <p>Student Ranking & Performance Analysis</p>
</header>

<div class="container">
    <h2>🏆 Student Ranking System</h2>
    <div class="subtitle">
        <?php if ($term_info && $year_info): ?>
            <?php echo $term_info['term_name'] . ' - ' . $year_info['year']; ?>
        <?php else: ?>
            Select filters and click Generate Ranking
        <?php endif; ?>
        <?php if ($class) echo " | Class: $class"; ?>
        <?php if ($ranking_type == 'subject' && $subject_id && isset($subject_info)) echo " | Subject: " . $subject_info['subject_name']; ?>
    </div>
    
    <!-- Tab Navigation -->
    <div class="tabs">
        <a href="?ranking_type=overall&class=<?php echo $class; ?>&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>&exam_type=<?php echo $exam_type; ?>" class="tab <?php echo $ranking_type == 'overall' ? 'active' : ''; ?>">
            📊 Overall Ranking
        </a>
        <a href="?ranking_type=subject&class=<?php echo $class; ?>&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>&exam_type=<?php echo $exam_type; ?>" class="tab <?php echo $ranking_type == 'subject' ? 'active' : ''; ?>">
            📖 Subject Ranking
        </a>
        <a href="?ranking_type=class&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>&exam_type=<?php echo $exam_type; ?>" class="tab <?php echo $ranking_type == 'class' ? 'active' : ''; ?>">
            🏫 Class Ranking
        </a>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <input type="hidden" name="ranking_type" value="<?php echo $ranking_type; ?>">
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
                <label>Year</label>
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
            <?php if ($ranking_type == 'subject'): ?>
            <div class="filter-group">
                <label>Subject</label>
                <select name="subject_id" required>
                    <option value="">-- Select Subject --</option>
                    <?php 
                    if ($subjects && $subjects->num_rows > 0) {
                        $subjects->data_seek(0);
                        while($sub = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo $subject_id == $sub['id'] ? 'selected' : ''; ?>>
                                <?php echo $sub['subject_name']; ?>
                            </option>
                        <?php endwhile; 
                    } ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-group">
                <button type="submit" class="btn">📊 Generate Ranking</button>
            </div>
        </form>
    </div>
    
    <!-- Debug/Info Message -->
    <?php if (!$has_marks && ($term_id || $year_id)): ?>
        <div class="alert">
            ⚠️ No marks found for the selected criteria. Please enter marks first or try different filters.
            <br><br>
            <a href="ENTER_MARKS.php" style="color: #856404; font-weight: bold;">➕ Click here to enter marks</a>
        </div>
    <?php endif; ?>
    
    <?php if ($result && $total_items > 0): ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_items; ?></div>
            <div class="label">Total <?php echo $ranking_type == 'class' ? 'Classes' : 'Students'; ?></div>
        </div>
        <?php if ($ranking_type != 'class'): ?>
        <?php 
        // Calculate average of averages
        $avg_total = 0;
        if ($ranking_type == 'overall') {
            $result->data_seek(0);
            while($row = $result->fetch_assoc()) {
                $avg_total += $row['average_marks'];
            }
            $overall_avg = $total_items > 0 ? round($avg_total / $total_items, 2) : 0;
            $result->data_seek(0);
        } elseif ($ranking_type == 'subject') {
            $result->data_seek(0);
            while($row = $result->fetch_assoc()) {
                $avg_total += $row['marks'];
            }
            $overall_avg = $total_items > 0 ? round($avg_total / $total_items, 2) : 0;
            $result->data_seek(0);
        }
        ?>
        <div class="stat-card">
            <div class="number"><?php echo isset($overall_avg) ? $overall_avg : 0; ?>%</div>
            <div class="label">Average Score</div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Top 3 Podium -->
    <?php if ($ranking_type != 'class' && $total_items >= 3): 
        $result->data_seek(0);
        $top1 = $result->fetch_assoc();
        $top2 = $result->fetch_assoc();
        $top3 = $result->fetch_assoc();
        $result->data_seek(0);
        
        $top1_score = $ranking_type == 'overall' ? $top1['average_marks'] : $top1['marks'];
        $top2_score = $ranking_type == 'overall' ? $top2['average_marks'] : $top2['marks'];
        $top3_score = $ranking_type == 'overall' ? $top3['average_marks'] : $top3['marks'];
    ?>
    <div class="podium">
        <div class="podium-item podium-2">
            <div class="podium-rank">🥈 2nd Place</div>
            <div class="podium-info">
                <div class="podium-name"><?php echo htmlspecialchars($top2['first_name'] . ' ' . $top2['last_name']); ?></div>
                <div class="podium-score"><?php echo $top2_score; ?>%</div>
                <div class="podium-class"><?php echo $top2['class']; ?></div>
            </div>
        </div>
        <div class="podium-item podium-1">
            <div class="podium-rank">🥇 1st Place</div>
            <div class="podium-info">
                <div class="podium-name"><?php echo htmlspecialchars($top1['first_name'] . ' ' . $top1['last_name']); ?></div>
                <div class="podium-score"><?php echo $top1_score; ?>%</div>
                <div class="podium-class"><?php echo $top1['class']; ?></div>
            </div>
        </div>
        <div class="podium-item podium-3">
            <div class="podium-rank">🥉 3rd Place</div>
            <div class="podium-info">
                <div class="podium-name"><?php echo htmlspecialchars($top3['first_name'] . ' ' . $top3['last_name']); ?></div>
                <div class="podium-score"><?php echo $top3_score; ?>%</div>
                <div class="podium-class"><?php echo $top3['class']; ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Ranking Table -->
    <h3>📋 Ranking Table</h3>
    <div style="overflow-x: auto;">
        <table class="ranking-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Admission</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <?php if ($ranking_type == 'overall'): ?>
                        <th>Subjects</th>
                        <th>Average (%)</th>
                        <th>Total Marks</th>
                        <th>Best</th>
                        <th>Worst</th>
                        <th>Grade Distribution</th>
                    <?php elseif ($ranking_type == 'subject'): ?>
                        <th>Marks (%)</th>
                        <th>Grade</th>
                    <?php elseif ($ranking_type == 'class'): ?>
                        <th>Students</th>
                        <th>Average (%)</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Pass %</th>
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
                
                while($row = $result->fetch_assoc()):
                    $current_score = $ranking_type == 'overall' ? $row['average_marks'] : ($ranking_type == 'subject' ? $row['marks'] : $row['average_marks']);
                    
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
                    <td><?php echo htmlspecialchars($row['admission_number']); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong></td>
                    <td><?php echo $row['class'] . ($row['stream'] ? ' ' . $row['stream'] : ''); ?></td>
                    
                    <?php if ($ranking_type == 'overall'): ?>
                        <td><?php echo $row['subjects_taken']; ?></td>
                        <td><strong><?php echo $row['average_marks']; ?>%</strong></td>
                        <td><?php echo $row['total_marks']; ?></td>
                        <td><?php echo $row['highest_mark']; ?>%</td>
                        <td><?php echo $row['lowest_mark']; ?>%</td>
                        <td>
                            <span class="grade-EE">EE:<?php echo $row['ee_count']; ?></span> |
                            <span class="grade-ME">ME:<?php echo $row['me_count']; ?></span> |
                            <span class="grade-AE">AE:<?php echo $row['ae_count']; ?></span> |
                            <span class="grade-BE">BE:<?php echo $row['be_count']; ?></span>
                        </td>
                        
                    <?php elseif ($ranking_type == 'subject'): ?>
                        <td><strong><?php echo $row['marks']; ?>%</strong></td>
                        <td class="grade-<?php echo $row['grade']; ?>"><?php echo $row['grade']; ?></td>
                        
                    <?php elseif ($ranking_type == 'class'): ?>
                        <td><?php echo $row['total_students']; ?></td>
                        <td><strong><?php echo $row['average_marks']; ?>%</strong></td>
                        <td><?php echo $row['highest_mark']; ?>%</td>
                        <td><?php echo $row['lowest_mark']; ?>%</td>
                        <td><?php echo $row['pass_percentage']; ?>%</td>
                        <td>
                            <span class="grade-EE">E:<?php echo $row['excellent_count']; ?></span> |
                            <span class="grade-ME">G:<?php echo $row['good_count']; ?></span> |
                            <span class="grade-AE">A:<?php echo $row['average_count']; ?></span> |
                            <span class="grade-BE">P:<?php echo $row['poor_count']; ?></span>
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
    
    <div style="text-align: center;">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Ranking</button>
    </div>
    
    <?php elseif ($term_id && $year_id): ?>
        <div class="no-data">
            <h3>📭 No ranking data found</h3>
            <p>No students or marks match your selected filters.</p>
            <p><strong>Possible reasons:</strong></p>
            <ul style="text-align: left; display: inline-block; margin-top: 10px;">
                <li>No marks entered for the selected term/year/exam type</li>
                <li>No students found in the selected class</li>
                <li>The selected filters don't match any records</li>
            </ul>
            <br><br>
            <a href="ENTER_MARKS.php" class="btn">➕ Enter Marks</a>
            <a href="?ranking_type=overall" class="btn">🔄 Reset Filters</a>
        </div>
    <?php else: ?>
        <div class="no-data">
            <h3>📭 Select filters and click Generate Ranking</h3>
            <p>Please select a term, year, and optional class to view rankings.</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <a href="DASHBOARD.php">🏠 Dashboard</a>
        <a href="VIEW_MARKS.php">📊 View Marks</a>
        <a href="merit_list.php">🏆 Merit List</a>
        <a href="analytics.php">📈 Analytics</a>
    </div>
</div>
</body>
</html>