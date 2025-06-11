<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../frontend/views/home.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$path = "../../uploads/avatar_$user_id.png";

if (file_exists($path)) {
  unlink($path);
}

header("Location: ../../frontend/views/home.php");
exit;
