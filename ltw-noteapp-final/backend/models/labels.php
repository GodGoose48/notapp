<?php
function getLabelsByUser($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM labels WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getLabelIdsByNote($conn, $note_id) {
    $stmt = $conn->prepare("SELECT label_id FROM label_note WHERE note_id = ?");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $label_ids = [];
    while ($row = $result->fetch_assoc()) {
        $label_ids[] = $row['label_id'];
    }
    return $label_ids;
}

function syncLabelsToNote($conn, $note_id, $label_ids) {
    $conn->query("DELETE FROM label_note WHERE note_id = $note_id");
    foreach ($label_ids as $label_id) {
        $stmt = $conn->prepare("INSERT INTO label_note (label_id, note_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $label_id, $note_id);
        $stmt->execute();
    }
}
