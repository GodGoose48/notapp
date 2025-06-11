<?php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../models/db.php';

$note_id = $_GET['note_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$note_id) {
    echo json_encode(['success' => false, 'message' => 'Note ID required']);
    exit;
}

try {
    // Check if note belongs to user and has password
    $stmt = $conn->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Note not found']);
        exit;
    }
    
    $note = $result->fetch_assoc();
    $has_password = !empty($note['password_hash']);
    
    echo json_encode([
        'success' => true,
        'has_password' => $has_password,
        'password_hash' => $has_password ? $note['password_hash'] : null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>