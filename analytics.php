<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

// Get filter parameters
$class = isset($_GET['class']) ? $_GET['class'] : '';
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : '';

// Get current year if not specified
if (!$year_id) {
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $year_id = $current_year ? $current_year['id'] : 0;
}

// Fetch all available data for filters
$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
$students = $conn->query("SELECT id, admission_number, first_name, last_name, class FROM students WHERE status='Active' ORDER BY first_name");
$subjects = $conn->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");

$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();

// 1. Progressive Performance Data (Term by Term)
$progressive_data = [];
if ($student_id) {
    $progressive_sql = "SELECT 
                           t.term_name,
                           t.term_number,
                           ROUND(AVG(m.marks), 2) as average_score,
                           COUNT(m.id) as subjects_count,
                           SUM(CASE WHEN m.grade = 'EE' THEN 1 ELSE 0 END) as ee_count,
                           SUM(CASE WHEN m.grade = 'ME' THEN 1 ELSE 0 END) as me_count,
                           SUM(CASE WHEN m.grade = 'AE' THEN 1 ELSE 0 END) as ae_count,
                           SUM(CASE WHEN m.grade = 'BE' THEN 1 ELSE 0 END) as be_count
                       FROM marks m
                       JOIN terms t ON m.term_id = t.id
                       WHERE m.student_id = $student_id
                         AND m.academic_year_id = $year_id
                         AND m.exam_type = 'End Term'
                       GROUP BY t.term_number, t.term_name
                       ORDER BY t.term_number";
    $progressive_result = $conn->query($progressive_sql);
    while($row = $progressive_result->fetch_assoc()) {
        $progressive_data[] = $row;
    }
}

// 2. Subject Performance Comparison (for a student or class)
$subject_performance = [];
if ($student_id) {
    $subject_sql = "SELECT 
                       sub.subject_name,
                       ROUND(AVG(m.marks), 2) as avg_marks,
                       MAX(m.marks) as max_marks,
                       MIN(m.marks) as min_marks,
                       m.grade
                   FROM marks m
                   JOIN subjects sub ON m.subject_id = sub.id
                   WHERE m.student_id = $student_id
                     AND m.academic_year_id = $year_id
                     AND m.exam_type = 'End Term'
                   GROUP BY sub.subject_name, m.grade
                   ORDER BY avg_marks DESC";
    $subject_result = $conn->query($subject_sql);
    while($row = $subject_result->fetch_assoc()) {
        $subject_performance[] = $row;
    }
} elseif ($class) {
    $subject_sql = "SELECT 
                       sub.subject_name,
                       ROUND(AVG(m.marks), 2) as avg_marks,
                       MAX(m.marks) as max_marks,
                       MIN(m.marks) as min_marks,
                       COUNT(DISTINCT m.student_id) as students_count
                   FROM marks m
                   JOIN subjects sub ON m.subject_id = sub.id
                   JOIN students s ON m.student_id = s.id
                   WHERE s.class = '$class'
                     AND m.academic_year_id = $year_id
                     AND m.exam_type = 'End Term'
                   GROUP BY sub.subject_name
                   ORDER BY avg_marks DESC";
    $subject_result = $conn->query($subject_sql);
    while($row = $subject_result->fetch_assoc()) {
        $subject_performance[] = $row;
    }
}

// 3. Grade Distribution Over Time
$grade_distribution = [];
$grade_sql = "SELECT 
                 t.term_name,
                 COUNT(CASE WHEN m.grade = 'EE' THEN 1 END) as EE,
                 COUNT(CASE WHEN m.grade = 'ME' THEN 1 END) as ME,
                 COUNT(CASE WHEN m.grade = 'AE' THEN 1 END) as AE,
                 COUNT(CASE WHEN m.grade = 'BE' THEN 1 END) as BE
              FROM marks m
              JOIN terms t ON m.term_id = t.id
              JOIN students s ON m.student_id = s.id
              WHERE " . ($class ? "s.class = '$class'" : "1=1") . "
                AND m.academic_year_id = $year_id
              GROUP BY t.term_name, t.term_number
              ORDER BY t.term_number";
