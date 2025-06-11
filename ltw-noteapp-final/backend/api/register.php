<?php
session_start();
header('Content-Type: application/json');
require_once '../models/db.php';
require_once '../utils/email_sender.php';

$email = $_POST['email'] ?? '';
$display_name = $_POST['display_name'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!$email || !$display_name || !$password || !$confirm_password) {
  echo json_encode([
    'success' => false,
    'message' => 'All fields are required.'
  ]);
  exit;
}

if ($password !== $confirm_password) {
  echo json_encode([
    'success' => false,
    'message' => 'Passwords do not match.'
  ]);
  exit;
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
  echo json_encode([
    'success' => false,
    'message' => 'Email is already registered.'
  ]);
  exit;
}

// Kiểm tra username trùng
$check_username = $conn->prepare("SELECT id FROM users WHERE display_name = ?");
$check_username->bind_param("s", $display_name);
$check_username->execute();
$check_username->store_result();

if ($check_username->num_rows > 0) {
  echo json_encode([
    'success' => false,
    'message' => 'Username is already taken.'
  ]);
  exit;
}

$activation_token = bin2hex(random_bytes(32)); // Increased token length for security
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
  "INSERT INTO users (email, password, display_name, is_verified, activation_token)
   VALUES (?, ?, ?, 0, ?)"
);
$stmt->bind_param("ssss", $email, $hashed_password, $display_name, $activation_token);

if ($stmt->execute()) {
  $_SESSION['user_id'] = $stmt->insert_id;
  $_SESSION['display_name'] = $display_name;
  $_SESSION['is_verified'] = false; // Add verification status to session

  // Try to send verification email
  try {
    if (sendVerificationEmail($email, $activation_token, $display_name)) {
      echo json_encode([
        'success' => true,
        'message' => "Registration successful! A verification email has been sent to $email. Please check your email to activate your account.",
        'is_verified' => false
      ]);
    } else {
      echo json_encode([
        'success' => true,
        'message' => "Registration successful! However, we couldn't send the verification email. You can still use the app, but please contact support for manual verification.",
        'is_verified' => false
      ]);
    }
  } catch (Exception $e) {
    error_log("Email sending error: " . $e->getMessage());
    echo json_encode([
      'success' => true,
      'message' => "Registration successful! Email service is temporarily unavailable. You can still use the app.",
      'is_verified' => false
    ]);
  }
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Registration failed. Please try again.'
  ]);
}
?>
