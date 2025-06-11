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

if (!$input || !isset($input['note_id']) || !isset($input['emails']) || !isset($input['permission'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$note_id = $input['note_id'];
$emails = $input['emails']; // Array of emails
$permission = $input['permission'];

// Validate permission
if (!in_array($permission, ['read', 'edit'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid permission']);
    exit;
}

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

$conn->begin_transaction();

try {
    $successful_shares = [];
    $failed_shares = [];
    
    foreach ($emails as $email) {
        $email = trim($email);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $failed_shares[] = ['email' => $email, 'reason' => 'Invalid email format'];
            continue;
        }
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, display_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user_result = $stmt->get_result();
        
        if ($user_result->num_rows === 0) {
            $failed_shares[] = ['email' => $email, 'reason' => 'User not found'];
            continue;
        }
        
        $shared_user = $user_result->fetch_assoc();
        
        // Don't allow sharing with self
        if ($shared_user['id'] == $user_id) {
            $failed_shares[] = ['email' => $email, 'reason' => 'Cannot share with yourself'];
            continue;
        }
        
        // Check if already shared
        $stmt = $conn->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND shared_with_email = ?");
        $stmt->bind_param("is", $note_id, $email);
        $stmt->execute();
        $existing = $stmt->get_result();
        
        if ($existing->num_rows > 0) {
            // Update existing share
            if (updateNoteSharePermission($conn, $note_id, $email, $permission, $user_id)) {
                $successful_shares[] = ['email' => $email, 'action' => 'updated'];
            } else {
                $failed_shares[] = ['email' => $email, 'reason' => 'Failed to update existing share'];
            }
        } else {
            // Create new share
            if (shareNote($conn, $note_id, $user_id, $email, $permission)) {
                $successful_shares[] = ['email' => $email, 'action' => 'created'];
            } else {
                $failed_shares[] = ['email' => $email, 'reason' => 'Failed to create share'];
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'successful_shares' => $successful_shares,
        'failed_shares' => $failed_shares,
        'message' => count($successful_shares) . ' shares processed successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>