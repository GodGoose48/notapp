<?php
session_start();
require_once '../models/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$note_id = $_GET['note_id'] ?? null;

if (!$user_id || !$note_id) {
  http_response_code(400);
  exit;
}

$stmt = $conn->prepare("
  SELECT a.id, a.filename
  FROM attachment a
  JOIN notes n ON a.note_id = n.id
  WHERE a.note_id = ? AND n.user_id = ?
");
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$images = [];

while ($row = $result->fetch_assoc()) {
  $images[] = $row;
}

echo json_encode(['success' => true, 'images' => $images]);
