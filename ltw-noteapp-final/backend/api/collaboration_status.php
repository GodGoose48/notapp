<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../models/db.php';

$note_id = $_GET['note_id'] ?? null;

if (!$note_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Note ID required']);
    exit;
}

try {
    // Clean up old active users (older than 5 minutes)
    $stmt = $conn->prepare("DELETE FROM active_users WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    
    // Get active users for this note
    $stmt = $conn->prepare("
        SELECT au.user_id, u.email, u.display_name 
        FROM active_users au 
        JOIN users u ON au.user_id = u.id 
        WHERE au.note_id = ? AND au.last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $active_users = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'active_users' => count($active_users),
        'active_users_list' => array_map(function($user) {
            return [
                'id' => $user['user_id'],
                'email' => $user['email'],
                'name' => $user['display_name']
            ];
        }, $active_users)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>