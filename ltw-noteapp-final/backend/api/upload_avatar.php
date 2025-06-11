<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_FILES['avatar'])) {
  header("Location: ../../frontend/views/home.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$target = "../../uploads/avatar_$user_id.png";

move_uploaded_file($_FILES['avatar']['tmp_name'], $target);
header("Location: ../../frontend/views/home.php");
exit;
