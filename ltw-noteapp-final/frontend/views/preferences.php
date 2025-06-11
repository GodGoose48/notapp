<?php

session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html");
  exit;
}

require_once '../../backend/models/db.php';
require_once '../../backend/models/preferences.php';

$user_id = $_SESSION['user_id'];
$display_name = $_SESSION['display_name'];

// Get current preferences
$preferences = getUserPreferences($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Preferences - Nốt</title>
  <link rel="stylesheet" href="/ltw-noteapp-final/frontend/assets/css/preferences.css" />
  <script src="/ltw-noteapp-final/frontend/assets/js/preferences.js" defer></script>
</head>

<body class="<?= $preferences['theme'] ?? 'light' ?>-theme">
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <img src="/ltw-noteapp-final/frontend/assets/images/logo2.png" class="logo" alt="Nốt" />

      <a href="home.php" class="nav-home">
        <img src="/ltw-noteapp-final/frontend/assets/images/icons/home.png" alt="Home"> Back to Home
      </a>
    </aside>

    <!-- Main -->
    <main class="main">
      <div class="preferences-header">
        <h1>User Preferences</h1>
        <p>Customize your note-taking experience</p>
      </div>

      <div class="preferences-content">
        <form id="preferencesForm" method="POST" action="/ltw-noteapp-final/backend/api/update_preferences.php">

          <!-- Theme Section -->
          <div class="preference-section">
            <h3>Theme</h3>
            <div class="theme-options">
              <label class="theme-option">
                <input type="radio" name="theme" value="light" <?= ($preferences['theme'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                <div class="theme-preview light-preview">
                  <div class="theme-header"></div>
                  <div class="theme-content"></div>
                </div>
                <span>Light</span>
              </label>

              <label class="theme-option">
                <input type="radio" name="theme" value="dark" <?= ($preferences['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                <div class="theme-preview dark-preview">
                  <div class="theme-header"></div>
                  <div class="theme-content"></div>
                </div>
                <span>Dark</span>
              </label>
            </div>
          </div>

          <!-- Font Size Section -->
          <div class="preference-section">
            <h3>Font Size</h3>
            <div class="font-size-section">
              <label for="fontSize">Note content font size:</label>
              <div class="font-size-controls">
                <input type="range" id="fontSize" name="font_size" min="12" max="24"
                  value="<?= $preferences['font_size'] ?? 16 ?>" step="1">
                <span id="fontSizeDisplay"><?= $preferences['font_size'] ?? 16 ?>px</span>
              </div>
              <div class="font-preview" id="fontPreview">
                This is a preview of your note text with the selected font size.
              </div>
            </div>
          </div>

          <!-- Note Colors Section -->
          <div class="preference-section">
            <h3>Default Note Color</h3>
            <div class="color-options">
              <?php
              $colors = [
                'default' => '#f8ddc2',
                'blue' => '#b3d9ff',
                'green' => '#c8e6c9',
                'yellow' => '#fff59d',
                'pink' => '#f8bbd9',
                'purple' => '#e1bee7',
                'orange' => '#ffcc80'
              ];

              foreach ($colors as $name => $hex):
                ?>
                <label class="color-option">
                  <input type="radio" name="note_color" value="<?= $name ?>" <?= ($preferences['note_color'] ?? 'default') === $name ? 'checked' : '' ?>>
                  <div class="color-preview" style="background-color: <?= $hex ?>"></div>
                  <span><?= ucfirst($name) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Auto-save Settings -->
          <div class="preference-section">
            <h3>Auto-save</h3>
            <label class="checkbox-option">
              <input type="checkbox" name="auto_save" value="1" <?= ($preferences['auto_save'] ?? 1) ? 'checked' : '' ?>>
              Enable auto-save (saves notes automatically while typing)
            </label>

            <div class="auto-save-delay">
              <label for="autoSaveDelay">Auto-save delay:</label>
              <select name="auto_save_delay" id="autoSaveDelay">
                <option value="500" <?= ($preferences['auto_save_delay'] ?? 800) == 500 ? 'selected' : '' ?>>0.5 seconds
                </option>
                <option value="800" <?= ($preferences['auto_save_delay'] ?? 800) == 800 ? 'selected' : '' ?>>0.8 seconds
                </option>
                <option value="1000" <?= ($preferences['auto_save_delay'] ?? 800) == 1000 ? 'selected' : '' ?>>1 second
                </option>
                <option value="2000" <?= ($preferences['auto_save_delay'] ?? 800) == 2000 ? 'selected' : '' ?>>2 seconds
                </option>
              </select>
            </div>
          </div>

          <div class="save-section">
            <button type="submit" class="save-btn">Save Preferences</button>
            <div id="statusMessage" class="status-message"></div>
          </div>
        </form>
      </div>
    </main>
  </div>
</body>

</html>