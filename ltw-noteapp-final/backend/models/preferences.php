<?php

function getUserPreferences($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Return default preferences if none exist
    if (!$result) {
        return [
            'theme' => 'light',
            'font_size' => 16,
            'note_color' => 'default',
            'auto_save' => 1,
            'auto_save_delay' => 800
        ];
    }
    
    return $result;
}

function updateUserPreferences($conn, $user_id, $preferences) {
    // Check if preferences exist
    $stmt = $conn->prepare("SELECT user_id FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    
    if ($exists) {
        // Update existing preferences
        $stmt = $conn->prepare("
            UPDATE user_preferences 
            SET theme = ?, font_size = ?, note_color = ?, auto_save = ?, auto_save_delay = ? 
            WHERE user_id = ?
        ");
        $stmt->bind_param("sisiii", 
            $preferences['theme'],
            $preferences['font_size'],
            $preferences['note_color'],
            $preferences['auto_save'],
            $preferences['auto_save_delay'],
            $user_id
        );
    } else {
        // Insert new preferences
        $stmt = $conn->prepare("
            INSERT INTO user_preferences (user_id, theme, font_size, note_color, auto_save, auto_save_delay) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isisii", 
            $user_id,
            $preferences['theme'],
            $preferences['font_size'],
            $preferences['note_color'],
            $preferences['auto_save'],
            $preferences['auto_save_delay']
        );
    }
    
    return $stmt->execute();
}