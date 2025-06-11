<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../models/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$note_id = $input['note_id'] ?? null;
$user_id = $_SESSION['user_id'];
$is_editing = isset($input['is_editing']) ? (bool)$input['is_editing'] : false;
$editing_field = $input['editing_field'] ?? null;

if (!$note_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Note ID required']);
    exit;
}

try {
    // Insert or update active user record
    $stmt = $conn->prepare("
        INSERT INTO active_users (note_id, user_id, last_seen, is_editing, editing_field) 
        VALUES (?, ?, NOW(), ?, ?) 
        ON DUPLICATE KEY UPDATE 
            last_seen = NOW(),
            is_editing = VALUES(is_editing),
            editing_field = VALUES(editing_field)
    ");
    $stmt->bind_param("iiis", $note_id, $user_id, $is_editing, $editing_field);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>