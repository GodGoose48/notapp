<?php
session_start();
header('Content-Type: application/json');
require_once '../models/db.php';

// Lấy dữ liệu JSON từ body
$data = json_decode(file_get_contents("php://input"), true);

$input = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Check if fields are filled
if (!$input || !$password) {
  echo json_encode([
    'success' => false,
    'message' => 'Email or username and password are required.'
  ]);
  exit;
}

// Query user by email or display name
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR display_name = ?");
$stmt->bind_param("ss", $input, $input);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
  $_SESSION['user_id'] = $user['id'];
  $_SESSION['display_name'] = $user['display_name'];

  echo json_encode([
    'success' => true,
    'redirect' => '/ltw-noteapp-final/frontend/views/home.php'
  ]);
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Invalid credentials.'
  ]);
}
?>
