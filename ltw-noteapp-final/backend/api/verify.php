<?php
session_start();
require_once '../models/db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: ../../frontend/views/verify-result.html?status=error&message=Invalid verification link');
    exit;
}

$stmt = $conn->prepare("SELECT id, email, display_name FROM users WHERE activation_token = ? AND is_verified = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Update user as verified and clear the token
    $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, activation_token = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $user['id']);
    
    if ($update_stmt->execute()) {
        // Update session if user is currently logged in
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
            $_SESSION['is_verified'] = true;
        }
        
        header('Location: ../../frontend/views/verify-result.html?status=success&message=Account verified successfully');
    } else {
        header('Location: ../../frontend/views/verify-result.html?status=error&message=Verification failed');
    }
} else {
    header('Location: ../../frontend/views/verify-result.html?status=error&message=Invalid or expired verification link');
}
?>
