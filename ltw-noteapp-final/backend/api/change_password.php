
<?php
session_start();
require_once '../models/db.php';
require_once '../models/users.php';

$user_id = $_SESSION['user_id'];
$new_password = $_POST['new_password'];
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed, $user_id);

if ($stmt->execute()) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false]);
}
