<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require_once '../../backend/models/db.php';
require_once '../../backend/models/sharing.php';
require_once '../../backend/models/preferences.php';

$user_id = $_SESSION['user_id'];

// Get user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_email = $user_data['email'];

// Get all notes shared with this user
$sharedNotes = getSharedWithMe($conn, $user_email);

// Get user preferences
$preferences = getUserPreferences($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared With Me - Nốt</title>
    <link rel="stylesheet" href="/ltw-noteapp-final/frontend/assets/css/home.css">
    <style>
        .shared-note-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }

        .shared-note-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .shared-note-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .shared-note-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin: 0;
            flex: 1;
        }

        .shared-note-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .shared-by-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 14px;
        }

        .owner-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .permission-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
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

        .shared-date {
            color: #999;
            font-size: 13px;
        }

        .shared-note-content {
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }

        .shared-note-content.truncated::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(transparent, white);
        }

        .shared-note-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-open {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn-open:hover {
            background: #0056b3;
        }

        .btn-open.read-only {
            background: #6c757d;
        }

        .btn-open.read-only:hover {
            background: #545b62;
        }

        .collaboration-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #28a745;
        }

        .collaboration-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state img {
            width: 120px;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .stats-bar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
            display: block;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .filter-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
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
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px;">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <a href="home.php" class="back-link">
            <img src="/ltw-noteapp-final/frontend/assets/images/icons/arrow-left.png" alt="Back" width="16">
            Back to Home
        </a>

        <h1>📩 Shared With Me</h1>

        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-number"><?= count($sharedNotes) ?></span>
                <div class="stat-label">Total Shared Notes</div>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= count(array_filter($sharedNotes, fn($note) => $note['permission'] === 'edit')) ?></span>
                <div class="stat-label">Editable Notes</div>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= count(array_filter($sharedNotes, fn($note) => $note['permission'] === 'read')) ?></span>
                <div class="stat-label">Read-Only Notes</div>
            </div>
        </div>

        <div class="filter-tabs">
            <div class="filter-tab active" data-filter="all">All Notes</div>
            <div class="filter-tab" data-filter="edit">Editable</div>
            <div class="filter-tab" data-filter="read">Read-Only</div>
        </div>

        <div class="shared-notes-container">
            <?php if (empty($sharedNotes)): ?>
                <div class="empty-state">
                    <img src="/ltw-noteapp-final/frontend/assets/images/icons/empty-folder.png" alt="No shared notes">
                    <h3>No shared notes yet</h3>
                    <p>Notes that others share with you will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($sharedNotes as $note): ?>
                    <div class="shared-note-card" data-permission="<?= $note['permission'] ?>">
                        <div class="shared-note-header">
                            <h3 class="shared-note-title"><?= htmlspecialchars($note['title']) ?></h3>
                            <span class="permission-indicator permission-<?= $note['permission'] ?>">
                                <?= $note['permission'] === 'edit' ? '✏️ Editable' : '👁️ Read-Only' ?>
                            </span>
                        </div>

                        <div class="shared-note-meta">
                            <div class="shared-by-info">
                                <div class="owner-avatar">
                                    <?= strtoupper(substr($note['owner_name'] ?: $note['owner_email'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong>Shared by:</strong> 
                                    <?= htmlspecialchars($note['owner_name'] ?: $note['owner_email']) ?>
                                    <?php if ($note['owner_name']): ?>
                                        <small>(<?= htmlspecialchars($note['owner_email']) ?>)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="shared-date">
                                📅 Shared on <?= date('M j, Y \a\t g:i A', strtotime($note['shared_at'])) ?>
                            </div>
                        </div>

                        <div class="shared-note-content <?= strlen($note['content']) > 200 ? 'truncated' : '' ?>">
                            <?= htmlspecialchars(substr(strip_tags($note['content']), 0, 200)) ?>
                            <?= strlen($note['content']) > 200 ? '...' : '' ?>
                        </div>

                        <div class="shared-note-actions">
                            <a href="<?= $note['permission'] === 'edit' ? 'collaborative_note.php' : 'view_note.php' ?>?id=<?= $note['id'] ?>" 
                               class="btn-open <?= $note['permission'] === 'read' ? 'read-only' : '' ?>">
                                <?= $note['permission'] === 'edit' ? '✏️ Edit Note' : '👁️ View Note' ?>
                            </a>
                            
                            <?php if ($note['permission'] === 'edit'): ?>
                                <div class="collaboration-status" id="collab-status-<?= $note['id'] ?>">
                                    <span class="collaboration-indicator"></span>
                                    <span>Real-time editing enabled</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Update active tab
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Filter notes
                const filter = tab.dataset.filter;
                document.querySelectorAll('.shared-note-card').forEach(card => {
                    if (filter === 'all' || card.dataset.permission === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Check collaboration status for editable notes
        document.addEventListener('DOMContentLoaded', () => {
            const editableNotes = document.querySelectorAll('.shared-note-card[data-permission="edit"]');
            editableNotes.forEach(card => {
                const noteId = card.querySelector('.btn-open').href.split('id=')[1];
                checkCollaborationStatus(noteId);
            });
        });

        function checkCollaborationStatus(noteId) {
            // This will be used to check if other users are currently editing
            fetch(`/ltw-noteapp-final/backend/api/collaboration_status.php?note_id=${noteId}`)
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById(`collab-status-${noteId}`);
                    if (data.active_users > 0) {
                        statusEl.innerHTML = `
                            <span class="collaboration-indicator"></span>
                            <span>${data.active_users} user(s) currently editing</span>
                        `;
                    }
                })
                .catch(console.error);
        }
    </script>
</body>
</html>