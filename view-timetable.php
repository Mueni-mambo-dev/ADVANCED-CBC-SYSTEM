<?php
session_start();
include "db.php";

// Get parameters
$class = isset($_GET['class']) ? $_GET['class'] : '';
$term_id = isset($_GET['term_id']) ? $_GET['term_id'] : '';
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : '';
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'class'; // class or teacher

// Get current term/year if not specified
if (!$term_id) {
    $current_term = $conn->query("SELECT id FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $term_id = $current_term ? $current_term['id'] : 0;
}
if (!$year_id) {
    $current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
    $year_id = $current_year ? $current_year['id'] : 0;
}

// Get all data
$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
$teachers = $conn->query("SELECT id, full_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY full_name");

$days = $conn->query("SELECT * FROM days WHERE is_active = 1 ORDER BY day_order");
$time_slots = $conn->query("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY slot_order");

$term_info = $conn->query("SELECT * FROM terms WHERE id = $term_id")->fetch_assoc();
$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();

// Get timetable data
$timetable = [];
if ($view == 'class' && $class) {
    $sql = "SELECT t.*, d.day_name, ts.slot_name, ts.start_time, ts.end_time, ts.slot_order,
                   sub.subject_name, u.full_name as teacher_name
            FROM timetable t
            JOIN days d ON t.day_id = d.id
            JOIN time_slots ts ON t.time_slot_id = ts.id
            JOIN subjects sub ON t.subject_id = sub.id
            JOIN users u ON t.teacher_id = u.id
            WHERE t.class = '$class' AND t.term_id = $term_id AND t.academic_year_id = $year_id
            ORDER BY d.day_order, ts.slot_order";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $timetable[$row['day_id']][$row['time_slot_id']] = $row;
    }
} elseif ($view == 'teacher' && $teacher_id) {
    $sql = "SELECT t.*, d.day_name, ts.slot_name, ts.start_time, ts.end_time, ts.slot_order,
                   sub.subject_name, s.class as class_name
            FROM timetable t
            JOIN days d ON t.day_id = d.id
            JOIN time_slots ts ON t.time_slot_id = ts.id
            JOIN subjects sub ON t.subject_id = sub.id
            JOIN students s ON s.class = t.class
            WHERE t.teacher_id = $teacher_id AND t.term_id = $term_id AND t.academic_year_id = $year_id
            GROUP BY t.class, t.day_id, t.time_slot_id
            ORDER BY d.day_order, ts.slot_order";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $timetable[$row['day_id']][$row['time_slot_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Timetable - Kitere CBC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        header { background: #002147; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        /* Filter Section */
        .filter-section { background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #555; }
        .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 20px; background: #1abc9c; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #1abc9c; }
        .btn-primary:hover { background: #16a085; }
        
        /* View Tabs */
        .view-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #f8f9fa; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-decoration: none; color: #333; }
        .tab.active { background: #1abc9c; color: white; }
        
        /* Timetable Table */
        .timetable-container { overflow-x: auto; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .timetable-table { width: 100%; border-collapse: collapse; }
        .timetable-table th, .timetable-table td { border: 1px solid #ddd; padding: 12px; text-align: center; vertical-align: top; }
        .timetable-table th { background: #002147; color: white; font-weight: bold; }
        .timetable-table td { background: white; }
        .time-slot { font-weight: bold; background: #f8f9fa; width: 100px; }
        .break-cell { background: #fff3cd; color: #856404; font-weight: bold; }
        .subject-info { font-weight: bold; color: #002147; }
        .teacher-info { font-size: 12px; color: #666; margin-top: 5px; }
        .room-info { font-size: 11px; color: #1abc9c; margin-top: 3px; }
        
        /* Print Styles */
        @media print {
            .filter-section, .view-tabs, .btn, .actions, .footer { display: none; }
            body { background: white; }
            .container { margin: 0; padding: 0; }
            .timetable-table th { background: #002147; color: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        .actions { margin-top: 20px; text-align: center; }
        .footer { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
        
        @media (max-width: 768px) {
            .timetable-table { font-size: 12px; }
            .timetable-table th, .timetable-table td { padding: 6px; }
        }
        
        .current-indicator { background: #e8f4f8; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
<header>
    <h2>📅 Kitere CBC Exam System</h2>
    <p>Class Timetable Viewer</p>
</header>

<div class="container">
    <!-- View Tabs -->
    <div class="view-tabs">
        <a href="?view=class&class=<?php echo $class; ?>&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>" class="tab <?php echo $view == 'class' ? 'active' : ''; ?>">📚 Class Timetable</a>
        <a href="?view=teacher&teacher_id=<?php echo $teacher_id; ?>&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>" class="tab <?php echo $view == 'teacher' ? 'active' : ''; ?>">👨‍🏫 Teacher Timetable</a>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <input type="hidden" name="view" value="<?php echo $view; ?>">
            
            <?php if ($view == 'class'): ?>
            <div class="filter-group">
                <label>Select Class</label>
                <select name="class" required>
                    <option value="">-- Select Class --</option>
                    <?php while($c = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $c['class']; ?>" <?php echo $class == $c['class'] ? 'selected' : ''; ?>>
                            <?php echo $c['class']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="filter-group">
                <label>Select Teacher</label>
                <select name="teacher_id" required>
                    <option value="">-- Select Teacher --</option>
                    <?php 
                    $teachers->data_seek(0);
                    while($t = $teachers->fetch_assoc()): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $teacher_id == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo $t['full_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            
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
                <button type="submit" class="btn btn-primary">View Timetable</button>
            </div>
        </form>
    </div>
    
    <?php if (($view == 'class' && $class) || ($view == 'teacher' && $teacher_id)): ?>
        <div class="current-indicator">
            <strong>
                <?php if ($view == 'class'): ?>
                    📚 Timetable for Class: <?php echo $class; ?>
                <?php else: ?>
                    👨‍🏫 Timetable for Teacher: <?php echo $conn->query("SELECT full_name FROM users WHERE id = $teacher_id")->fetch_assoc()['full_name']; ?>
                <?php endif; ?>
            </strong>
            | Term: <?php echo $term_info['term_name']; ?> | Year: <?php echo $year_info['year']; ?>
        </div>
        
        <div class="timetable-container">
            <table class="timetable-table">
                <thead>
                    <tr>
                        <th>Time / Day</th>
                        <?php 
                        $days->data_seek(0);
                        while($day = $days->fetch_assoc()): ?>
                            <th><?php echo $day['day_name']; ?></th>
                        <?php endwhile; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $time_slots->data_seek(0);
                    while($slot = $time_slots->fetch_assoc()): 
                        $is_break = stripos($slot['slot_name'], 'break') !== false || stripos($slot['slot_name'], 'lunch') !== false;
                    ?>
                    <tr>
                        <td class="time-slot">
                            <?php echo $slot['slot_name']; ?><br>
                            <small><?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($slot['end_time'])); ?></small>
                        </td>
                        <?php 
                        $days->data_seek(0);
                        while($day = $days->fetch_assoc()): 
                            $day_id = $day['id'];
                            $time_slot_id = $slot['id'];
                            $entry = isset($timetable[$day_id][$time_slot_id]) ? $timetable[$day_id][$time_slot_id] : null;
                        ?>
                        <td class="<?php echo $is_break ? 'break-cell' : ''; ?>">
                            <?php if ($is_break): ?>
                                <strong><?php echo $slot['slot_name']; ?></strong>
                            <?php elseif ($entry): ?>
                                <div class="subject-info"><?php echo $entry['subject_name']; ?></div>
                                <div class="teacher-info">👨‍🏫 <?php echo $entry['teacher_name']; ?></div>
                                <?php if ($view == 'teacher'): ?>
                                    <div class="room-info">🏫 Class: <?php echo $entry['class_name']; ?></div>
                                <?php else: ?>
                                    <div class="room-info">🏠 Room: <?php echo $entry['room'] ?: 'Not specified'; ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999;">- Free -</span>
                            <?php endif; ?>
                        </td>
                        <?php endwhile; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="actions">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Timetable</button>
            <?php if ($view == 'class'): ?>
                <a href="manage-timetable.php?class=<?php echo $class; ?>&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>" class="btn btn-primary">✏️ Edit Timetable</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
            <h3>📅 Select a class or teacher to view timetable</h3>
            <p>Choose from the filters above to display the timetable.</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="view-timetable.php">📅 View Timetable</a>
        <a href="manage-timetable.php">✏️ Manage Timetable</a>
        <a href="profile.php">👤 Profile</a>
    </div>
</div>
</body>
</html>