$grade_result = $conn->query($grade_sql);
while($row = $grade_result->fetch_assoc()) {
    $grade_distribution[] = $row;
}

// 4. Top Performers
$top_performers = [];
$top_sql = "SELECT 
               s.first_name, s.last_name, s.class,
               ROUND(AVG(m.marks), 2) as avg_score,
               COUNT(m.id) as subjects_taken
            FROM students s
            JOIN marks m ON s.id = m.student_id
            WHERE " . ($class ? "s.class = '$class'" : "1=1") . "
              AND m.academic_year_id = $year_id
              AND m.exam_type = 'End Term'
            GROUP BY s.id
            ORDER BY avg_score DESC
            LIMIT 10";
$top_result = $conn->query($top_sql);
while($row = $top_result->fetch_assoc()) {
    $top_performers[] = $row;
}

// 5. Class Comparison
$class_comparison = [];
if (!$class) {
    $class_sql = "SELECT 
                     s.class,
                     COUNT(DISTINCT s.id) as student_count,
                     ROUND(AVG(m.marks), 2) as class_average,
                     MAX(m.marks) as highest_score,
                     MIN(m.marks) as lowest_score,
                     ROUND((SUM(CASE WHEN m.marks >= 50 THEN 1 ELSE 0 END) / COUNT(m.id)) * 100, 2) as pass_rate
                  FROM students s
                  JOIN marks m ON s.id = m.student_id
                  WHERE m.academic_year_id = $year_id
                    AND m.exam_type = 'End Term'
                  GROUP BY s.class
                  ORDER BY class_average DESC";
    $class_result = $conn->query($class_sql);
    while($row = $class_result->fetch_assoc()) {
        $class_comparison[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics & Charts - Kitere CBC</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        /* Filter Section */
        .filter-section { background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; font-size: 12px; }
        .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 20px; background: #1abc9c; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #16a085; }
        
        /* Chart Grid */
        .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .chart-card h3 { color: #002147; margin-bottom: 20px; border-bottom: 2px solid #1abc9c; padding-bottom: 10px; }
        .chart-container { position: relative; height: 300px; margin-bottom: 20px; }
        canvas { max-height: 300px; width: 100%; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: bold; margin: 10px 0; }
        .stat-card .label { font-size: 14px; opacity: 0.9; }
        
        /* Top Performers Table */
        .performers-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .performers-table th, .performers-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .performers-table th { background: #f8f9fa; color: #002147; }
        .rank-badge { display: inline-block; width: 30px; height: 30px; line-height: 30px; text-align: center; border-radius: 50%; font-weight: bold; }
        .rank-1 { background: #ffd700; color: #333; }
        .rank-2 { background: #c0c0c0; color: #333; }
        .rank-3 { background: #cd7f32; color: white; }
        
        /* Insights Section */
        .insights { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-top: 20px; }
        .insights h4 { margin-bottom: 10px; }
        .insights ul { margin-left: 20px; }
        .insights li { margin: 5px 0; }
        
        @media (max-width: 768px) {
            .chart-grid { grid-template-columns: 1fr; }
            .chart-container { height: 250px; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 10px; }
    </style>
</head>
<body>
<header>
    <h2>📚 Kitere CBC Exam System</h2>
    <p>Advanced Analytics & Performance Charts</p>
</header>

<div class="container">
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Select Class</label>
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
                <label>Select Student (Optional)</label>
                <select name="student_id">
                    <option value="">-- Select Student for Individual Analytics --</option>
                    <?php 
                    $students->data_seek(0);
                    while($s = $students->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $student_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo $s['admission_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['class'] . ')'; ?>
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
                <button type="submit" class="btn">📊 Generate Analytics</button>
            </div>
        </form>
    </div>

    <?php if ($student_id): 
        // Get student info
        $student_info = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
    ?>
        <!-- Student Header -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Student Name</div>
                <div class="number" style="font-size: 20px;"><?php echo $student_info['first_name'] . ' ' . $student_info['last_name']; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Admission Number</div>
                <div class="number" style="font-size: 20px;"><?php echo $student_info['admission_number']; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Class</div>
                <div class="number" style="font-size: 20px;"><?php echo $student_info['class'] . ' ' . $student_info['stream']; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="chart-grid">
        <!-- Chart 1: Progressive Performance (Term by Term) -->
        <?php if ($student_id && !empty($progressive_data)): ?>
        <div class="chart-card">
            <h3>📈 Progressive Performance Trend</h3>
            <div class="chart-container">
                <canvas id="progressiveChart"></canvas>
            </div>
            <div class="insights">
                <h4>📊 Performance Insights:</h4>
                <ul>
                    <?php 
                    $trend = "stable";
                    if (count($progressive_data) >= 2) {
                        $first = $progressive_data[0]['average_score'];
                        $last = $progressive_data[count($progressive_data)-1]['average_score'];
                        if ($last > $first) $trend = "improving 📈";
                        elseif ($last < $first) $trend = "declining 📉";
                        else $trend = "stable 📊";
                    }
                    ?>
                    <li>Performance trend: <strong><?php echo $trend; ?></strong></li>
                    <li>Best performance: <strong><?php echo max(array_column($progressive_data, 'average_score')); ?>%</strong></li>
                    <li>Current average: <strong><?php echo end($progressive_data)['average_score']; ?>%</strong></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart 2: Subject Performance Comparison -->
        <?php if (!empty($subject_performance)): ?>
        <div class="chart-card">
            <h3>📊 Subject Performance Comparison</h3>
            <div class="chart-container">
                <canvas id="subjectChart"></canvas>
            </div>
            <div class="insights">
                <h4>🎯 Subject Insights:</h4>
                <ul>
                    <li>Strongest subject: <strong><?php echo $subject_performance[0]['subject_name']; ?></strong> (<?php echo $subject_performance[0]['avg_marks']; ?>%)</li>
                    <li>Weakest subject: <strong><?php echo end($subject_performance)['subject_name']; ?></strong> (<?php echo end($subject_performance)['avg_marks']; ?>%)</li>
                    <?php 
                    $avg_all = array_sum(array_column($subject_performance, 'avg_marks')) / count($subject_performance);
                    ?>
                    <li>Overall average: <strong><?php echo round($avg_all, 2); ?>%</strong></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart 3: Grade Distribution Over Time -->
        <?php if (!empty($grade_distribution)): ?>
        <div class="chart-card">
            <h3>📊 Grade Distribution Over Time</h3>
            <div class="chart-container">
                <canvas id="gradeDistributionChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart 4: Class Comparison -->
        <?php if (!empty($class_comparison)): ?>
        <div class="chart-card">
            <h3>🏫 Class Performance Comparison</h3>
            <div class="chart-container">
                <canvas id="classComparisonChart"></canvas>
            </div>
            <div class="insights">
                <h4>🏆 Class Insights:</h4>
                <ul>
                    <li>Best performing class: <strong><?php echo $class_comparison[0]['class']; ?></strong> (<?php echo $class_comparison[0]['class_average']; ?>%)</li>
                    <li>Highest pass rate: <strong><?php echo $class_comparison[0]['pass_rate']; ?>%</strong></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart 5: Top 10 Performers -->
        <?php if (!empty($top_performers)): ?>
        <div class="chart-card">
            <h3>🏆 Top 10 Performers</h3>
            <div class="chart-container">
                <canvas id="topPerformersChart"></canvas>
            </div>
            <table class="performers-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Average Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_performers as $index => $performer): ?>
                    <tr>
                        <td>
                            <span class="rank-badge rank-<?php echo $index+1 <= 3 ? $index+1 : 'other'; ?>">
                                <?php echo $index+1; ?>
                            </span>
                        </td>
                        <td><?php echo $performer['first_name'] . ' ' . $performer['last_name']; ?></td>
                        <td><?php echo $performer['class']; ?></td>
                        <td><strong><?php echo $performer['avg_score']; ?>%</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <a href="dashboard.php">🏠 Dashboard</a> |
        <a href="ranking.php">🏆 Rankings</a> |
        <a href="merit-list.php">📋 Merit List</a> |
        <a href="reports.php">📈 Reports</a>
    </div>
</div>

<script>
<?php if ($student_id && !empty($progressive_data)): ?>
// Progressive Performance Chart
const progressiveCtx = document.getElementById('progressiveChart').getContext('2d');
new Chart(progressiveCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($progressive_data, 'term_name')); ?>,
        datasets: [{
            label: 'Average Score (%)',
            data: <?php echo json_encode(array_column($progressive_data, 'average_score')); ?>,
            borderColor: '#1abc9c',
            backgroundColor: 'rgba(26, 188, 156, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#1abc9c',
            pointBorderColor: '#fff',
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: { callbacks: { label: function(context) { return context.raw + '%'; } } }
        },
        scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Percentage (%)' } } }
    }
});
<?php endif; ?>

<?php if (!empty($subject_performance)): ?>
// Subject Performance Chart
const subjectCtx = document.getElementById('subjectChart').getContext('2d');
new Chart(subjectCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($subject_performance, 'subject_name')); ?>,
        datasets: [{
            label: 'Average Score (%)',
            data: <?php echo json_encode(array_column($subject_performance, 'avg_marks')); ?>,
            backgroundColor: 'rgba(26, 188, 156, 0.7)',
            borderColor: '#1abc9c',
            borderWidth: 1,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: { callbacks: { label: function(context) { return context.raw + '%'; } } }
        },
        scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Percentage (%)' } } }
    }
});
<?php endif; ?>

<?php if (!empty($grade_distribution)): ?>
// Grade Distribution Chart
const gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
new Chart(gradeCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($grade_distribution, 'term_name')); ?>,
        datasets: [
            { label: 'EE (Excellent)', data: <?php echo json_encode(array_column($grade_distribution, 'EE')); ?>, backgroundColor: '#27ae60' },
            { label: 'ME (Good)', data: <?php echo json_encode(array_column($grade_distribution, 'ME')); ?>, backgroundColor: '#f39c12' },
            { label: 'AE (Average)', data: <?php echo json_encode(array_column($grade_distribution, 'AE')); ?>, backgroundColor: '#e67e22' },
            { label: 'BE (Poor)', data: <?php echo json_encode(array_column($grade_distribution, 'BE')); ?>, backgroundColor: '#e74c3c' }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Students' } } }
    }
});
<?php endif; ?>

<?php if (!empty($class_comparison)): ?>
// Class Comparison Chart
const classCtx = document.getElementById('classComparisonChart').getContext('2d');
new Chart(classCtx, {
    type: 'radar',
    data: {
        labels: <?php echo json_encode(array_column($class_comparison, 'class')); ?>,
        datasets: [
            { label: 'Average Score (%)', data: <?php echo json_encode(array_column($class_comparison, 'class_average')); ?>, backgroundColor: 'rgba(26, 188, 156, 0.2)', borderColor: '#1abc9c', pointBackgroundColor: '#1abc9c' },
            { label: 'Pass Rate (%)', data: <?php echo json_encode(array_column($class_comparison, 'pass_rate')); ?>, backgroundColor: 'rgba(52, 152, 219, 0.2)', borderColor: '#3498db', pointBackgroundColor: '#3498db' }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } }
    }
});
<?php endif; ?>

<?php if (!empty($top_performers)): ?>
// Top Performers Chart
const topCtx = document.getElementById('topPerformersChart').getContext('2d');
new Chart(topCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($p) { return $p['first_name'] . ' ' . substr($p['last_name'], 0, 1); }, $top_performers)); ?>,
        datasets: [{
            label: 'Average Score (%)',
            data: <?php echo json_encode(array_column($top_performers, 'avg_score')); ?>,
            backgroundColor: ['#ffd700', '#c0c0c0', '#cd7f32', '#1abc9c', '#3498db', '#9b59b6', '#e67e22', '#2ecc71', '#e74c3c', '#95a5a6'],
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: function(context) { return context.raw + '%'; } } } },
        scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Percentage (%)' } } }
    }
});
<?php endif; ?>
</script>
</body>
</html>