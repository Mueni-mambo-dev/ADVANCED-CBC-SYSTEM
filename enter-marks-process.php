<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please login first.";
    $_SESSION['message_type'] = "error";
    header("Location: LOGIN.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = intval($_POST['student_id']);
    $subject_id = intval($_POST['subject_id']);
    $term_id = intval($_POST['term_id']);
    $academic_year_id = intval($_POST['academic_year_id']);
    $exam_type = mysqli_real_escape_string($conn, $_POST['exam_type']);
    $marks = floatval($_POST['marks']);
    $recorded_by = $_SESSION['user_id'];

    if ($marks < 0 || $marks > 100) {
        $_SESSION['message'] = "Marks must be between 0 and 100.";
        $_SESSION['message_type'] = "error";
        header("Location: enter-marks.php");
        exit();
    }

    // Determine grade
    if ($marks >= 75) $grade = "EE";
    elseif ($marks >= 50) $grade = "ME";
    elseif ($marks >= 25) $grade = "AE";
    else $grade = "BE";

    // Check for duplicate
    $check = $conn->prepare("SELECT id FROM marks WHERE student_id = ? AND subject_id = ? AND term_id = ? AND academic_year_id = ? AND exam_type = ?");
    $check->bind_param("iiiis", $student_id, $subject_id, $term_id, $academic_year_id, $exam_type);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $_SESSION['message'] = "Marks already exist for this student, subject, term, year and exam type.";
        $_SESSION['message_type'] = "error";
        $check->close();
        header("Location: enter-marks.php");
        exit();
    }
    $check->close();

    // Insert
    $stmt = $conn->prepare("INSERT INTO marks (student_id, subject_id, marks, term_id, academic_year_id, exam_type, grade, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidiiisi", $student_id, $subject_id, $marks, $term_id, $academic_year_id, $exam_type, $grade, $recorded_by);

    if ($stmt->execute()) {
        $_SESSION['message'] = "✅ Marks saved successfully! Grade: $grade";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    header("Location: enter-marks.php");
    exit();
} else {
    header("Location: enter-marks.php");
    exit();
}
?>