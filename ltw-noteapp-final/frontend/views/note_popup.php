<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html");
  exit;
}

require_once '../../backend/models/db.php';
require_once '../../backend/models/notes.php';
require_once '../../backend/models/labels.php';
require_once '../../backend/models/preferences.php';

$user_id = $_SESSION['user_id'];
$note_id = $_GET['id'] ?? null;

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

// Check if note is password protected
$isPasswordProtected = false;
if ($note_id) {
  $note = getNoteById($conn, $note_id);
  if (!$note || $note['user_id'] != $user_id) {
    header("Location: home.php");
    exit;
  }

  // Check if note has password protection
  $isPasswordProtected = !empty($note['password_hash']);

  // Get note labels
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
} else {
  $note = null;
  $noteLabelIds = [];
}

$allLabels = getLabelsByUser($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $note ? htmlspecialchars($note['title']) : 'New Note' ?> - Nốt</title>
  <link rel="stylesheet" href="/ltw-noteapp-final/frontend/assets/css/new_note.css">
  <script src="/ltw-noteapp-final/frontend/assets/js/note_popup.js" defer></script>
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

    /* Password protection overlay */
    .password-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      backdrop-filter: blur(5px);
    }

    .password-prompt {
      background: white;
      padding: 40px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      max-width: 400px;
      width: 90%;
    }

    .password-prompt h2 {
      margin-top: 0;
      color: #333;
      margin-bottom: 20px;
    }

    .password-prompt .lock-icon {
      font-size: 48px;
      margin-bottom: 20px;
      color: #9b5e35;
    }

    .password-prompt input {
      width: 100%;
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      margin-bottom: 20px;
      box-sizing: border-box;
    }

    .password-prompt input:focus {
      outline: none;
      border-color: #9b5e35;
    }

    .password-prompt .button-group {
      display: flex;
      gap: 10px;
    }

    .password-prompt button {
      flex: 1;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .password-prompt .unlock-btn {
      background: #9b5e35;
      color: white;
    }

    .password-prompt .unlock-btn:hover {
      background: #7b4322;
    }

    .password-prompt .cancel-btn {
      background: #6c757d;
      color: white;
    }

    .password-prompt .cancel-btn:hover {
      background: #545b62;
    }

    .password-error {
      color: #dc3545;
      margin-bottom: 15px;
      padding: 8px;
      background: #f8d7da;
      border-radius: 4px;
      border: 1px solid #f5c6cb;
    }

    /* Hide content when password protected */
    .content-hidden {
      filter: blur(10px);
      pointer-events: none;
      user-select: none;
    }
  </style>
</head>

<body class="<?= $preferences['theme'] ?>-theme" data-preferences='<?= json_encode($preferences) ?>' data-password-protected="<?= $isPasswordProtected ? 'true' : 'false' ?>">
  
  <!-- Password protection overlay (shown only for protected notes) -->
  <?php if ($isPasswordProtected): ?>
  <div id="passwordOverlay" class="password-overlay">
    <div class="password-prompt">
      <div class="lock-icon">🔒</div>
      <h2>This note is password protected</h2>
      <form id="passwordForm">
        <div id="passwordError" class="password-error" style="display: none;"></div>
        <input type="password" id="passwordInput" placeholder="Enter password to unlock" required>
        <div class="button-group">
          <button type="submit" class="unlock-btn">🔓 Unlock</button>
          <button type="button" class="cancel-btn" onclick="window.location.href='home.php'">Cancel</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="container <?= $isPasswordProtected ? 'content-hidden' : '' ?>" id="mainContent">
    <!-- Sidebar -->
    <aside class="sidebar">
      <img src="/ltw-noteapp-final/frontend/assets/images/logo2.png" class="logo" alt="Nốt">
      <a href="home.php" class="nav-home">
        <img src="/ltw-noteapp-final/frontend/assets/images/icons/arrow-left.png" alt="Home"> Back to Home
      </a>
    </aside>

    <!-- Main content -->
    <main class="main">
      <?php if ($note): ?>
        <input type="hidden" id="note_id" value="<?= $note['id'] ?>">
      <?php endif; ?>

      <input type="text" id="title" class="note-title" placeholder="Note title..."
        value="<?= $note ? htmlspecialchars($note['title']) : '' ?>" <?= $isPasswordProtected ? 'disabled' : '' ?>>

      <!-- Toolbar -->
      <div class="toolbar">
        <button type="button" data-command="bold" <?= $isPasswordProtected ? 'disabled' : '' ?>><strong>B</strong></button>
        <button type="button" data-command="italic" <?= $isPasswordProtected ? 'disabled' : '' ?>><em>I</em></button>
        <button type="button" data-command="underline" <?= $isPasswordProtected ? 'disabled' : '' ?>><u>U</u></button>
      </div>

      <div id="content" class="note-textarea" contenteditable="<?= $isPasswordProtected ? 'false' : 'true' ?>" placeholder="Start writing your note...">
        <?= $note ? $note['content'] : '' ?>
      </div>

      <!-- Options section -->
      <div class="options">
        <?php if ($note): ?>
          <label>
            <input type="checkbox" id="is_pinned" <?= $note['is_pinned'] ? 'checked' : '' ?> <?= $isPasswordProtected ? 'disabled' : '' ?>>
            Pin this note
          </label>
        <?php endif; ?>

        <!-- Labels -->
        <div class="labels-section">
          <h4>Labels:</h4>
          <?php foreach ($allLabels as $label): ?>
            <label>
              <input type="checkbox" class="label-checkbox" value="<?= $label['id'] ?>" 
                     <?= in_array($label['id'], $noteLabelIds) ? 'checked' : '' ?>
                     <?= $isPasswordProtected ? 'disabled' : '' ?>>
              <?= htmlspecialchars($label['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="password-section">
          <h4>Password Protection:</h4>
          <?php if ($note): ?>
            <div class="password-controls">
              <button type="button" id="setPasswordBtn" class="password-btn" style="<?= $isPasswordProtected ? 'display: none;' : '' ?>">🔒 Set Password</button>
              <button type="button" id="changePasswordBtn" class="password-btn" style="<?= !$isPasswordProtected ? 'display: none;' : '' ?>">🔑 Change Password</button>
              <button type="button" id="disablePasswordBtn" class="password-btn disable" style="<?= !$isPasswordProtected ? 'display: none;' : '' ?>">🔓 Remove Password</button>
            </div>
          <?php else: ?>
            <p class="password-info">Save the note first to enable password protection</p>
          <?php endif; ?>
        </div>

        <!-- Sharing section -->
        <div class="sharing-section">
          <h4>Share Note:</h4>
          <?php if ($note): ?>
            <div class="sharing-controls">
              <button type="button" id="shareNoteBtn" class="password-btn">🌞 Share Note</button>
              <button type="button" id="manageSharingBtn" class="password-btn">⚙️ Manage Sharing</button>
            </div>
          <?php else: ?>
            <p class="password-info">Save the note first to enable sharing</p>
          <?php endif; ?>
        </div>

        <!-- Image upload -->
        <?php if ($note): ?>
          <div class="attach-section">
            <label for="image">Attach Images:</label>
            <input type="file" id="image" multiple accept="image/*" <?= $isPasswordProtected ? 'disabled' : '' ?>>
          </div>

          <div id="imageList" class="image-list"></div>

          <button type="button" id="deleteBtn" class="delete-btn" <?= $isPasswordProtected ? 'disabled' : '' ?>>Delete Note</button>
        <?php endif; ?>
      </div>

      <div id="statusMessage" class="status"></div>
    </main>
  </div>

  <!-- Lightbox for image viewing -->
  <div id="lightbox" style="display: none;">
    <span id="lightbox-close">&times;</span>
    <img id="lightbox-img" src="" alt="Full size image">
  </div>

  <!-- Sharing Modal -->
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
</body>

</html>