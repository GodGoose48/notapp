<?php
session_start();
header('Content-Type: application/json');
require_once '../models/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $_SESSION['is_verified'] = (bool)$user['is_verified'];
    
    echo json_encode([
        'success' => true,
        'is_verified' => (bool)$user['is_verified']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>