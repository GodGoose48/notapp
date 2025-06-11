<?php
session_start();
header('Content-Type: application/json');

require_once '../models/db.php';
require_once '../models/notes.php';
require_once '../models/labels.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

// Lấy dữ liệu từ FormData
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$is_pinned = (isset($_POST['is_pinned']) && $_POST['is_pinned'] == '1') ? 1 : 0;
$label_ids = json_decode($_POST['labels'] ?? '[]', true);
$password = $_POST['password'] ?? null; // ✅ Thêm dòng này

// Validate dữ liệu
if ($title === '' && $content === '') {
  echo json_encode(['success' => false, 'message' => 'Missing title or content']);
  exit;
}

// Xử lý ảnh (nếu có)
$image_path = null;
if (!empty($_FILES['images']['name'][0])) {
  $filename = basename($_FILES['images']['name'][0]);
  $target = "../../uploads/" . $filename;
  if (move_uploaded_file($_FILES['images']['tmp_name'][0], $target)) {
    $image_path = $filename;
  }
}

// Tạo note (gọi hàm có $password)
$note_id = createNote($conn, $user_id, $title, $content, $is_pinned, $image_path, $password);

// Gắn nhãn
if (!empty($label_ids)) {
  syncLabelsToNote($conn, $note_id, $label_ids);
}

// Phản hồi thành công
echo json_encode(['success' => true, 'note_id' => $note_id]);
