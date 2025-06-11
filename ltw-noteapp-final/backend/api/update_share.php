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

if (!$input || !isset($input['note_id']) || !isset($input['shared_with_email']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$note_id = $input['note_id'];
$shared_with_email = $input['shared_with_email'];
$action = $input['action'];

// Verify user owns the note
$stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Note not found or access denied']);
    exit;
}

try {
    if ($action === 'update') {
        if (!isset($input['permission']) || !in_array($input['permission'], ['read', 'edit'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid permission']);
            exit;
        }
        
        $permission = $input['permission'];
        
        if (updateNoteSharePermission($conn, $note_id, $shared_with_email, $permission, $user_id)) {
            echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
        } else {
            throw new Exception('Failed to update permission');
        }
        
    } elseif ($action === 'remove') {
        if (removeNoteShare($conn, $note_id, $shared_with_email, $user_id)) {
            echo json_encode(['success' => true, 'message' => 'Share removed successfully']);
        } else {
            throw new Exception('Failed to remove share');
        }
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>