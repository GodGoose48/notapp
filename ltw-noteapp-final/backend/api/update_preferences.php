<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once '../models/db.php';
require_once '../models/preferences.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate and sanitize input
$theme = $_POST['theme'] ?? 'light';
$font_size = intval($_POST['font_size'] ?? 16);
$note_color = $_POST['note_color'] ?? 'default';
$auto_save = isset($_POST['auto_save']) ? 1 : 0;
$auto_save_delay = intval($_POST['auto_save_delay'] ?? 800);

// Validate theme
if (!in_array($theme, ['light', 'dark'])) {
    $theme = 'light';
}

// Validate font size
if ($font_size < 12 || $font_size > 24) {
    $font_size = 16;
}

// Validate note color
$valid_colors = ['default', 'blue', 'green', 'yellow', 'pink', 'purple', 'orange'];
if (!in_array($note_color, $valid_colors)) {
    $note_color = 'default';
}

// Validate auto save delay
$valid_delays = [500, 800, 1000, 2000];
if (!in_array($auto_save_delay, $valid_delays)) {
    $auto_save_delay = 800;
}

$preferences = [
    'theme' => $theme,
    'font_size' => $font_size,
    'note_color' => $note_color,
    'auto_save' => $auto_save,
    'auto_save_delay' => $auto_save_delay
];

try {
    $success = updateUserPreferences($conn, $user_id, $preferences);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Preferences updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update preferences']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}