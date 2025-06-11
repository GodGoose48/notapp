<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once '../models/db.php';

$user_id = $_SESSION['user_id'];
$note_id = $_POST['id'] ?? null;
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$is_pinned = isset($_POST['is_pinned']) ? (int)$_POST['is_pinned'] : 0;
$labels = isset($_POST['labels']) ? json_decode($_POST['labels'], true) : null;
$is_collaborative = isset($_POST['collaborative_save']);

if (!$note_id) {
    echo json_encode(['success' => false, 'error' => 'Note ID is required']);
    exit;
}

try {
    // Get user email for permission checking
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $user_email = $user_data['email'];

    // Check if user owns the note OR has edit permission
    $stmt = $conn->prepare("
        SELECT n.id, n.user_id,
               CASE 
                   WHEN n.user_id = ? THEN 'owner'
                   WHEN EXISTS(
                       SELECT 1 FROM shared_notes sn 
                       WHERE sn.note_id = ? AND sn.shared_with_email = ? AND sn.permission = 'edit'
                   ) THEN 'collaborator'
                   ELSE 'none'
               END as access_type
        FROM notes n 
        WHERE n.id = ? AND (
            n.user_id = ? OR 
            EXISTS(
                SELECT 1 FROM shared_notes sn 
                WHERE sn.note_id = ? AND sn.shared_with_email = ? AND sn.permission = 'edit'
            )
        )
    ");
    $stmt->bind_param("iisiiss", $user_id, $note_id, $user_email, $note_id, $user_id, $note_id, $user_email);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();

    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Note not found or no edit permission']);
        exit;
    }

    // Update the note
    $stmt = $conn->prepare("
        UPDATE notes 
        SET title = ?, content = ?, is_pinned = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("ssii", $title, $content, $is_pinned, $note_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update note");
    }

    // Handle labels (only if user is the owner or note is collaborative)
    if ($labels !== null && ($note['user_id'] == $user_id || $is_collaborative)) {
        // For collaborators, only allow label changes if they have edit permission
        if ($note['access_type'] === 'owner' || $note['access_type'] === 'collaborator') {
            // Remove existing labels
            $stmt = $conn->prepare("DELETE FROM label_note WHERE note_id = ?");
            $stmt->bind_param("i", $note_id);
            $stmt->execute();

            // Add new labels
            if (!empty($labels)) {
                $stmt = $conn->prepare("INSERT INTO label_note (label_id, note_id) VALUES (?, ?)");
                foreach ($labels as $label_id) {
                    if (is_numeric($label_id)) {
                        $stmt->bind_param("ii", $label_id, $note_id);
                        $stmt->execute();
                    }
                }
            }
        }
    }

    // Get the updated timestamp for collaborative sync
    $stmt = $conn->prepare("SELECT updated_at FROM notes WHERE id = ?");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $response = [
        'success' => true,
        'message' => 'Note updated successfully',
        'updated_at' => $result['updated_at'],
        'access_type' => $note['access_type']
    ];

    // If this is a collaborative save, include additional metadata
    if ($is_collaborative) {
        $response['collaborative'] = true;
        $response['user_id'] = $user_id;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error updating note: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update note: ' . $e->getMessage()]);
}
?>
