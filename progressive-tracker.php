<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$students = $conn->query("SELECT id, admission_number, first_name, last_name, class FROM students WHERE status='Active' ORDER BY first_name");

// Get all terms for the current year
$current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$year_id = $current_year ? $current_year['id'] : 0;

$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");

// Get subject performance over terms for selected student
$subject_trends = [];
if ($student_id) {
    $subject_sql = "SELECT 
                       sub.subject_name,
                       t.term_name,
                       t.term_number,
                       m.marks
                   FROM marks m
                   JOIN subjects sub ON m.subject_id = sub.id
                   JOIN terms t ON m.term_id = t.id
                   WHERE m.student_id = $student_id
                     AND m.academic_year_id = $year_id
                     AND m.exam_type = 'End Term'
                   ORDER BY sub.subject_name, t.term_number";
    $subject_result = $conn->query($subject_sql);
    $temp = [];
    while($row = $subject_result->fetch_assoc()) {
        $temp[$row['subject_name']][$row['term_name']] = $row['marks'];
    }
    $subject_trends = $temp;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Progress Tracker - Kitere CBC</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; }
        .filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .filter-form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { flex: 1; }
        .filter-group label { font-weight: bold; font-size: 12px; }
        .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 20px; background: #1abc9c; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .chart-card { margin-bottom: 30px; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .chart-card h3 { color: #002147; margin-bottom: 20px; }
        .chart-container { height: 400px; }
        @media (max-width: 768px) { .chart-container { height: 300px; } }
    </style>
</head>
<body>
<header><h2>📈 Student Progress Tracker</h2></header>
<div class="container">
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Select Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php while($s = $students->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $student_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo $s['admission_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn">Track Progress</button>
            </div>
        </form>
    </div>

    <?php if ($student_id && !empty($subject_trends)): 
        $terms_list = [];
        $terms->data_seek(0);
        while($t = $terms->fetch_assoc()) $terms_list[] = $t['term_name'];
    ?>
        <div class="chart-card">
            <h3>📊 Subject Performance Trends Over Terms</h3>
            <div class="chart-container">
                <canvas id="progressChart"></canvas>
            </div>
        </div>
    <?php elseif($student_id): ?>
        <p style="text-align: center; color: #666;">No marks data available for this student.</p>
    <?php endif; ?>
</div>

<script>
<?php if ($student_id && !empty($subject_trends)): ?>
const ctx = document.getElementById('progressChart').getContext('2d');
const datasets = [];
const colors = ['#1abc9c', '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#2ecc71', '#f39c12', '#1e8449', '#c0392b', '#2980b9'];

let colorIndex = 0;
<?php foreach($subject_trends as $subject => $terms_data): ?>
datasets.push({
    label: '<?php echo addslashes($subject); ?>',
    data: <?php echo json_encode(array_values($terms_data)); ?>,
    borderColor: colors[colorIndex % colors.length],
    backgroundColor: 'rgba(26, 188, 156, 0.1)',
    tension: 0.4,
    fill: false,
    pointBackgroundColor: colors[colorIndex % colors.length],
    pointRadius: 5,
    pointHoverRadius: 7
});
<?php $colorIndex++; endforeach; ?>

new Chart(ctx, {
    type: 'line',
    data: { labels: <?php echo json_encode($terms_list); ?>, datasets: datasets },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + context.raw + '%'; } } } },
        scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Marks (%)' } } }
    }
});
<?php endif; ?>
</script>
</body>
</html>