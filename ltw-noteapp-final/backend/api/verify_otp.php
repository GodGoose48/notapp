<?php
session_start();
require_once __DIR__ . '/../models/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$otp = trim($_POST['otp'] ?? '');

if (empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Please enter the OTP code']);
    exit;
}

// Check if OTP exists in session
if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expires']) || !isset($_SESSION['otp_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
    exit;
}

// Check if OTP has expired
if (time() > $_SESSION['otp_expires']) {
    unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_email'], $_SESSION['otp_user_id']);
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
    exit;
}

if ($otp === $_SESSION['otp']) {
    $_SESSION['otp_verified'] = true;
    $_SESSION['reset_user_id'] = $_SESSION['otp_user_id'];
    
    // Clear OTP data
    unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_email'], $_SESSION['otp_user_id']);
    
    echo json_encode(['success' => true, 'message' => 'OTP verified successfully! Please enter your new password.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please try again.']);
}
?>
