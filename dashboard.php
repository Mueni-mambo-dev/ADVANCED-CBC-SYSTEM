<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: LOGIN.html");
    exit();
}

include "db.php";

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$email = $_SESSION['email'];

// Get current term and year
$current_term = $conn->query("SELECT * FROM terms WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_year = $conn->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();

// Get student_id if role is student
$student_id = null;
if ($role == 'student') {
    $student_sql = "SELECT id FROM students WHERE email = '$email' OR CONCAT(first_name, ' ', last_name) = '$full_name' LIMIT 1";
    $student_result = $conn->query($student_sql);
    if ($student_result && $student_result->num_rows > 0) {
        $student = $student_result->fetch_assoc();
        $student_id = $student['id'];
    }
}

// Fetch statistics based on role
if ($role == 'teacher') {
    // Teacher statistics
    $stats_sql = "SELECT 
                    COUNT(DISTINCT m.student_id) as total_students,
                    COUNT(m.id) as total_marks_entered,
                    ROUND(AVG(m.marks), 2) as avg_marks,
                    (SELECT COUNT(DISTINCT cs.class) FROM class_subjects cs WHERE cs.teacher_id = $user_id) as classes_teaching,
                    (SELECT COUNT(DISTINCT subject_id) FROM marks WHERE recorded_by = $user_id) as subjects_taught,
                    (SELECT COUNT(*) FROM marks WHERE recorded_by = $user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_entries,
                    (SELECT COUNT(*) FROM timetable WHERE teacher_id = $user_id AND term_id = {$current_term['id']}) as total_classes
                  FROM marks m
                  WHERE m.recorded_by = $user_id";
    $stats_result = $conn->query($stats_sql);
    $stats = $stats_result->fetch_assoc();
    
    // Get pending tasks
    $pending_sql = "SELECT COUNT(DISTINCT s.id) as pending_students
                    FROM students s
                    WHERE s.class IN (SELECT DISTINCT class FROM class_subjects WHERE teacher_id = $user_id)
                    AND s.id NOT IN (
                        SELECT DISTINCT student_id FROM marks 
                        WHERE term_id = {$current_term['id']} 
                        AND academic_year_id = {$current_year['id']}
                        AND recorded_by = $user_id
                    )";
    $pending_result = $conn->query($pending_sql);
    $pending = $pending_result->fetch_assoc();
    
    // Get recent marks
    $recent_sql = "SELECT m.*, s.first_name, s.last_name, s.admission_number, sub.subject_name 
                   FROM marks m
                   JOIN students s ON m.student_id = s.id
                   JOIN subjects sub ON m.subject_id = sub.id
                   WHERE m.recorded_by = $user_id
                   ORDER BY m.created_at DESC LIMIT 10";
    $recent_result = $conn->query($recent_sql);
    
    // Get today's timetable
    $today = date('l');
    $today_timetable = $conn->query("SELECT t.*, sub.subject_name, s.class, ts.start_time, ts.end_time
                                      FROM timetable t
                                      JOIN days d ON t.day_id = d.id
                                      JOIN subjects sub ON t.subject_id = sub.id
                                      JOIN time_slots ts ON t.time_slot_id = ts.id
                                      JOIN students s ON s.class = t.class
                                      WHERE d.day_name = '$today' 
                                      AND t.teacher_id = $user_id
                                      AND t.term_id = {$current_term['id']}
                                      ORDER BY ts.slot_order LIMIT 5");
    
} else {
    // Student statistics
    if ($student_id) {
        $stats_sql = "SELECT 
                        COUNT(DISTINCT m.subject_id) as total_subjects,
                        ROUND(AVG(m.marks), 2) as avg_marks,
                        MAX(m.marks) as best_mark,
                        MIN(m.marks) as worst_mark,
                        (SELECT COUNT(*) FROM attendance WHERE student_id = $student_id AND status = 'Absent' AND date >= '{$current_year['start_date']}') as total_absences,
                        (SELECT COUNT(*) FROM attendance WHERE student_id = $student_id AND status = 'Present' AND date >= '{$current_year['start_date']}') as total_presents,
                        (SELECT COUNT(*) FROM marks WHERE student_id = $student_id AND marks >= 75) as excellent_count,
                        (SELECT COUNT(*) FROM marks WHERE student_id = $student_id AND marks < 50) as improvement_needed
                      FROM marks m
                      WHERE m.student_id = $student_id AND m.academic_year_id = {$current_year['id']}";
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result->fetch_assoc();
        
        // Get class position
        $position_sql = "SELECT COUNT(DISTINCT s2.id) + 1 as position
                         FROM students s1
                         JOIN marks m1 ON s1.id = m1.student_id
                         JOIN students s2 ON s2.class = s1.class
                         JOIN marks m2 ON s2.id = m2.student_id
                         WHERE s1.id = $student_id
                         AND s2.class = s1.class
                         AND m2.term_id = {$current_term['id']}
                         AND m2.academic_year_id = {$current_year['id']}
                         GROUP BY s2.id
                         HAVING AVG(m2.marks) > (SELECT AVG(marks) FROM marks WHERE student_id = $student_id AND term_id = {$current_term['id']})";
        $position_result = $conn->query($position_sql);
        $position = $position_result->num_rows + 1;
        
        // Get recent results
        $recent_sql = "SELECT m.*, sub.subject_name, t.term_name, ay.year 
                       FROM marks m
                       JOIN subjects sub ON m.subject_id = sub.id
                       JOIN terms t ON m.term_id = t.id
                       JOIN academic_years ay ON m.academic_year_id = ay.id
                       WHERE m.student_id = $student_id
                       ORDER BY m.created_at DESC LIMIT 10";
        $recent_result = $conn->query($recent_sql);
        
        // Get today's timetable for student's class
        $student_class = $conn->query("SELECT class FROM students WHERE id = $student_id")->fetch_assoc();
        $today = date('l');
        $today_timetable = $conn->query("SELECT t.*, sub.subject_name, ts.start_time, ts.end_time, u.full_name as teacher_name
                                          FROM timetable t
                                          JOIN days d ON t.day_id = d.id
                                          JOIN subjects sub ON t.subject_id = sub.id
                                          JOIN time_slots ts ON t.time_slot_id = ts.id
                                          JOIN users u ON t.teacher_id = u.id
                                          WHERE d.day_name = '$today' 
                                          AND t.class = '{$student_class['class']}'
                                          AND t.term_id = {$current_term['id']}
                                          ORDER BY ts.slot_order LIMIT 5");
    }
}

// Get announcements
$announcements = $conn->query("SELECT * FROM announcements WHERE is_active = 1 AND (target_role = 'all' OR target_role = '$role') ORDER BY created_at DESC LIMIT 3");

// Get certificate count
$certificate_count = 0;
if ($role == 'student' && $student_id) {
    $cert_sql = "SELECT COUNT(*) as count FROM certificates_log WHERE student_id = $student_id";
    $cert_result = $conn->query($cert_sql);
    $certificate_count = $cert_result->fetch_assoc()['count'];
}

// Get recent communications (for teacher)
$recent_communications = null;
if ($role == 'teacher') {
    $recent_communications = $conn->query("SELECT rs.*, s.first_name, s.last_name 
                                            FROM results_sent rs
                                            JOIN students s ON rs.student_id = s.id
                                            ORDER BY rs.sent_date DESC LIMIT 5");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Kitere CBC Exam System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        /* Header Styles */
        header { background: #002147; color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h2 { font-size: 24px; margin-bottom: 5px; }
        .logo p { font-size: 12px; opacity: 0.9; }
        .user-info { text-align: right; }
        .user-name { font-size: 16px; font-weight: bold; }
        .user-role { font-size: 12px; opacity: 0.9; margin-top: 5px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin-left: 15px; font-size: 14px; transition: background 0.3s; }
        .logout-btn:hover { background: #c82333; }
        
        /* Container */
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        /* Welcome Card */
        .welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .welcome-card h1 { font-size: 28px; margin-bottom: 10px; }
        .welcome-card p { opacity: 0.9; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #1abc9c; margin: 10px 0; }
        .stat-card .label { color: #666; font-size: 14px; }
        .stat-card .icon { font-size: 40px; margin-bottom: 10px; }
        
        /* Section Styles */
        .section { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { color: #002147; margin-bottom: 20px; border-bottom: 2px solid #1abc9c; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        
        /* Quick Actions Grid */
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .action-card { background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; text-decoration: none; color: #333; transition: all 0.3s; border: 1px solid #e0e0e0; }
        .action-card:hover { background: #1abc9c; color: white; transform: translateY(-3px); }
        .action-card .action-icon { font-size: 32px; margin-bottom: 10px; display: block; }
        .action-card .action-title { font-weight: bold; font-size: 14px; }
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; color: #002147; }
        tr:hover { background: #f5f5f5; }
        
        /* Grade Colors */
        .grade-EE { color: #27ae60; font-weight: bold; }
        .grade-ME { color: #f39c12; font-weight: bold; }
        .grade-AE { color: #e67e22; font-weight: bold; }
        .grade-BE { color: #e74c3c; font-weight: bold; }
        
        /* Timetable */
        .timetable-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; }
        .timetable-time { font-weight: bold; color: #002147; width: 100px; }
        .timetable-subject { flex: 1; }
        .timetable-teacher { color: #666; font-size: 12px; }
        
        /* Announcements */
        .announcement { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 10px; border-radius: 8px; }
        .announcement-title { font-weight: bold; color: #856404; }
        .announcement-date { font-size: 11px; color: #856404; margin-top: 5px; }
        
        /* Two Columns */
        .two-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; text-align: center; }
            .user-info { text-align: center; margin-top: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
            .two-columns { grid-template-columns: 1fr; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
        
        /* Footer */
        .footer { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .footer a { color: #1abc9c; text-decoration: none; margin: 0 15px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">
            <h2>📚 Kitere CBC Exam System</h2>
            <p>Excellence in Education</p>
        </div>
        <div class="user-info">
            <div class="user-name">Welcome, <?php echo htmlspecialchars($full_name); ?>!</div>
            <div class="user-role">Role: <?php echo ucfirst($role); ?></div>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </div>
</header>

<div class="container">
    <!-- Welcome Card -->
    <div class="welcome-card">
        <h1>Welcome to Your Dashboard</h1>
        <p>
            <?php if ($role == 'teacher'): ?>
                Manage student marks, track performance, communicate with parents, and generate reports.
            <?php else: ?>
                Track your academic progress, view results, monitor attendance, and download certificates.
            <?php endif; ?>
        </p>
        <p style="margin-top: 10px; font-size: 14px;">
            📅 Current Term: <?php echo $current_term['term_name'] ?? 'Not set'; ?> | 
            📆 Academic Year: <?php echo $current_year['year'] ?? 'Not set'; ?>
            <?php if ($role == 'student' && isset($position)): ?>
            | 🏆 Class Position: <?php echo $position; ?>
            <?php endif; ?>
        </p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <?php if ($role == 'teacher'): ?>
            <div class="stat-card" onclick="location.href='view-marks.php'">
                <div class="icon">📝</div>
                <div class="number"><?php echo $stats['total_marks_entered'] ?? 0; ?></div>
                <div class="label">Marks Entered</div>
            </div>
            <div class="stat-card" onclick="location.href='manage-students.php'">
                <div class="icon">👨‍🎓</div>
                <div class="number"><?php echo $stats['total_students'] ?? 0; ?></div>
                <div class="label">Students</div>
            </div>
            <div class="stat-card" onclick="location.href='ranking.php'">
                <div class="icon">📊</div>
                <div class="number"><?php echo $stats['avg_marks'] ?? 0; ?>%</div>
                <div class="label">Average Marks</div>
            </div>
            <div class="stat-card" onclick="location.href='view-timetable.php?view=teacher&teacher_id=<?php echo $user_id; ?>'">
                <div class="icon">📅</div>
                <div class="number"><?php echo $stats['total_classes'] ?? 0; ?></div>
                <div class="label">Today's Classes</div>
            </div>
            <div class="stat-card" onclick="location.href='enter-marks.php'">
                <div class="icon">⚠️</div>
                <div class="number"><?php echo $pending['pending_students'] ?? 0; ?></div>
                <div class="label">Pending Marks</div>
            </div>
        <?php else: ?>
            <div class="stat-card" onclick="location.href='view-marks.php'">
                <div class="icon">📚</div>
                <div class="number"><?php echo $stats['total_subjects'] ?? 0; ?></div>
                <div class="label">Subjects</div>
            </div>
            <div class="stat-card" onclick="location.href='ranking.php'">
                <div class="icon">🎯</div>
                <div class="number"><?php echo $stats['avg_marks'] ?? 0; ?>%</div>
                <div class="label">Average Score</div>
            </div>
            <div class="stat-card" onclick="location.href='ranking.php'">
                <div class="icon">🏆</div>
                <div class="number"><?php echo $position ?? 'N/A'; ?></div>
                <div class="label">Class Position</div>
            </div>
            <div class="stat-card" onclick="location.href='view-attendance.php'">
                <div class="icon">📅</div>
                <div class="number"><?php echo $stats['total_presents'] ?? 0; ?></div>
                <div class="label">Days Present</div>
            </div>
            <div class="stat-card" onclick="location.href='leaving-certificate.php'">
                <div class="icon">📜</div>
                <div class="number"><?php echo $stats['excellent_count'] ?? 0; ?></div>
                <div class="label">Excellent Grades</div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="section">
        <h2>🚀 Quick Actions</h2>
        <div class="actions-grid">
            <?php if ($role == 'teacher'): ?>
                <a href="view-marks.php" class="action-card"><span class="action-icon">➕</span><span class="action-title">Enter Marks</span></a>
                <a href="view-marks.php" class="action-card"><span class="action-icon">📊</span><span class="action-title">View Marks</span></a>
                <a href="manage-students.php" class="action-card"><span class="action-icon">👨‍🎓</span><span class="action-title">Manage Students</span></a>
                <a href="take-attendance.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Take Attendance</span></a>
                <a href="ranking.php" class="action-card"><span class="action-icon">🏆</span><span class="action-title">Rankings</span></a>
                <a href="merit-list.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Merit List</span></a>
                <a href="analytics.php" class="action-card"><span class="action-icon">📈</span><span class="action-title">Analytics</span></a>
                <a href="view-timetable.php?view=teacher&teacher_id=<?php echo $user_id; ?>" class="action-card"><span class="action-icon">📅</span><span class="action-title">My Timetable</span></a>
                <a href="sent-results.php" class="action-card"><span class="action-icon">📧</span><span class="action-title">Send Results</span></a>
                <a href="leaving-certificate.php" class="action-card"><span class="action-icon">📜</span><span class="action-title">Certificates</span></a>
                <a href="reports.php" class="action-card"><span class="action-icon">📄</span><span class="action-title">Reports</span></a>
                <a href="profile.php" class="action-card"><span class="action-icon">⚙️</span><span class="action-title">Profile</span></a>
            <!-- Add to Quick Actions section -->
<a href="sms-log.php" class="action-card"><span class="action-icon">📱</span><span class="action-title">SMS Log</span></a>
                <?php else: ?>
                <a href="view-marks.php" class="action-card"><span class="action-icon">📊</span><span class="action-title">My Results</span></a>
                <a href="view-attendance.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">My Attendance</span></a>
                <a href="ranking.php" class="action-card"><span class="action-icon">🏆</span><span class="action-title">Rankings</span></a>
                <a href="merit-list.php" class="action-card"><span class="action-icon">📋</span><span class="action-title">Merit List</span></a>
                <a href="analytics.php" class="action-card"><span class="action-icon">📈</span><span class="action-title">My Analytics</span></a>
                <a href="view-timetable.php?class=<?php echo $student_class['class'] ?? ''; ?>" class="action-card"><span class="action-icon">📅</span><span class="action-title">Class Timetable</span></a>
                <a href="leaving-certificate.php" class="action-card"><span class="action-icon">📜</span><span class="action-title">Get Certificate</span></a>
                <a href="fees-management.php" class="action-card"><span class="action-icon">💰</span><span class="action-title">Fee Status</span></a>
                <a href="profile.php" class="action-card"><span class="action-icon">👤</span><span class="action-title">My Profile</span></a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Two Column Layout -->
    <div class="two-columns">
        <!-- Today's Timetable -->
        <div class="section">
            <h2>📅 Today's Timetable</h2>
            <?php if (isset($today_timetable) && $today_timetable && $today_timetable->num_rows > 0): ?>
                <?php while($tt = $today_timetable->fetch_assoc()): ?>
                <div class="timetable-item">
                    <div class="timetable-time"><?php echo date('h:i A', strtotime($tt['start_time'])); ?> - <?php echo date('h:i A', strtotime($tt['end_time'])); ?></div>
                    <div class="timetable-subject">
                        <strong><?php echo $tt['subject_name']; ?></strong>
                        <?php if ($role == 'teacher'): ?>
                            <div class="timetable-teacher">Class: <?php echo $tt['class']; ?> | Room: <?php echo $tt['room'] ?? 'N/A'; ?></div>
                        <?php else: ?>
                            <div class="timetable-teacher">Teacher: <?php echo $tt['teacher_name']; ?> | Room: <?php echo $tt['room'] ?? 'N/A'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No classes scheduled for today.</p>
            <?php endif; ?>
            <div style="margin-top: 15px; text-align: center;">
                <a href="view-timetable.php" class="btn" style="background: #1abc9c; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px;">View Full Timetable</a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="section">
            <h2>📋 Recent Activity</h2>
            <?php if (isset($recent_result) && $recent_result && $recent_result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($role == 'teacher'): ?>
                                    <th>Student</th>
                                    <th>Subject</th>
                                <?php else: ?>
                                    <th>Subject</th>
                                    <th>Term</th>
                                <?php endif; ?>
                                <th>Marks</th>
                                <th>Grade</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $recent_result->fetch_assoc()): ?>
                                <tr>
                                    <?php if ($role == 'teacher'): ?>
                                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <?php else: ?>
                                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['term_name']); ?></td>
                                    <?php endif; ?>
                                    <td><strong><?php echo $row['marks']; ?>%</strong></td>
                                    <td class="grade-<?php echo $row['grade']; ?>"><?php echo $row['grade']; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px;">No recent activity found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Announcements & Communications -->
    <div class="two-columns">
        <!-- Announcements -->
        <div class="section">
            <h2>📢 Announcements</h2>
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while($ann = $announcements->fetch_assoc()): ?>
                <div class="announcement">
                    <div class="announcement-title">📌 <?php echo htmlspecialchars($ann['title']); ?></div>
                    <p><?php echo htmlspecialchars(substr($ann['content'], 0, 150)) . '...'; ?></p>
                    <div class="announcement-date">Posted: <?php echo date('d F, Y', strtotime($ann['created_at'])); ?></div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No announcements at this time.</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Communications (Teacher Only) -->
        <?php if ($role == 'teacher' && $recent_communications && $recent_communications->num_rows > 0): ?>
        <div class="section">
            <h2>📧 Recent Parent Communications</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Sent To</th>
                            <th>Via</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($msg = $recent_communications->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $msg['first_name'] . ' ' . $msg['last_name']; ?></td>
                            <td><?php echo $msg['sent_to']; ?></td>
                            <td><span style="background: #d4edda; padding: 3px 8px; border-radius: 4px;"><?php echo strtoupper($msg['sent_via']); ?></span></td>
                            <td><?php echo date('d-m-Y H:i', strtotime($msg['sent_date'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 15px; text-align: center;">
                <a href="sent-results.php" class="btn" style="background: #1abc9c; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px;">📧 Send More Results</a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Tip (Student Only) -->
        <?php if ($role == 'student'): ?>
        <div class="section">
            <h2>💡 Quick Tips</h2>
            <div style="background: #e8f4f8; padding: 15px; border-radius: 8px;">
                <ul style="margin-left: 20px;">
                    <li>Check your timetable daily to stay organized</li>
                    <li>Track your performance using the Analytics dashboard</li>
                    <li>Download your leaving certificate when you graduate</li>
                    <li>Monitor your attendance regularly</li>
                    <li>Contact your teachers if you need help with any subject</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer Navigation -->
    <div class="footer">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="view-marks.php">📊 Results</a>
        <a href="ranking.php">🏆 Rankings</a>
        <a href="analytics.php">📈 Analytics</a>
        <a href="view-timetable.php">📅 Timetable</a>
        <?php if ($role == 'teacher'): ?>
        <a href="sent-results.php">📧 Parent Comm</a>
        <?php endif; ?>
        <a href="leaving-certificate.php">📜 Certificate</a>
        <a href="profile.php">👤 Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
</div>
</body>
</html>