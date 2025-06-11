<?php

session_start();
header('Content-Type: application/json');
require_once '../models/db.php';

// Check if reset was verified
if (!isset($_SESSION['reset_verified']) || !$_SESSION['reset_verified']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please verify your reset request first.'
    ]);
    exit;
}

$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!$new_password || !$confirm_password) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode([
        'success' => false,
        'message' => 'Passwords do not match.'
    ]);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters long.'
    ]);
    exit;
}

$email = $_SESSION['reset_email'];
$reset_token = $_SESSION['reset_token'];

// Verify token is still valid
$verify_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()");
$verify_stmt->bind_param("ss", $email, $reset_token);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Reset session expired. Please request a new password reset.'
    ]);
    exit;
}

// Update password and clear reset token
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE email = ?");
$update_stmt->bind_param("ss", $hashed_password, $email);

if ($update_stmt->execute()) {
    // Clear reset session
    unset($_SESSION['reset_verified']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_token']);
    
    // Log out user if they're currently logged in
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully. Please log in with your new password.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update password. Please try again.'
    ]);
}
?>