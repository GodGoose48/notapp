<?php

session_start();
header('Content-Type: application/json');
require_once '../models/db.php';
require_once '../utils/email_sender.php';

$email = $_POST['email'] ?? '';

if (!$email) {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, display_name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'Email not found in our system.'
    ]);
    exit;
}

// Generate reset token (32 chars) + OTP (6 digits)
$reset_token = bin2hex(random_bytes(16)) . sprintf('%06d', mt_rand(0, 999999));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

// Update user with reset token
$update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
$update_stmt->bind_param("ssi", $reset_token, $expires, $user['id']);

if ($update_stmt->execute()) {
    // Extract OTP from token (last 6 characters)
    $otp = substr($reset_token, -6);
    
    try {
        if (sendPasswordResetEmail($email, $reset_token, $otp, $user['display_name'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Password reset instructions have been sent to your email.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send reset email. Please try again later.'
            ]);
        }
    } catch (Exception $e) {
        error_log("Password reset email error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Email service is temporarily unavailable.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process password reset request.'
    ]);
}
?>