<?php
require_once 'db.php';

function shareNote($conn, $note_id, $owner_id, $shared_with_email, $permission = 'read')
{
    // Check if note belongs to owner
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $owner_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        return false;
    }

    // Get shared_with_id if user exists
    $shared_with_id = null;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $shared_with_email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $shared_with_id = $user['id'];
    }

    // Insert or update share
    $stmt = $conn->prepare("
        INSERT INTO shared_notes (note_id, owner_id, shared_with_email, shared_with_id, permission) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE permission = VALUES(permission), shared_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iisis", $note_id, $owner_id, $shared_with_email, $shared_with_id, $permission);

    return $stmt->execute();
}

function getSharedNotes($conn, $user_id)
{
    $stmt = $conn->prepare("
        SELECT n.*, sn.permission, sn.shared_at, sn.shared_with_email, 
               u.display_name as owner_name, u.email as owner_email
        FROM notes n
        JOIN shared_notes sn ON n.id = sn.note_id
        JOIN users u ON sn.owner_id = u.id
        WHERE sn.shared_with_id = ? OR sn.shared_with_email = (SELECT email FROM users WHERE id = ?)
        ORDER BY sn.shared_at DESC
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getNoteShares($conn, $note_id, $owner_id) {
    $stmt = $conn->prepare("
        SELECT sn.*, u.display_name 
        FROM shared_notes sn 
        LEFT JOIN users u ON sn.shared_with_email = u.email 
        WHERE sn.note_id = ? AND sn.owner_id = ?
        ORDER BY sn.shared_at DESC
    ");
    $stmt->bind_param("ii", $note_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getSharedWithMe($conn, $user_email) {
    $stmt = $conn->prepare("
        SELECT n.*, sn.permission, sn.shared_at, sn.owner_id,
               owner.email as owner_email, owner.display_name as owner_name
        FROM shared_notes sn
        JOIN notes n ON sn.note_id = n.id
        JOIN users owner ON n.user_id = owner.id
        WHERE sn.shared_with_email = ?
        ORDER BY sn.shared_at DESC
    ");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function hasNoteAccess($conn, $note_id, $user_id, $user_email) {
    // Check if user owns the note
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return true;
    }
    
    // Check if note is shared with user
    $stmt = $conn->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND shared_with_email = ?");
    $stmt->bind_param("is", $note_id, $user_email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function removeNoteShare($conn, $note_id, $shared_with_email, $owner_id) {
    $stmt = $conn->prepare("DELETE FROM shared_notes WHERE note_id = ? AND shared_with_email = ? AND owner_id = ?");
    $stmt->bind_param("isi", $note_id, $shared_with_email, $owner_id);
    return $stmt->execute();
}

function updateNoteSharePermission($conn, $note_id, $shared_with_email, $permission, $owner_id) {
    if (!in_array($permission, ['read', 'edit'])) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND shared_with_email = ? AND owner_id = ?");
    $stmt->bind_param("sisi", $permission, $note_id, $shared_with_email, $owner_id);
    return $stmt->execute();
}

function createNoteShare($conn, $note_id, $shared_with_email, $permission, $shared_by_id) {
    if (!in_array($permission, ['read', 'edit'])) {
        return false;
    }
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $shared_with_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false; // User doesn't exist
    }
    
    $user = $result->fetch_assoc();
    $shared_with_id = $user['id'];
    
    // Insert or update share
    $stmt = $conn->prepare("
        INSERT INTO shared_notes (note_id, owner_id, shared_with_email, shared_with_id, permission) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE permission = VALUES(permission), shared_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iisis", $note_id, $shared_by_id, $shared_with_email, $shared_with_id, $permission);
    return $stmt->execute();
}

function getSharingStats($conn, $user_id) {
    // Get notes shared by user
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sn.note_id) as shared_notes, 
               COUNT(sn.id) as total_shares
        FROM shared_notes sn 
        JOIN notes n ON sn.note_id = n.id 
        WHERE n.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $shared_by_me = $stmt->get_result()->fetch_assoc();
    
    // Get notes shared with user
    $stmt = $conn->prepare("
        SELECT COUNT(*) as shared_with_me 
        FROM shared_notes sn 
        JOIN users u ON sn.shared_with_email = u.email 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $shared_with_me = $stmt->get_result()->fetch_assoc();
    
    return [
        'shared_notes' => $shared_by_me['shared_notes'] ?? 0,
        'total_shares' => $shared_by_me['total_shares'] ?? 0,
        'shared_with_me' => $shared_with_me['shared_with_me'] ?? 0
    ];
}
?>