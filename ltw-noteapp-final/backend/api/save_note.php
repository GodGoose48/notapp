<?php
session_start();
require_once '../models/db.php';
require_once '../models/labels.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false]);
  exit;
}

$user_id = $_SESSION['user_id'];
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$label_ids = $_POST['labels'] ?? [];
$note_id = $_POST['note_id'] ?? null;

if ($title === '' || $content === '') {
  echo json_encode(['success' => false, 'message' => 'Missing title or content']);
  exit;
}

// UPDATE nếu có note_id
if ($note_id) {
  $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ssii", $title, $content, $note_id, $user_id);
  $stmt->execute();
} else {
  // INSERT mới
  $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
  $stmt->bind_param("iss", $user_id, $title, $content);
  $stmt->execute();
  $note_id = $stmt->insert_id;
}

// Cập nhật label
$stmt = $conn->prepare("DELETE FROM label_note WHERE note_id = ?");
$stmt->bind_param("i", $note_id);
$stmt->execute();

foreach ($label_ids as $label_id) {
  $stmt2 = $conn->prepare("INSERT IGNORE INTO label_note (label_id, note_id) VALUES (?, ?)");
  $stmt2->bind_param("ii", $label_id, $note_id);
  $stmt2->execute();
}

// Ảnh (nếu có)
if (!empty($_FILES['images']['name'][0])) {
  foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
    $filename = basename($_FILES['images']['name'][$i]);
    $target = "../../uploads/" . $filename;
    if (move_uploaded_file($tmp, $target)) {
      $stmt = $conn->prepare("UPDATE notes SET image = ? WHERE id = ?");
      $stmt->bind_param("si", $filename, $note_id);
      $stmt->execute();
    }
  }
}

echo json_encode(['success' => true, 'note_id' => $note_id]);
