<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
include "db.php";

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get current term and year
$current_term = $conn->query("SELECT id FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();

$term_id = isset($_GET['term_id']) ? $_GET['term_id'] : ($current_term ? $current_term['id'] : 0);
$year_id = isset($_GET['year_id']) ? $_GET['year_id'] : ($current_year ? $current_year['id'] : 0);
$class = isset($_GET['class']) ? $_GET['class'] : '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_timetable'])) {
    $class = $_POST['class'];
    $term_id = $_POST['term_id'];
    $year_id = $_POST['year_id'];
    
    // Delete existing timetable for this class/term/year
    $conn->query("DELETE FROM timetable WHERE class = '$class' AND term_id = $term_id AND academic_year_id = $year_id");
    
    // Insert new timetable entries
    foreach ($_POST['timetable'] as $day_id => $slots) {
        foreach ($slots as $time_slot_id => $data) {
            $subject_id = $data['subject'];
            $teacher_id = $data['teacher'];
            $room = $data['room'];
            
            if ($subject_id && $teacher_id) {
                $stmt = $conn->prepare("INSERT INTO timetable (class, day_id, time_slot_id, subject_id, teacher_id, room, term_id, academic_year_id, created_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiissiii", $class, $day_id, $time_slot_id, $subject_id, $teacher_id, $room, $term_id, $year_id, $user_id);
                $stmt->execute();
            }
        }
    }
    
    $_SESSION['message'] = "Timetable saved successfully!";
    $_SESSION['message_type'] = "success";
    header("Location: manage_timetable.php?class=$class&term_id=$term_id&year_id=$year_id");
    exit();
}

// Get existing timetable for display
$existing_timetable = [];
if ($class && $term_id && $year_id) {
    $timetable_sql = "SELECT t.*, d.day_name, ts.slot_name, ts.start_time, ts.end_time, 
                             sub.subject_name, u.full_name as teacher_name
                      FROM timetable t
                      JOIN days d ON t.day_id = d.id
                      JOIN time_slots ts ON t.time_slot_id = ts.id
                      JOIN subjects sub ON t.subject_id = sub.id
                      JOIN users u ON t.teacher_id = u.id
                      WHERE t.class = '$class' AND t.term_id = $term_id AND t.academic_year_id = $year_id
                      ORDER BY d.day_order, ts.slot_order";
    $timetable_result = $conn->query($timetable_sql);
    while($row = $timetable_result->fetch_assoc()) {
        $existing_timetable[$row['day_id']][$row['time_slot_id']] = $row;
    }
}

// Get all data for dropdowns
$classes = $conn->query("SELECT DISTINCT class FROM students WHERE status='Active' ORDER BY class");
$terms = $conn->query("SELECT * FROM terms ORDER BY term_number");
$years = $conn->query("SELECT * FROM academic_years ORDER BY year DESC");
$days = $conn->query("SELECT * FROM days WHERE is_active = 1 ORDER BY day_order");
$time_slots = $conn->query("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY slot_order");
$subjects = $conn->query("SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name");
$teachers = $conn->query("SELECT id, full_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY full_name");

$term_info = $conn->query("SELECT * FROM terms WHERE id = $term_id")->fetch_assoc();
$year_info = $conn->query("SELECT * FROM academic_years WHERE id = $year_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Timetable - Kitere CBC</title>
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
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        /* Timetable Table */
        .timetable-container { overflow-x: auto; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .timetable-table { width: 100%; border-collapse: collapse; }
        .timetable-table th, .timetable-table td { border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: top; }
        .timetable-table th { background: #002147; color: white; font-weight: bold; }
        .timetable-table td { background: white; }
        .time-slot { font-weight: bold; background: #f8f9fa; width: 100px; }
        .subject-cell { min-width: 150px; }
        select, input { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
        .break-cell { background: #fff3cd; color: #856404; font-weight: bold; }
        
        /* Message */
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Actions */
        .actions { margin-top: 20px; text-align: center; }
        
        @media (max-width: 768px) {
            .timetable-table { font-size: 12px; }
            .timetable-table th, .timetable-table td { padding: 5px; }
            select, input { font-size: 10px; }
        }
        
        .footer { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
        
        .teacher-name { font-size: 11px; color: #666; margin-top: 3px; }
    </style>
</head>
<body>
<header>
    <h2>📅 Kitere CBC Exam System</h2>
    <p>Class Timetable Management</p>
</header>

<div class="container">
    <?php if(isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
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
                <button type="submit" class="btn btn-primary">Load Timetable</button>
            </div>
        </form>
    </div>
    
    <?php if ($class): ?>
        <form method="POST">
            <input type="hidden" name="class" value="<?php echo $class; ?>">
            <input type="hidden" name="term_id" value="<?php echo $term_id; ?>">
            <input type="hidden" name="year_id" value="<?php echo $year_id; ?>">
            
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
                                $existing = isset($existing_timetable[$day_id][$time_slot_id]) ? $existing_timetable[$day_id][$time_slot_id] : null;
                            ?>
                            <td class="<?php echo $is_break ? 'break-cell' : 'subject-cell'; ?>">
                                <?php if ($is_break): ?>
                                    <strong><?php echo $slot['slot_name']; ?></strong>
                                <?php else: ?>
                                    <select name="timetable[<?php echo $day_id; ?>][<?php echo $time_slot_id; ?>][subject]" style="margin-bottom: 5px;">
                                        <option value="">-- Select Subject --</option>
                                        <?php 
                                        $subjects->data_seek(0);
                                        while($sub = $subjects->fetch_assoc()): ?>
                                            <option value="<?php echo $sub['id']; ?>" <?php echo ($existing && $existing['subject_id'] == $sub['id']) ? 'selected' : ''; ?>>
                                                <?php echo $sub['subject_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <select name="timetable[<?php echo $day_id; ?>][<?php echo $time_slot_id; ?>][teacher]">
                                        <option value="">-- Select Teacher --</option>
                                        <?php 
                                        $teachers->data_seek(0);
                                        while($teacher = $teachers->fetch_assoc()): ?>
                                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($existing && $existing['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                                <?php echo $teacher['full_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <input type="text" name="timetable[<?php echo $day_id; ?>][<?php echo $time_slot_id; ?>][room]" placeholder="Room" value="<?php echo $existing ? $existing['room'] : ''; ?>">
                                    <?php if ($existing): ?>
                                        <div class="teacher-name"><?php echo $existing['teacher_name']; ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php endwhile; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="actions">
                <button type="submit" name="save-timetable" class="btn btn-success">💾 Save Timetable</button>
                <a href="view-timetable.php?class=<?php echo $class; ?>&term_id=<?php echo $term_id; ?>&year_id=<?php echo $year_id; ?>" class="btn btn-primary">👁️ View Timetable</a>
            </div>
        </form>
    <?php else: ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
            <h3>📅 Select a class to manage timetable</h3>
            <p>Choose a class from the filter above to create or edit the timetable.</p>
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