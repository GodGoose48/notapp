<?php
require_once __DIR__ . '/../models/db.php';
require_once __DIR__ . '/../utils/email_sender.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, display_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Email address not found']);
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $display_name = $user['display_name'];    // Generate OTP only (6-digit code)
    $otp = sprintf('%06d', mt_rand(1, 999999));

    // Store in session for OTP verification
    session_start();
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_user_id'] = $user_id;
    $_SESSION['otp_expires'] = time() + 600; // 10 minutes

    // Send OTP email using the email_sender utility
    if (sendOTPEmail($email, $otp, $display_name)) {
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent to your email! Please check your inbox and enter the 6-digit code.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
?>