<?php
session_start();
require_once '../models/db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../frontend/views/login.html");
  exit();
}

$label_id = $_POST['label_id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $label_id, $user_id);
$stmt->execute();

header("Location: ../../frontend/views/home.php");
exit();
