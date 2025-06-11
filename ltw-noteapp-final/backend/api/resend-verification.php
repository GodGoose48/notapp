<?php
session_start();
header('Content-Type: application/json');
require_once '../models/db.php';
require_once '../utils/email_sender.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$stmt = $conn->prepare("SELECT email, display_name, is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    if ($user['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'Account is already verified']);
        exit;
    }
    
    // Generate new token
    $activation_token = bin2hex(random_bytes(32));
    
    // Update token in database
    $update_stmt = $conn->prepare("UPDATE users SET activation_token = ? WHERE id = ?");
    $update_stmt->bind_param("si", $activation_token, $_SESSION['user_id']);
    $update_stmt->execute();
    
    // Send email
    try {
        if (sendVerificationEmail($user['email'], $activation_token, $user['display_name'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Verification email sent successfully! Please check your email.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again later.'
            ]);
        }
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Email service is temporarily unavailable.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>