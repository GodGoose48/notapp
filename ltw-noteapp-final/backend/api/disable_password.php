<?php
session_start();
require_once '../models/db.php'; 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$note_id = $_POST['note_id'] ?? '';
$password = $_POST['password'] ?? '';
$skip_current_password = isset($_POST['skip_current_password']) && $_POST['skip_current_password'] === 'true';

if (!$note_id) {
    echo json_encode(['success' => false, 'message' => 'Note ID is required']);
    exit;
}

try {
    // Get the note and verify ownership
    $stmt = $conn->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Note not found']);
        exit;
    }
    
    $note = $result->fetch_assoc();

    // Check if note is password protected
    if (!$note['password_hash']) {
        echo json_encode(['success' => false, 'message' => 'Note is not password protected']);
        exit;
    }

    // Verify current password (unless skipped)
    if (!$skip_current_password) {
        if (!$password) {
            echo json_encode(['success' => false, 'message' => 'Current password is required']);
            exit;
        }

        if (!password_verify($password, $note['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password']);
            exit;
        }
    }

    // Remove password protection
    $stmt = $conn->prepare("UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $_SESSION['user_id']);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Password protection disabled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to disable password protection']);
    }

} catch (Exception $e) {
    error_log("Error in disable_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>