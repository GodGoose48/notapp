<?php
session_start();
require_once '../models/db.php';
require_once '../models/notes.php';

$user_id = $_SESSION['user_id'] ?? null;
$note_id = $_POST['note_id'] ?? null;

if (!$user_id || !$note_id) {
  http_response_code(400);
  echo "Invalid request";
  exit;
}

// Lấy ghi chú để xác minh quyền sở hữu
$note = getNoteById($conn, $note_id);
if (!$note || $note['user_id'] != $user_id) {
  http_response_code(403);
  echo "Access denied";
  exit;
}

// Xoá ghi chú
deleteNote($conn, $note_id);
echo "Note deleted";
