<?php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../models/db.php';

$note_id = $_POST['note_id'] ?? null;
$password = $_POST['password'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$note_id || !$password) {
    echo json_encode(['success' => false, 'message' => 'Note ID and password required']);
    exit;
}

if (strlen($password) < 4) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 4 characters']);
    exit;
}

try {
    // Check if note belongs to user
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Note not found']);
        exit;
    }
    
    // Hash password and update note
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $password_hash, $note_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Password set successfully',
            'password_hash' => $password_hash
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to set password']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>