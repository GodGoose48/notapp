<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require_once '../../backend/models/db.php';
require_once '../../backend/models/sharing.php';
require_once '../../backend/models/preferences.php';

$user_id = $_SESSION['user_id'];
$note_id = $_GET['id'] ?? null;

if (!$note_id) {
    header("Location: shared_with_me.php");
    exit;
}

// Get user preferences
$preferences = getUserPreferences($conn, $user_id);

// Define note color mapping
$noteColors = [
    'default' => '#f8ddc2',
    'blue' => '#b3d9ff',
    'green' => '#c8e6c9',
    'yellow' => '#fff59d',
    'pink' => '#f8bbd9',
    'purple' => '#e1bee7',
    'orange' => '#ffcc80'
];
$selectedNoteColor = $noteColors[$preferences['note_color']] ?? $noteColors['default'];

// Get user email for checking shared access
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_email = $user_data['email'];

// Check if user owns the note OR has shared access
$stmt = $conn->prepare("
    SELECT n.*, u.email as owner_email, u.display_name as owner_name 
    FROM notes n 
    JOIN users u ON n.user_id = u.id 
    WHERE n.id = ? AND (n.user_id = ? OR EXISTS(
        SELECT 1 FROM shared_notes sn 
        WHERE sn.note_id = ? AND sn.shared_with_email = ?
    ))
");
$stmt->bind_param("iiss", $note_id, $user_id, $note_id, $user_email);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();

if (!$note) {
    $_SESSION['error_message'] = "Note not found or you don't have permission to access it.";
    header("Location: shared_with_me.php");
    exit;
}

// Get user's permission level
$permission = 'read'; // default
if ($note['user_id'] == $user_id) {
    $permission = 'edit'; // owner has full access
} else {
    // Get shared permission
    $stmt = $conn->prepare("
        SELECT permission FROM shared_notes 
        WHERE note_id = ? AND shared_with_email = ?
    ");
    $stmt->bind_param("is", $note_id, $user_email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $permission = $result['permission'];
    }
}

// Get note labels if user has edit access
$noteLabels = [];
$noteLabelIds = [];
if ($permission === 'edit') {
    $stmt = $conn->prepare("
        SELECT l.id, l.name 
        FROM labels l 
        JOIN label_note ln ON l.id = ln.label_id 
        WHERE ln.note_id = ?
    ");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $noteLabels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $noteLabelIds = array_column($noteLabels, 'id');
}

// Get all user labels for editing
$allLabels = [];
if ($permission === 'edit') {
    require_once '../../backend/models/labels.php';
    $allLabels = getLabelsByUser($conn, $user_id);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($note['title']) ?> - Shared Note</title>
    <link rel="stylesheet" href="/ltw-noteapp-final/frontend/assets/css/new_note.css">
    <style>
        /* Apply user preferences */
        :root {
            --user-font-size: <?= $preferences['font_size'] ?>px;
            --user-note-color: <?= $selectedNoteColor ?>;
            --auto-save-delay: <?= $preferences['auto_save_delay'] ?>ms;
        }

        .note-content,
        .note-textarea,
        [contenteditable="true"] {
            font-size: var(--user-font-size) !important;
            background-color: var(--user-note-color) !important;
        }

        .note-title {
            background-color: var(--user-note-color) !important;
            font-size: calc(var(--user-font-size) + 4px) !important;
        }

        /* Collaboration bar at the top */
        .collaboration-bar {
            background: linear-gradient(135deg, rgb(0, 9, 51) 0%, rgb(0, 0, 0) 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Adjust body to account for fixed header */
        body {
            padding-top: 70px;
        }

        .container {
            margin-top: 0;
        }

        .collaboration-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .owner-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .owner-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .permission-badge {
            padding: 4px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .active-users {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .collaboration-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4CAF50;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .read-only-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }

        .note-meta {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .user-indicator {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .user-indicator.editing {
            animation: pulse 1s infinite;
            border: 2px solid #fff;
            box-shadow: 0 0 10px rgba(0,123,255,0.5);
        }
        
        @keyframes pulse {
            0% { 
                transform: scale(1); 
                opacity: 1; 
            }
            50% { 
                transform: scale(1.1); 
                opacity: 0.8; 
            }
            100% { 
                transform: scale(1); 
                opacity: 1; 
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .collaboration-bar {
                flex-direction: column;
                gap: 10px;
                padding: 10px 15px;
            }
            
            body {
                padding-top: 90px;
            }
            
            .collaboration-info {
                gap: 10px;
            }
        }
    </style>
    <?php if ($permission === 'edit'): ?>
        <!-- Remove the note_popup.js script that was causing conflicts -->
        <!-- <script src="/ltw-noteapp-final/frontend/assets/js/note_popup.js" defer></script> -->
    <?php endif; ?>
</head>

<body class="<?= $preferences['theme'] ?>-theme" data-preferences='<?= json_encode($preferences) ?>' 
      data-note-id="<?= $note_id ?>" data-user-id="<?= $user_id ?>" 
      data-can-edit="<?= $permission === 'edit' ? 'true' : 'false' ?>">

    <!-- Collaboration Bar - Moved to top -->
    <div class="collaboration-bar">
        <div class="collaboration-info">
            <div class="owner-info">
                <div class="owner-avatar">
                    <?= strtoupper(substr($note['owner_name'] ?: $note['owner_email'], 0, 1)) ?>
                </div>
                <div>
                    <strong>Owner:</strong> <?= htmlspecialchars($note['owner_name'] ?: $note['owner_email']) ?>
                </div>
            </div>
            <span class="permission-badge">
                <?= $permission === 'edit' ? 'Can Edit' : 'Read Only' ?>
            </span>
        </div>

        <div class="active-users">
            <div class="collaboration-status">
                <span class="status-indicator"></span>
                <span id="connectionStatus">Loading...</span>
            </div>
            <div id="activeUsers"></div>
        </div>
    </div>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <img src="/ltw-noteapp-final/frontend/assets/images/logo2.png" class="logo" alt="Nốt">
            <a href="home.php" class="nav-home">
                <img src="/ltw-noteapp-final/frontend/assets/images/icons/arrow-left.png" alt="Back"> Back to Home
            </a>
        </aside>

        <!-- Main content -->
        <main class="main">
            <?php if ($permission !== 'edit'): ?>
                <div class="read-only-banner">
                    This note is shared with you in read-only mode. You can view but not edit the note.
                </div>
            <?php endif; ?>

            <div class="note-meta">
                <strong>Note Information:</strong><br>
                Created: <?= date('M j, Y \a\t g:i A', strtotime($note['created_at'])) ?><br>
                Last updated: <?= date('M j, Y \a\t g:i A', strtotime($note['updated_at'])) ?><br>
                Your permission: <?= ucfirst($permission) ?>
            </div>

            <?php if ($permission === 'edit'): ?>
                <!-- Hidden note ID for JavaScript -->
                <input type="hidden" id="note_id" value="<?= $note['id'] ?>">
            <?php endif; ?>

            <input type="text" id="title" class="note-title" placeholder="Note title..."
                value="<?= htmlspecialchars($note['title']) ?>" 
                <?= $permission !== 'edit' ? 'readonly' : '' ?>>

            <?php if ($permission === 'edit'): ?>
                <!-- Toolbar -->
                <div class="toolbar">
                    <button type="button" data-command="bold"><strong>B</strong></button>
                    <button type="button" data-command="italic"><em>I</em></button>
                    <button type="button" data-command="underline"><u>U</u></button>
                </div>
            <?php endif; ?>

            <div id="content" class="note-textarea" 
                 contenteditable="<?= $permission === 'edit' ? 'true' : 'false' ?>"
                 placeholder="<?= $permission === 'edit' ? 'Start writing...' : 'This note is read-only' ?>">
                <?= $note['content'] ?>
            </div>

            <?php if ($permission === 'edit'): ?>
                <!-- Options section -->
                <div class="options">
                    <!-- Labels -->
                    <?php if (!empty($allLabels)): ?>
                        <div class="labels-section">
                            <h4>Labels:</h4>
                            <?php foreach ($allLabels as $label): ?>
                                <label>
                                    <input type="checkbox" class="label-checkbox" value="<?= $label['id'] ?>" 
                                           <?= in_array($label['id'], $noteLabelIds) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($label['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Password Protection (only for owner) -->
                    <?php if ($note['user_id'] == $user_id): ?>
                        <div class="password-section">
                            <h4>Password Protection:</h4>
                            <div class="password-controls">
                                <button type="button" id="setPasswordBtn" class="password-btn" 
                                        style="<?= !empty($note['password_hash']) ? 'display: none;' : '' ?>">
                                    🔒 Set Password
                                </button>
                                <button type="button" id="changePasswordBtn" class="password-btn" 
                                        style="<?= empty($note['password_hash']) ? 'display: none;' : '' ?>">
                                    🔑 Change Password
                                </button>
                                <button type="button" id="disablePasswordBtn" class="password-btn disable" 
                                        style="<?= empty($note['password_hash']) ? 'display: none;' : '' ?>">
                                    🔓 Remove Password
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sharing section (only for owner) -->
                    <?php if ($note['user_id'] == $user_id): ?>
                        <div class="sharing-section">
                            <h4>Share Note:</h4>
                            <div class="sharing-controls">
                                <button type="button" id="shareNoteBtn" class="password-btn">🌞 Share Note</button>
                                <button type="button" id="manageSharingBtn" class="password-btn">⚙️ Manage Sharing</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Image upload -->
                    <div class="attach-section">
                        <label for="image">Attach Images:</label>
                        <input type="file" id="image" multiple accept="image/*">
                    </div>

                    <div id="imageList" class="image-list"></div>

                    <!-- Delete button (only for owner) -->
                    <?php if ($note['user_id'] == $user_id): ?>
                        <button type="button" id="deleteBtn" class="delete-btn">Delete Note</button>
                    <?php endif; ?>
                </div>

                <div id="statusMessage" class="status"></div>
            <?php else: ?>
                <!-- Show images for read-only users -->
                <div id="imageList" class="image-list"></div>
                
                <div class="read-only-banner" style="margin-top: 15px;">
                    Please contact the note's owner to request edit permissions!
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Lightbox for image viewing -->
    <div id="lightbox" style="display: none;">
        <span id="lightbox-close">&times;</span>
        <img id="lightbox-img" src="" alt="Full size image">
    </div>

    <!-- Sharing Modal (only for owners with edit permission) -->
    <?php if ($permission === 'edit' && $note['user_id'] == $user_id): ?>
        <div id="sharingModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Share Note</h3>
                    <span class="close" onclick="closeSharingModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="shareForm">
                        <div class="form-group">
                            <label for="shareEmails">Email addresses (one per line or comma-separated):</label>
                            <textarea id="shareEmails" placeholder="Enter email addresses..." required></textarea>
                            <small style="color: #666; font-size: 12px;">Enter multiple emails separated by commas or new lines</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sharePermission">Permission:</label>
                            <select id="sharePermission" required>
                                <option value="read">Read Only - Can view the note</option>
                                <option value="edit">Can Edit - Can modify the note</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="closeSharingModal()">Cancel</button>
                            <button type="submit">Share Note</button>
                        </div>
                    </form>
                    
                    <div id="shareResults" style="display: none;">
                        <h4>Sharing Results:</h4>
                        <div id="shareResultsContent"></div>
                    </div>

                    <!-- Current shares display -->
                    <div id="currentShares" class="current-shares">
                        <h4>Currently Shared With:</h4>
                        <div id="currentSharesList"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Collaboration and core functionality script -->
    <script src="/ltw-noteapp-final/frontend/assets/js/collaborative_note.js" defer></script>
</body>

</html>