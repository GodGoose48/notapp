<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require_once '../../backend/models/db.php';
require_once '../../backend/models/notes.php';
require_once '../../backend/models/sharing.php';

$user_id = $_SESSION['user_id'];

// Get all notes owned by the user
$notes = getNotesByUser($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sharing - Nốt</title>
    <link rel="stylesheet" href="/ltw-noteapp-final/frontend/assets/css/home.css">
    <style>
        .sharing-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .note-sharing-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .note-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
        }

        .note-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .note-meta {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .sharing-content {
            padding: 20px;
        }

        .no-shares {
            color: #666;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }

        .share-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .share-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .share-item:last-child {
            border-bottom: none;
        }

        .share-user {
            flex: 1;
        }

        .share-email {
            font-weight: bold;
            color: #333;
        }

        .share-name {
            font-size: 12px;
            color: #666;
        }

        .share-date {
            font-size: 11px;
            color: #999;
        }

        .share-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .permission-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .permission-read {
            background: #e3f2fd;
            color: #1976d2;
        }

        .permission-edit {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-edit {
            background: #28a745;
            color: white;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
        }

        .btn-edit:hover {
            background: #218838;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .stats-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="sharing-container">
        <a href="home.php" class="back-link">
            <img src="/ltw-noteapp-final/frontend/assets/images/icons/arrow-left.png" alt="Back" width="16">
            Back to Home
        </a>

        <h1>Sharing Management</h1>

        <?php
        $totalNotes = count($notes);
        $sharedNotes = 0;
        $totalShares = 0;
        
        foreach ($notes as $note) {
            $shares = getNoteShares($conn, $note['id'], $user_id);
            if (!empty($shares)) {
                $sharedNotes++;
                $totalShares += count($shares);
            }
        }
        ?>

        <div class="stats-summary">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalNotes ?></div>
                    <div class="stat-label">Total Notes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $sharedNotes ?></div>
                    <div class="stat-label">Shared Notes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $totalShares ?></div>
                    <div class="stat-label">Total Shares</div>
                </div>
            </div>
        </div>

        <?php foreach ($notes as $note): ?>
            <?php
            $shares = getNoteShares($conn, $note['id'], $user_id);
            ?>
            <div class="note-sharing-card">
                <div class="note-header">
                    <h3 class="note-title"><?= htmlspecialchars($note['title']) ?></h3>
                    <div class="note-meta">
                        Created: <?= date('M j, Y \a\t g:i A', strtotime($note['created_at'])) ?> |
                        Last updated: <?= date('M j, Y \a\t g:i A', strtotime($note['updated_at'])) ?>
                    </div>
                </div>
                
                <div class="sharing-content">
                    <?php if (empty($shares)): ?>
                        <div class="no-shares">
                            This note is not shared with anyone yet.
                            <br>
                            <a href="note_popup.php?id=<?= $note['id'] ?>">Open note to start sharing</a>
                        </div>
                    <?php else: ?>
                        <ul class="share-list">
                            <?php foreach ($shares as $share): ?>
                                <li class="share-item">
                                    <div class="share-user">
                                        <div class="share-email"><?= htmlspecialchars($share['shared_with_email']) ?></div>
                                        <?php if ($share['display_name']): ?>
                                            <div class="share-name"><?= htmlspecialchars($share['display_name']) ?></div>
                                        <?php endif; ?>
                                        <div class="share-date">
                                            Shared on <?= date('M j, Y \a\t g:i A', strtotime($share['shared_at'])) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="share-controls">
                                        <span class="permission-badge permission-<?= $share['permission'] ?>">
                                            <?= $share['permission'] ?>
                                        </span>
                                        
                                        <button class="btn-small btn-edit" 
                                                onclick="togglePermission(<?= $note['id'] ?>, '<?= $share['shared_with_email'] ?>', '<?= $share['permission'] ?>')">
                                            Toggle
                                        </button>
                                        
                                        <button class="btn-small btn-remove" 
                                                onclick="removeShare(<?= $note['id'] ?>, '<?= $share['shared_with_email'] ?>')">
                                            Remove
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($notes)): ?>
            <div class="no-shares">
                <h3>No notes found</h3>
                <p>You haven't created any notes yet.</p>
                <a href="note_popup.php">Create your first note</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function togglePermission(noteId, email, currentPermission) {
            const newPermission = currentPermission === 'read' ? 'edit' : 'read';
            
            if (!confirm(`Change permission for ${email} to "${newPermission}"?`)) {
                return;
            }

            try {
                const response = await fetch('/ltw-noteapp-final/backend/api/update_share.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        note_id: noteId,
                        shared_with_email: email,
                        action: 'update',
                        permission: newPermission
                    })
                });

                const result = await response.json();

                if (response.ok) {
                    alert('Permission updated successfully!');
                    location.reload();
                } else {
                    alert(result.error || 'Failed to update permission');
                }
            } catch (error) {
                console.error('Error updating permission:', error);
                alert('Failed to update permission');
            }
        }

        async function removeShare(noteId, email) {
            if (!confirm(`Remove sharing access for ${email}?`)) {
                return;
            }

            try {
                const response = await fetch('/ltw-noteapp-final/backend/api/update_share.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        note_id: noteId,
                        shared_with_email: email,
                        action: 'remove'
                    })
                });

                const result = await response.json();

                if (response.ok) {
                    alert('Share removed successfully!');
                    location.reload();
                } else {
                    alert(result.error || 'Failed to remove share');
                }
            } catch (error) {
                console.error('Error removing share:', error);
                alert('Failed to remove share');
            }
        }
    </script>
</body>
</html>