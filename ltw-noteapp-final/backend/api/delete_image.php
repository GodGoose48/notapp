<?php
session_start();
require_once '../models/db.php';

$image_id = $_POST['image_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || !$image_id) {
  http_response_code(400);
  exit;
}

$stmt = $conn->prepare("
  SELECT a.filename, n.user_id 
  FROM attachment a 
  JOIN notes n ON a.note_id = n.id 
  WHERE a.id = ?
");
$stmt->bind_param("i", $image_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || $result['user_id'] != $user_id) {
  http_response_code(403);
  exit;
}

$filepath = "../../uploads/" . $result['filename'];
if (file_exists($filepath)) unlink($filepath);

$stmt = $conn->prepare("DELETE FROM attachment WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();

echo json_encode(['success' => true]);
