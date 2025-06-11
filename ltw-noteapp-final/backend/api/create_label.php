<?php
session_start();
require_once '../models/db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../frontend/views/login.html");
  exit();
}

$user_id = $_SESSION['user_id'];
$label_name = trim($_POST['label_name']);

if ($label_name !== "") {
  $stmt = $conn->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?)");
  $stmt->bind_param("is", $user_id, $label_name);
  $stmt->execute();
}

header("Location: ../../frontend/views/home.php");
exit();
