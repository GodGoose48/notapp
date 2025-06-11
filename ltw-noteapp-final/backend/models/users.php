<?php
function getUserById($conn, $user_id) {
  $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc();
}

function verifyUserPassword($conn, $user_id, $password) {
  $user = getUserById($conn, $user_id);
  if (!$user) return false;
  return password_verify($password, $user['password']);
}
