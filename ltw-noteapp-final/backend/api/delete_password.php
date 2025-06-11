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

try {
    // Get current password hash
    $stmt = $conn->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Note not found']);
        exit;
    }
    
    $note = $result->fetch_assoc();
    
    if (empty($note['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Note is not password protected']);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $note['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
    
    // Delete password protection (set password_hash to NULL)
    $stmt = $conn->prepare("UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete password']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>