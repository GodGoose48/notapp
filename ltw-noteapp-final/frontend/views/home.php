<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html");
  exit;
}
require_once '../../backend/models/db.php';
require_once '../../backend/models/labels.php';
require_once '../../backend/models/notes.php';
require_once '../../backend/models/preferences.php';
require_once '../../backend/models/sharing.php';

$user_id = $_SESSION['user_id'];
$display_name = $_SESSION['display_name'];

// Check if user is verified
$stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$is_verified = $user['is_verified'] ?? 0;

$labels = getLabelsByUser($conn, $user_id);

// Get user preferences
$preferences = getUserPreferences($conn, $user_id);

// Get shared notes
$shared_notes = getSharedNotes($conn, $user_id);

$label_ids = $_GET['label_ids'] ?? [];
$label_ids = array_map('intval', (array) $label_ids);
$search = trim($_GET['search'] ?? '');

if (!empty($label_ids)) {
  $notes = getNotesByAllLabels($conn, $user_id, $label_ids);
} else {
  $notes = getNotesByUser($conn, $user_id);
}

if (!empty($search)) {
  $notes = array_filter(
    $notes,
    fn($note) =>
    stripos($note['title'], $search) !== false
  );
}

usort($notes, fn($a, $b) => strtotime($b['updated_at']) - strtotime($a['updated_at']));
$pinned_notes = array_filter($notes, fn($note) => $note['is_pinned']);

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Home - Nốt</title>
  <link rel="stylesheet" href="/ltw-noteapp-final/frontend/assets/css/home.css" />
  <script src="/ltw-noteapp-final/frontend/assets/js/home.js" defer></script>
  <style>
    :root {
      --user-font-size:
        <?= $preferences['font_size'] ?>
        px;
      --user-note-color:
        <?= $selectedNoteColor ?>
      ;
    }

    .note-card {
      background-color: var(--user-note-color) !important;
      font-size: var(--user-font-size);
    }

    .note-content,
    .note-textarea {
      font-size: var(--user-font-size);
    }

    .verification-banner {
      background-color: #fff3cd;
      border: 1px solid #ffeaa7;
      color: #856404;
      padding: 12px 20px;
      margin-bottom: 20px;
      border-radius: 5px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .verification-banner .close-btn {
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: #856404;
    }

    .shared-note-card {
      position: relative;
      border-left: 4px solid #007bff;
    }

    .shared-note-info {
      font-size: 12px;
      color: #666;
      margin-top: 5px;
    }

    .permission-badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .permission-read {
      background-color: #e3f2fd;
      color: #1976d2;
    }

    .permission-edit {
      background-color: #e8f5e8;
      color: #2e7d32;
    }
  </style>
</head>

<body class="<?= $preferences['theme'] ?>-theme" data-preferences='<?= json_encode($preferences) ?>'>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <img src="/ltw-noteapp-final/frontend/assets/images/logo2.png" class="logo" alt="Nốt" />
      <form method="GET" action="" class="search-bar">
        <img src="/ltw-noteapp-final/frontend/assets/images/icons/search.png" class="icon" alt="Search">
        <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" />
        <?php foreach ($label_ids as $id): ?>
          <input type="hidden" name="label_ids[]" value="<?= $id ?>">
        <?php endforeach; ?>
      </form>

      <a href="home.php" class="nav-home">
        <img src="/ltw-noteapp-final/frontend/assets/images/icons/home.png" alt="Home"> Home
      </a>

      <a href="note_popup.php" class="btn-new-note">+ New Note</a>

      <!-- Shared Notes Section -->
      <button class="nav-btn toggle-btn" data-target="sharedNotesList">
        <span><img src="/ltw-noteapp-final/frontend/assets/images/icons/share.png"> Shared with me</span> ▼
      </button>
      <ul id="sharedNotesList" class="sub-list">
        <?php foreach ($shared_notes as $shared_note): ?>
          <li>
            <a href="collaborative_note.php?id=<?= $shared_note['id'] ?>">
              <div>
                <span><?= htmlspecialchars($shared_note['title']) ?></span>
                <span class="permission-badge permission-<?= $shared_note['permission'] ?>">
                  <?= $shared_note['permission'] ?>
                </span>
              </div>
              <small>by <?= htmlspecialchars($shared_note['owner_name']) ?></small>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <button class="nav-btn toggle-btn" data-target="labelList">
        <span><img src="/ltw-noteapp-final/frontend/assets/images/icons/label.png"> Labels</span> ▼
      </button>
      <ul id="labelList" class="sub-list">
        <?php foreach ($labels as $label): ?>
          <li>
            <form method="GET" action="" style="flex: 1; display: flex; align-items: center; gap: 6px;">
              <input type="checkbox" name="label_ids[]" value="<?= $label['id'] ?>" <?= in_array($label['id'], $label_ids) ? 'checked' : '' ?> onchange="this.form.submit();">
              <span class="label-link"><?= htmlspecialchars($label['name']) ?></span>
              <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
              <?php foreach ($label_ids as $id) {
                if ($id != $label['id']) {
                  echo "<input type='hidden' name='label_ids[]' value='$id'>";
                }
              } ?>
            </form>
            <form method="POST" action="/ltw-noteapp-final/backend/api/delete_label.php">
              <input type="hidden" name="label_id" value="<?= $label['id'] ?>">
              <button type="submit" class="delete-btn">
                <img src="/ltw-noteapp-final/frontend/assets/images/icons/delete.png" alt="Delete" />
              </button>
            </form>
          </li>
        <?php endforeach; ?>
        <li>
          <form class="label-form" method="POST" action="/ltw-noteapp-final/backend/api/create_label.php">
            <input type="text" name="label_name" placeholder="New label..." required />
            <button type="submit">Add</button>
          </form>
        </li>
      </ul>

      <button class="nav-btn toggle-btn" data-target="noteList">
        <span><img src="/ltw-noteapp-final/frontend/assets/images/icons/notes.png"> Notes</span> ▼
      </button>
      <ul id="noteList" class="sub-list">
        <?php foreach ($notes as $note): ?>
          <li>
            <a href="note_popup.php?id=<?= $note['id'] ?>">
              <?= htmlspecialchars($note['title']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <!-- Main -->
    <main class="main">
      <?php if (!$is_verified): ?>
        <div class="verification-banner" id="verificationBanner">
          <div class="banner-content">
            <span class="banner-icon">⚠️</span>
            <span class="banner-text">
              <strong>Account not verified!</strong> Please check your email and click the verification link to activate
              your account and access all features.
            </span>
            <button id="resendVerification" class="resend-btn">Resend Email</button>
          </div>
        </div>
      <?php endif; ?>

      <div class="user-section">
        <div class="user-name">
          <h1>Hello <?= htmlspecialchars($display_name) ?>!</h1>
          <p>Think it. Note it. Do it.</p>
        </div>

        <div class="dropdown-container">
          <?php
          $avatarPath = "../../uploads/avatar_" . $user_id . ".png";
          $avatarSrc = file_exists($avatarPath)
            ? "/ltw-noteapp-final/uploads/avatar_$user_id.png"
            : "/ltw-noteapp-final/frontend/assets/images/icons/user.png";
          ?>
          <img src="<?= $avatarSrc ?>" class="user-icon" alt="User" onclick="toggleDropdown()">
          <div id="dropdownMenu" class="dropdown-menu">
            <span class="dropdown-link" onclick="viewAvatar()">View Avatar</span>
            <form id="avatarForm" enctype="multipart/form-data" method="POST"
              action="/ltw-noteapp-final/backend/api/upload_avatar.php">
              <label class="dropdown-link">
                Upload Avatar
                <input type="file" name="avatar" onchange="document.getElementById('avatarForm').submit()"
                  style="display:none">
              </label>
            </form>
            <a href="/ltw-noteapp-final/backend/api/delete_avatar.php">Remove Avatar</a>
            <a href="/ltw-noteapp-final/frontend/views/sharing_management.php">Manage Sharing</a>
            <a href="/ltw-noteapp-final/frontend/views/preferences.php">Preferences</a>
            <a href="/ltw-noteapp-final/frontend/views/change_password.html">Change Password</a>
            <a href="/ltw-noteapp-final/frontend/views/login.html">Logout</a>
          </div>
        </div>
      </div>

      <!-- Shared Notes Section -->
      <?php if (!empty($shared_notes)): ?>
        <div class="note-section">
          <p><img src="/ltw-noteapp-final/frontend/assets/images/icons/share.png" class="icon"> Shared with me</p>
          <div class="note-container grid-view">
            <?php foreach ($shared_notes as $shared_note): ?>
              <div class="note-card shared-note-card">
                <a href="collaborative_note.php?id=<?= $shared_note['id'] ?>">
                  <div>
                    <strong><?= htmlspecialchars($shared_note['title']) ?></strong>
                    <div class="shared-note-info">
                      <span class="permission-badge permission-<?= $shared_note['permission'] ?>">
                        <?= $shared_note['permission'] ?>
                      </span>
                      <br>
                      Shared by: <?= htmlspecialchars($shared_note['owner_name']) ?>
                      <br>
                      <small><?= date('H:i d/m/Y', strtotime($shared_note['shared_at'])) ?></small>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Pinned Notes -->
      <div class="note-section">
        <p><img src="/ltw-noteapp-final/frontend/assets/images/icons/pin.png" class="icon"> Pinned</p>
        <div class="note-container grid-view">
          <?php foreach ($pinned_notes as $note): ?>
            <div class="note-card">
              <a href="note_popup.php?id=<?= $note['id'] ?>">
                <strong><?= htmlspecialchars($note['title']) ?></strong>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Recently visited -->
      <div class="note-section">
        <p><img src="/ltw-noteapp-final/frontend/assets/images/icons/time.png" class="icon"> Recently visited</p>
        <div class="note-container grid-view">
          <?php foreach ($notes as $note): ?>
            <div class="note-card">
              <a href="note_popup.php?id=<?= $note['id'] ?>">
                <div>
                  <strong><?= htmlspecialchars($note['title']) ?></strong><br>
                  <small style="font-weight: normal; color: #555;">
                    <?= date('H:i d/m/Y', strtotime($note['updated_at'])) ?>
                  </small>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- View toggle -->
      <div class="view-toggle">
        <button class="view-btn active" data-view="grid">Grid</button>
        <button class="view-btn" data-view="list">List</button>
      </div>
    </main>
  </div>

  <script>
    // Check for preference updates every 500ms
    setInterval(() => {
      const storedPrefs = localStorage.getItem('userPreferences');
      const currentPrefs = document.body.getAttribute('data-preferences');

      if (storedPrefs && storedPrefs !== currentPrefs) {
        try {
          const preferences = JSON.parse(storedPrefs);
          applyPreferences(preferences);
          document.body.setAttribute('data-preferences', storedPrefs);
        } catch (error) {
          console.error('Error applying preferences:', error);
        }
      }
    }, 500);
  </script>
</body>

</html>