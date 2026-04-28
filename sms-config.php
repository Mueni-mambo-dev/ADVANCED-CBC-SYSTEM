<?php
// smsleopard_config.php - SMSLeopard Configuration
// Place this file in your project root

class SMSLeopardConfig {
    // SMSLeopard API Credentials (Get from your dashboard)
    private $apiKey = '';  // Leave empty for manual sending
    private $apiSecret = ''; // Leave empty for manual sending
    private $senderId = 'KITERE_CBC'; // Your approved sender ID (or default)
    
    // Database connection
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Format phone number for SMSLeopard (254XXXXXXXX format)
     */
    public function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading 0 or +254
        if (substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) == '254') {
            $phone = $phone;
        } elseif (substr($phone, 0, 4) == '+254') {
            $phone = substr($phone, 1);
        }
        
        return $phone;
    }
    
    /**
     * Validate phone number
     */
    public function isValidPhone($phone) {
        $formatted = $this->formatPhoneNumber($phone);
        return preg_match('/^254[7-9][0-9]{8}$/', $formatted);
    }
    
    /**
     * Generate CSV file for SMSLeopard upload
     */
    public function generateCSV($class = null, $student_ids = []) {
        $where = "";
        if ($class) {
            $where = "AND s.class = '$class'";
        }
        if (!empty($student_ids)) {
            $ids = implode(',', $student_ids);
            $where = "AND s.id IN ($ids)";
        }
        
        $sql = "SELECT 
                    s.id,
                    s.parent_phone,
                    s.parent_name,
                    s.first_name,
                    s.last_name,
                    s.class,
                    s.stream,
                    ROUND(AVG(m.marks), 2) as avg_marks
                FROM students s
                LEFT JOIN marks m ON s.id = m.student_id
                WHERE s.parent_phone IS NOT NULL 
                AND s.parent_phone != ''
                AND s.sms_consent = 1
                $where
                GROUP BY s.id
                ORDER BY s.class, s.first_name";
        
        $result = $this->conn->query($sql);
        
        $filename = 'smsleopard_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/exports/' . $filename;
        
        // Create exports directory if not exists
        if (!file_exists(__DIR__ . '/exports')) {
            mkdir(__DIR__ . '/exports', 0777, true);
        }
        
        $file = fopen($filepath, 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($file, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($file, ['Number', 'Name', 'Class', 'StudentName', 'StudentID', 'AverageMarks']);
        
        while($row = $result->fetch_assoc()) {
            $phone = $this->formatPhoneNumber($row['parent_phone']);
            if ($this->isValidPhone($phone)) {
                fputcsv($file, [
                    $phone,
                    $row['parent_name'],
                    $row['class'] . ' ' . $row['stream'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['id'],
                    $row['avg_marks']
                ]);
            }
        }
        
        fclose($file);
        
        return ['filename' => $filename, 'filepath' => $filepath, 'count' => $result->num_rows];
    }
    
    /**
     * Log SMS to database
     */
    public function logSMS($student_id, $phone, $message, $status, $response = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO sms_log (student_id, parent_phone, message, status, smsleopard_response, sent_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("issss", $student_id, $phone, $message, $status, $response);
        $stmt->execute();
        return $stmt->insert_id;
    }
    
    /**
     * Update SMS status
     */
    public function updateSMSStatus($log_id, $status, $delivered_date = null) {
        $delivered = $delivered_date ?: date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("UPDATE sms_log SET status = ?, delivered_date = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $delivered, $log_id);
        return $stmt->execute();
    }
    
    /**
     * Get SMS balance from database
     */
    public function getBalance() {
        $result = $this->conn->query("SELECT balance, last_checked FROM sms_balance ORDER BY id DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return ['balance' => 0, 'last_checked' => null];
    }
    
    /**
     * Update balance (manual entry after SMSLeopard recharge)
     */
    public function updateBalance($new_balance) {
        $stmt = $this->conn->prepare("UPDATE sms_balance SET balance = ?, last_checked = NOW()");
        $stmt->bind_param("i", $new_balance);
        return $stmt->execute();
    }
    
    /**
     * Get unsent messages (for manual sending)
     */
    public function getUnsentMessages($limit = 100) {
        $sql = "SELECT l.*, s.first_name, s.last_name, s.class 
                FROM sms_log l
                JOIN students s ON l.student_id = s.id
                WHERE l.status = 'pending'
                ORDER BY l.created_at ASC
                LIMIT $limit";
        return $this->conn->query($sql);
    }
}
?>