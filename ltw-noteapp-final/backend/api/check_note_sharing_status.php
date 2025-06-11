<?php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once '../models/db.php';

$note_id = $_GET['note_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$note_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Note ID required']);
    exit;
}

try {
    // Check if user owns the note
    $stmt = $conn->prepare("SELECT user_id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();

    if (!$note) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Note not found or access denied']);
        exit;
    }

    // Check if the note is shared with others
    $stmt = $conn->prepare("
        SELECT COUNT(*) as share_count
        FROM shared_notes 
        WHERE note_id = ?
    ");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $is_shared = $result['share_count'] > 0;
    $has_collaborators = false;

    // If shared, check if any collaborators have edit permissions
    if ($is_shared) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as editor_count
            FROM shared_notes 
            WHERE note_id = ? AND permission = 'edit'
        ");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $editor_result = $stmt->get_result()->fetch_assoc();
        
        $has_collaborators = $editor_result['editor_count'] > 0;
    }

    echo json_encode([
        'success' => true,
        'is_shared' => $is_shared,
        'has_collaborators' => $has_collaborators,
        'share_count' => (int)$result['share_count']
    ]);

} catch (Exception $e) {
    error_log("Error in check_note_sharing_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>