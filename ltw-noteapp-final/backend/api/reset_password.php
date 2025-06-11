<?php
session_start();
require_once __DIR__ . '/../models/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is authorized to reset password
if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please verify your email first.']);
    exit;
}

$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if (empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all password fields']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    $user_id = $_SESSION['reset_user_id'];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);    // Update user password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    $stmt->execute();

    // Clear session
    unset($_SESSION['otp_verified'], $_SESSION['reset_user_id']);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully! Redirecting to login...']);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
?>
