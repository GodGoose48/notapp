<?php

function getNotesByUser($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT * FROM notes 
        WHERE user_id = ? 
        ORDER BY is_pinned DESC, updated_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function createNote($conn, $user_id, $title, $content, $is_pinned = 0, $image = null, $password = null) {
    $stmt = $conn->prepare("
        INSERT INTO notes (user_id, title, content, is_pinned, image, password) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $image = $image ?? null;
    $password = $password !== '' ? $password : null;
    $stmt->bind_param("isssss", $user_id, $title, $content, $is_pinned, $image, $password);
    $stmt->execute();
    return $conn->insert_id;
}

function deleteNote($conn, $note_id) {
    $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
}

function getNoteById($conn, $note_id) {
    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

function updateNote($conn, $id, $user_id, $title, $content, $is_pinned, $image_path = null, $password = null) {
    $is_pinned = (int)$is_pinned;

    if (!is_null($image_path)) {
        $stmt = $conn->prepare("
            UPDATE notes 
            SET title = ?, content = ?, is_pinned = ?, image = ?, password = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ssissii", $title, $content, $is_pinned, $image_path, $password, $id, $user_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE notes 
            SET title = ?, content = ?, is_pinned = ?, password = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ssissi", $title, $content, $is_pinned, $password, $id, $user_id);
    }

    $stmt->execute();
}

function getNotesByLabel($conn, $user_id, $label_id) {
    $stmt = $conn->prepare("
        SELECT n.* 
        FROM notes n
        JOIN label_note ln ON n.id = ln.note_id
        WHERE ln.label_id = ? AND n.user_id = ?
        ORDER BY is_pinned DESC, updated_at DESC
    ");
    $stmt->bind_param("ii", $label_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getNotesByAllLabels($conn, $user_id, $label_ids) {
    if (empty($label_ids)) return [];

    $placeholders = implode(',', array_fill(0, count($label_ids), '?'));
    $types = str_repeat('i', count($label_ids) + 1); // user_id + label_ids
    $params = array_merge([$user_id], $label_ids);
    $params[] = count($label_ids); // đếm nhãn cần khớp

    $sql = "
        SELECT n.*
        FROM notes n
        JOIN label_note ln ON n.id = ln.note_id
        WHERE n.user_id = ? AND ln.label_id IN ($placeholders)
        GROUP BY n.id
        HAVING COUNT(DISTINCT ln.label_id) = ?
        ORDER BY n.updated_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types . 'i', ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
