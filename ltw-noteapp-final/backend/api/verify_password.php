

<?php
session_start();
require_once '../models/db.php';
require_once '../models/users.php';

$user_id = $_SESSION['user_id'];
$password = $_POST['password'];

if (verifyUserPassword($conn, $user_id, $password)) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false]);
}
