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
$last_update = $_GET['last_update'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$note_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Note ID required']);
    exit;
}

try {
    // Get user email for permission checking
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $user_email = $user_data['email'];

    // Check if user has access to this note (owner or shared with edit permission)
    $stmt = $conn->prepare("
        SELECT n.*, u.email as owner_email, u.display_name as owner_name,
               CASE 
                   WHEN n.user_id = ? THEN 'owner'
                   WHEN EXISTS(
                       SELECT 1 FROM shared_notes sn 
                       WHERE sn.note_id = ? AND sn.shared_with_email = ? AND sn.permission = 'edit'
                   ) THEN 'collaborator'
                   ELSE 'none'
               END as access_type
        FROM notes n 
        JOIN users u ON n.user_id = u.id 
        WHERE n.id = ? AND (
            n.user_id = ? OR 
            EXISTS(
                SELECT 1 FROM shared_notes sn 
                WHERE sn.note_id = ? AND sn.shared_with_email = ? AND sn.permission = 'edit'
            )
        )
    ");
    $stmt->bind_param("iissiis", $user_id, $note_id, $user_email, $note_id, $user_id, $note_id, $user_email);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();

    if (!$note) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    // Only allow real-time updates for notes that are actually shared with collaborators
    // If the user is the owner, check if the note is shared with others
    if ($note['access_type'] === 'owner') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as collaborator_count
            FROM shared_notes 
            WHERE note_id = ? AND permission = 'edit'
        ");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $collab_result = $stmt->get_result()->fetch_assoc();
        
        // If no collaborators, don't send updates
        if ($collab_result['collaborator_count'] == 0) {
            echo json_encode(['success' => true, 'has_updates' => false, 'message' => 'No collaborators']);
            exit;
        }
    }

    // Convert last_update from JavaScript timestamp to MySQL timestamp
    $last_update_mysql = date('Y-m-d H:i:s', $last_update / 1000);
    $note_updated_mysql = $note['updated_at'];

    // Check if note has been updated since last check
    $has_updates = strtotime($note_updated_mysql) > strtotime($last_update_mysql);

    if (!$has_updates) {
        echo json_encode(['success' => true, 'has_updates' => false]);
        exit;
    }

    // Get note labels
    $stmt = $conn->prepare("
        SELECT l.id 
        FROM labels l 
        JOIN label_note ln ON l.id = ln.label_id 
        WHERE ln.note_id = ?
    ");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $label_result = $stmt->get_result();
    $labels = [];
    while ($row = $label_result->fetch_assoc()) {
        $labels[] = (int)$row['id'];
    }

    // Return updated note data
    $response = [
        'success' => true,
        'has_updates' => true,
        'note' => [
            'id' => $note['id'],
            'title' => $note['title'],
            'content' => $note['content'],
            'is_pinned' => (bool)$note['is_pinned'],
            'labels' => $labels,
            'updated_at' => $note['updated_at']
        ],
        'access_type' => $note['access_type']
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_note_updates.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>