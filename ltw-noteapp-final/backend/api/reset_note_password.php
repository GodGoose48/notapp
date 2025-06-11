<?php
session_start();
header('Content-Type: application/json');
require_once '../models/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$note_id = $_POST['note_id'] ?? null;

if (!$user_id || !$note_id) {
  echo json_encode(['success' => false, 'message' => 'Missing fields']);
  exit;
}

$stmt = $conn->prepare("UPDATE notes SET password = NULL WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Note password has been reset']);
