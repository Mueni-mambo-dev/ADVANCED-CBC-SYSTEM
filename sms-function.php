<?php
// sms_functions.php - Updated for SMSLeopard
require_once __DIR__ . '/sms-config.php';

// Initialize SMSLeopard
$smsLeopard = new SMSLeopardConfig($conn);

/**
 * Send results via SMSLeopard (Manual - CSV Export Method)
 */
function sendResultsViaSMSLeopard($class = null, $student_ids = []) {
    global $smsLeopard;
    
    // Generate CSV file
    $result = $smsLeopard->generateCSV($class, $student_ids);
    
    if ($result['count'] == 0) {
        return [
            'success' => false,
            'message' => 'No valid phone numbers found for selected students'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'CSV file generated successfully',
        'file' => $result['filename'],
        'filepath' => $result['filepath'],
        'count' => $result['count'],
        'instructions' => 'Download the CSV file and upload to SMSLeopard dashboard'
    ];
}

/**
 * Prepare result message for SMS
 */
function prepareResultMessage($student_id, $term_id, $year_id) {
    global $conn;
    
    // Get student details
    $student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
    
    // Get marks
    $marks = $conn->query("SELECT marks FROM marks WHERE student_id = $student_id AND term_id = $term_id AND academic_year_id = $year_id");
    $total = 0;
    $count = 0;
    while($m = $marks->fetch_assoc()) {
        $total += $m['marks'];
        $count++;
    }
    $average = $count > 0 ? round($total / $count, 2) : 0;
    
    // Determine grade
    if ($average >= 75) $grade = "EE (Excellent)";
    elseif ($average >= 50) $grade = "ME (Good)";
    elseif ($average >= 25) $grade = "AE (Average)";
    else $grade = "BE (Below Expectations)";
    
    // Get class position
    $position_sql = "SELECT COUNT(DISTINCT s2.id) + 1 as position
                     FROM students s1
                     JOIN marks m1 ON s1.id = m1.student_id
                     JOIN students s2 ON s2.class = s1.class
                     JOIN marks m2 ON s2.id = m2.student_id
                     WHERE s1.id = $student_id
                       AND s2.class = s1.class
                       AND m2.term_id = $term_id
                     GROUP BY s2.id
                     HAVING AVG(m2.marks) > (SELECT AVG(marks) FROM marks WHERE student_id = $student_id AND term_id = $term_id)";
    $position_result = $conn->query($position_sql);
    $position = $position_result->num_rows + 1;
    
    // Get total students
    $total_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE class = '{$student['class']}'")->fetch_assoc();
    
    // Build message (under 160 characters)
    $message = "KITERE CBC: {$student['first_name']} {$student['last_name']} - Avg: {$average}% - Grade: {$grade} - Position: {$position}/{$total_students['total']}";
    
    // Truncate if needed
    $message = substr($message, 0, 158);
    
    return [
        'message' => $message,
        'phone' => $smsLeopard->formatPhoneNumber($student['parent_phone']),
        'student_name' => $student['first_name'] . ' ' . $student['last_name']
    ];
}

/**
 * Log pending messages for batch sending
 */
function queueResultsForSending($student_ids, $term_id, $year_id) {
    global $conn, $smsLeopard;
    
    $queued = 0;
    foreach ($student_ids as $student_id) {
        $result_data = prepareResultMessage($student_id, $term_id, $year_id);
        
        if ($smsLeopard->isValidPhone($result_data['phone'])) {
            $smsLeopard->logSMS(
                $student_id,
                $result_data['phone'],
                $result_data['message'],
                'pending'
            );
            $queued++;
        }
    }
    
    return [
        'success' => true,
        'queued' => $queued,
        'message' => "$queued messages queued for sending"
    ];
}

/**
 * Export all pending messages to CSV for SMSLeopard
 */
function exportPendingMessages() {
    global $smsLeopard;
    
    $pending = $smsLeopard->getUnsentMessages();
    
    if ($pending->num_rows == 0) {
        return ['success' => false, 'message' => 'No pending messages'];
    }
    
    $filename = 'pending_messages_' . date('Y-m-d_H-i-s') . '.csv';
    $filepath = __DIR__ . '/exports/' . $filename;
    
    if (!file_exists(__DIR__ . '/exports')) {
        mkdir(__DIR__ . '/exports', 0777, true);
    }
    
    $file = fopen($filepath, 'w');
    fwrite($file, "\xEF\xBB\xBF");
    fputcsv($file, ['Phone Number', 'Message', 'Student Name', 'Class', 'Log ID']);
    
    while($row = $pending->fetch_assoc()) {
        fputcsv($file, [
            $row['parent_phone'],
            $row['message'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['class'],
            $row['id']
        ]);
    }
    
    fclose($file);
    
    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'count' => $pending->num_rows
    ];
}

/**
 * Mark messages as sent after uploading to SMSLeopard
 */
function markMessagesAsSent($log_ids) {
    global $conn;
    
    $ids = implode(',', $log_ids);
    $conn->query("UPDATE sms_log SET status = 'sent', sent_date = NOW() WHERE id IN ($ids)");
    
    return ['success' => true, 'updated' => $conn->affected_rows];
}

/**
 * Get SMS sending statistics
 */
function getSMSStatistics() {
    global $conn;
    
    $stats = [];
    
    $result = $conn->query("SELECT status, COUNT(*) as count FROM sms_log GROUP BY status");
    while($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as total_sent, SUM(credits_used) as total_credits FROM sms_log WHERE status IN ('sent', 'delivered')");
    $stats['total_sent'] = $result->fetch_assoc();
    
    return $stats;
}
?>