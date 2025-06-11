<?php
session_start();
require_once '../models/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$note_id = $_POST['note_id'] ?? null;

if (!$user_id || !$note_id || empty($_FILES['images']['name'][0])) {
  http_response_code(400);
  echo "Invalid request";
  exit;
}

$uploaded = [];

foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
  $name = basename($_FILES['images']['name'][$i]);
  $filename = time() . '_' . $name;
  $target = "../../uploads/" . $filename;

  if (move_uploaded_file($tmp, $target)) {
    $stmt = $conn->prepare("INSERT INTO attachment (note_id, filename) VALUES (?, ?)");
    $stmt->bind_param("is", $note_id, $filename);
    $stmt->execute();
    $uploaded[] = $filename;
  }
}

echo json_encode(['success' => true, 'files' => $uploaded]);
