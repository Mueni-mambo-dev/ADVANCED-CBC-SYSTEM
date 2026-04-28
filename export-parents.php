<?php
// export_parents.php - Quick export for SMSLeopard
include "db.php";
include "sms-config.php";

$smsLeopard = new SMSLeopardConfig($conn);
$class = isset($_GET['class']) ? $_GET['class'] : null;

$result = $smsLeopard->generateCSV($class);

if ($result['success']) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    readfile($result['filepath']);
    exit();
} else {
    echo "Error: " . $result['message'];
}
?>