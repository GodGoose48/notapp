<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../models/db.php';
require_once '../models/sharing.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$note_id = $input['note_id'];
$field = $input['field'];
$value = $input['value'];
$version = $input['version'];

// Get user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_email = $user_data['email'];

// Check edit permission
if (!hasNoteAccess($conn, $note_id, $user_id, $user_email, 'edit')) {
    http_response_code(403);
    echo json_encode(['error' => 'No edit permission']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Update the note
    if ($field === 'title') {
        $stmt = $conn->prepare("UPDATE notes SET title = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $value, $note_id);
    } else if ($field === 'content') {
        $stmt = $conn->prepare("UPDATE notes SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $value, $note_id);
    } else {
        throw new Exception('Invalid field');
    }
    
    $stmt->execute();
    
    // Create collaboration log entry
    $new_version = $version + 1;
    $stmt = $conn->prepare("
        INSERT INTO collaboration_log (note_id, user_id, change_type, field_name, new_value, version, created_at) 
        VALUES (?, ?, 'update', ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iissi", $note_id, $user_id, $field, $value, $new_version);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'new_version' => $new_version,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>