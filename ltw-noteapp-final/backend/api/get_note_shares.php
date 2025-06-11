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

$note_id = $_GET['note_id'] ?? null;

if (!$note_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Note ID is required']);
    exit;
}

$user_id = $_SESSION['user_id'];

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
    $shares = getNoteShares($conn, $note_id, $user_id);
    
    echo json_encode([
        'success' => true,
        'shares' => $shares
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load shares: ' . $e->getMessage()]);
}
?>