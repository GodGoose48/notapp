const noteId = document.body.dataset.noteId;
const userId = document.body.dataset.userId;
const canEdit = document.body.dataset.canEdit === "true";
const connectionStatus = document.getElementById("connectionStatus");
const activeUsers = document.getElementById("activeUsers");
const statusMessage = document.getElementById("statusMessage");

// Note elements
const titleEl = document.getElementById("title");
const contentEl = document.getElementById("content");
const pinnedEl = document.getElementById("is_pinned");
const labelEls = document.querySelectorAll(".label-checkbox");
const imageEl = document.getElementById("image");
const deleteBtn = document.getElementById("deleteBtn");

let saveTimeout;
let lastUpdateTime = Date.now();
let isUpdating = false;

// Get auto-save preferences
let autoSaveDelay = 800;
const preferencesData = document.body.getAttribute("data-preferences");
if (preferencesData) {
  try {
    const preferences = JSON.parse(preferencesData);
    autoSaveDelay = preferences.auto_save_delay || 800;
    if (!preferences.auto_save) {
      autoSaveDelay = null;
    }
  } catch (error) {
    console.error("Error parsing preferences:", error);
  }
}

// Initialize
document.addEventListener("DOMContentLoaded", function () {
  connectionStatus.textContent = "Connected";

  if (canEdit) {
    markUserActive();
    setInterval(markUserActive, 30000);

    // Setup auto-save for editable users
    setupAutoSave();

    // Check for updates from other users
    setInterval(checkForUpdates, 3000);
  }

  // Check collaboration status
  checkCollaborationStatus();
  setInterval(checkCollaborationStatus, 10000);

  // Load images for all users
  loadImages();

  // Setup lightbox
  setupLightbox();

  // Setup toolbar
  setupToolbar();

  // Setup image upload
  setupImageUpload();

  // Setup delete button
  setupDeleteButton();

  // Setup password management
  setupPasswordManagement();

  // Setup sharing functionality
  setupSharingFunctionality();
});

// Setup auto-save functionality
function setupAutoSave() {
  if (!autoSaveDelay) return; // Auto-save disabled

  const debounce = (func, delay) => {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), delay);
    };
  };

  const debouncedSave = debounce(saveNote, autoSaveDelay);

  // Add event listeners
  if (titleEl) {
    titleEl.addEventListener("input", () => {
      if (!isUpdating) {
        lastUpdateTime = Date.now();
        debouncedSave();
      }
    });
  }

  if (contentEl) {
    contentEl.addEventListener("input", () => {
      if (!isUpdating) {
        lastUpdateTime = Date.now();
        debouncedSave();
      }
    });
  }

  if (pinnedEl) {
    pinnedEl.addEventListener("change", () => {
      if (!isUpdating) {
        lastUpdateTime = Date.now();
        debouncedSave();
      }
    });
  }

  labelEls.forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      if (!isUpdating) {
        lastUpdateTime = Date.now();
        debouncedSave();
      }
    });
  });
}

// Get selected labels
function getSelectedLabels() {
  return Array.from(labelEls)
    .filter((cb) => cb.checked)
    .map((cb) => cb.value);
}

// Save note function
async function saveNote() {
  if (!canEdit) return;

  try {
    const selectedLabels = getSelectedLabels();

    const formData = new FormData();
    formData.append("id", noteId);
    formData.append("title", titleEl ? titleEl.value : "");
    formData.append("content", contentEl ? contentEl.innerHTML : "");
    formData.append("labels", JSON.stringify(selectedLabels));
    formData.append("is_pinned", pinnedEl?.checked ? 1 : 0);
    formData.append("collaborative_save", "true"); // Flag for collaborative save

    const response = await fetch(
      "/ltw-noteapp-final/backend/api/update_note.php",
      {
        method: "POST",
        body: formData,
      }
    );

    if (response.ok) {
      const result = await response.json();
      if (result.success) {
        if (statusMessage) {
          statusMessage.textContent =
            "Saved at " + new Date().toLocaleTimeString();
          statusMessage.style.color = "#28a745";
        }

        // Update last update time
        if (result.updated_at) {
          lastUpdateTime = new Date(result.updated_at).getTime();
        }
      } else {
        throw new Error(result.error || "Save failed");
      }
    } else {
      throw new Error("Network error");
    }
  } catch (error) {
    console.error("Save error:", error);
    if (statusMessage) {
      statusMessage.textContent = "Error saving note: " + error.message;
      statusMessage.style.color = "#dc3545";
    }
  }
}

// Check for updates from other users
async function checkForUpdates() {
  if (!canEdit || isUpdating) return;

  try {
    const response = await fetch(
      `/ltw-noteapp-final/backend/api/get_note_updates.php?note_id=${noteId}&last_update=${lastUpdateTime}`
    );
    const data = await response.json();

    if (data.success && data.has_updates) {
      applyUpdates(data.note);
    }
  } catch (error) {
    console.error("Error checking for updates:", error);
  }
}

// Apply updates from other users
function applyUpdates(noteData) {
  isUpdating = true;

  try {
    // Update title if different
    if (titleEl && titleEl.value !== noteData.title) {
      const cursorPos = titleEl.selectionStart;
      titleEl.value = noteData.title;
      // Restore cursor position if possible
      if (cursorPos <= noteData.title.length) {
        titleEl.setSelectionRange(cursorPos, cursorPos);
      }
    }

    // Update content if different
    if (contentEl && contentEl.innerHTML !== noteData.content) {
      // Save cursor position in contentEditable
      const selection = window.getSelection();
      const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

      contentEl.innerHTML = noteData.content;

      // Try to restore cursor position
      if (range) {
        try {
          selection.removeAllRanges();
          selection.addRange(range);
        } catch (e) {
          // If restoration fails, place cursor at end
          const newRange = document.createRange();
          newRange.selectNodeContents(contentEl);
          newRange.collapse(false);
          selection.removeAllRanges();
          selection.addRange(newRange);
        }
      }
    }

    // Update pinned status
    if (pinnedEl) {
      pinnedEl.checked = Boolean(noteData.is_pinned);
    }

    // Update labels
    if (noteData.labels) {
      labelEls.forEach((checkbox) => {
        checkbox.checked = noteData.labels.includes(parseInt(checkbox.value));
      });
    }

    // Update last update time
    lastUpdateTime = new Date(noteData.updated_at).getTime();

    // Show update notification
    if (statusMessage) {
      statusMessage.textContent =
        "Updated by another user at " + new Date().toLocaleTimeString();
      statusMessage.style.color = "#007bff";
      setTimeout(() => {
        if (statusMessage.textContent.includes("Updated by another user")) {
          statusMessage.textContent = "";
          statusMessage.style.color = "";
        }
      }, 3000);
    }
  } finally {
    isUpdating = false;
  }
}

// Setup toolbar
function setupToolbar() {
  document.querySelectorAll(".toolbar button").forEach((button) => {
    button.addEventListener("click", () => {
      if (!canEdit) return;
      document.execCommand(button.dataset.command);
      button.classList.toggle("active");
    });
  });
}

// Setup image upload
function setupImageUpload() {
  if (imageEl && noteId && canEdit) {
    imageEl.addEventListener("change", () => {
      const formData = new FormData();
      formData.append("note_id", noteId);
      for (let i = 0; i < imageEl.files.length; i++) {
        formData.append("images[]", imageEl.files[i]);
      }

      fetch("/ltw-noteapp-final/backend/api/upload_image.php", {
        method: "POST",
        body: formData,
      })
        .then(() => {
          loadImages(); // Reload images instead of full page reload
          imageEl.value = ""; // Clear the input
        })
        .catch((error) => {
          console.error("Error uploading images:", error);
        });
    });
  }
}

// Setup delete button
function setupDeleteButton() {
  if (deleteBtn && canEdit) {
    deleteBtn.addEventListener("click", () => {
      if (!confirm("Are you sure you want to delete this note?")) return;

      fetch("/ltw-noteapp-final/backend/api/delete_note.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `note_id=${noteId}`,
      }).then(() => {
        window.location.href = "home.php";
      });
    });
  }
}

// Setup password management
function setupPasswordManagement() {
  const setPasswordBtn = document.getElementById("setPasswordBtn");
  const changePasswordBtn = document.getElementById("changePasswordBtn");
  const disablePasswordBtn = document.getElementById("disablePasswordBtn");

  if (setPasswordBtn) {
    setPasswordBtn.addEventListener("click", () => {
      // Redirect to note_popup.php for password management
      window.location.href = `note_popup.php?id=${noteId}`;
    });
  }

  if (changePasswordBtn) {
    changePasswordBtn.addEventListener("click", () => {
      window.location.href = `note_popup.php?id=${noteId}`;
    });
  }

  if (disablePasswordBtn) {
    disablePasswordBtn.addEventListener("click", () => {
      window.location.href = `note_popup.php?id=${noteId}`;
    });
  }
}

// Setup sharing functionality
function setupSharingFunctionality() {
  const shareNoteBtn = document.getElementById("shareNoteBtn");
  const manageSharingBtn = document.getElementById("manageSharingBtn");

  if (shareNoteBtn) {
    shareNoteBtn.addEventListener("click", () => {
      openSharingModal(noteId);
    });
  }

  if (manageSharingBtn) {
    manageSharingBtn.addEventListener("click", () => {
      window.location.href = "sharing_management.php";
    });
  }
}

async function markUserActive() {
  try {
    await fetch("/ltw-noteapp-final/backend/api/mark_user_active.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ note_id: noteId, user_id: userId }),
    });
  } catch (error) {
    console.error("Error marking user active:", error);
  }
}

async function checkCollaborationStatus() {
  try {
    const response = await fetch(
      `/ltw-noteapp-final/backend/api/collaboration_status.php?note_id=${noteId}`
    );
    const data = await response.json();

    if (data.success) {
      updateCollaborationStatus(data);
    }
  } catch (error) {
    console.error("Error checking collaboration status:", error);
    connectionStatus.textContent = "Connection issues";
  }
}

function updateCollaborationStatus(data) {
  const otherUsers = (data.active_users_list || []).filter(
    (user) => user.id != userId
  );

  if (otherUsers.length > 0) {
    connectionStatus.textContent = `Connected (${otherUsers.length} other user${
      otherUsers.length > 1 ? "s" : ""
    })`;

    activeUsers.innerHTML = otherUsers
      .map(
        (user) =>
          `<div class="user-indicator" title="${
            user.name || user.email
          }" style="
                    background: ${getUserColor(user.id)};
                ">
                    ${(user.name || user.email).charAt(0).toUpperCase()}
                </div>`
      )
      .join("");
  } else {
    connectionStatus.textContent = "Connected";
    activeUsers.innerHTML = "";
  }
}

function getUserColor(userId) {
  const colors = [
    "#FF6B6B",
    "#4ECDC4",
    "#45B7D1",
    "#96CEB4",
    "#FECA57",
    "#FF9FF3",
    "#54A0FF",
  ];
  return colors[userId % colors.length];
}

// Load images
function loadImages() {
  fetch(`/ltw-noteapp-final/backend/api/get_images.php?note_id=${noteId}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const container = document.getElementById("imageList");
        if (container) {
          container.innerHTML = "";
          data.images.forEach((img) => {
            const wrapper = document.createElement("div");
            wrapper.className = "image-wrapper";

            const image = document.createElement("img");
            image.src = `/ltw-noteapp-final/uploads/${img.filename}`;
            image.style.cursor = "pointer";
            image.onclick = () => {
              const lightboxImg = document.getElementById("lightbox-img");
              const lightbox = document.getElementById("lightbox");
              if (lightboxImg && lightbox) {
                lightboxImg.src = image.src;
                lightbox.style.display = "flex";
              }
            };

            wrapper.appendChild(image);

            // Add delete button only for users with edit permission
            if (canEdit) {
              const btn = document.createElement("button");
              btn.textContent = "🗑";
              btn.onclick = () => {
                if (confirm("Delete this image?")) {
                  fetch("/ltw-noteapp-final/backend/api/delete_image.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `image_id=${img.id}`,
                  }).then(() => wrapper.remove());
                }
              };
              wrapper.appendChild(btn);
            }

            container.appendChild(wrapper);
          });
        }
      }
    });
}

// Setup lightbox
function setupLightbox() {
  const lightbox = document.getElementById("lightbox");
  const lightboxClose = document.getElementById("lightbox-close");
  if (lightbox && lightboxClose) {
    lightboxClose.onclick = () => {
      lightbox.style.display = "none";
    };
  }
}

// Cleanup on page unload
window.addEventListener("beforeunload", function () {
  if (canEdit) {
    navigator.sendBeacon(
      "/ltw-noteapp-final/backend/api/mark_user_inactive.php",
      JSON.stringify({ note_id: noteId, user_id: userId })
    );
  }
});

// Sharing Modal Functions (copy from note_popup.js)
let currentNoteId = null;

function openSharingModal(noteId) {
  currentNoteId = noteId;
  document.getElementById("sharingModal").style.display = "block";
  document.getElementById("shareResults").style.display = "none";
  document.getElementById("shareForm").reset();

  // Load current shares
  loadCurrentShares(noteId);
}

function closeSharingModal() {
  document.getElementById("sharingModal").style.display = "none";
  currentNoteId = null;
}

// Load current shares for the note
async function loadCurrentShares(noteId) {
  try {
    const response = await fetch(
      `/ltw-noteapp-final/backend/api/get_note_shares.php?note_id=${noteId}`
    );
    const data = await response.json();

    const sharesList = document.getElementById("currentSharesList");

    if (data.success && data.shares.length > 0) {
      sharesList.innerHTML = "";
      data.shares.forEach((share) => {
        const shareItem = createShareItem(share);
        sharesList.appendChild(shareItem);
      });
    } else {
      sharesList.innerHTML =
        '<div class="no-shares">This note is not currently shared with anyone.</div>';
    }
  } catch (error) {
    console.error("Error loading current shares:", error);
    document.getElementById("currentSharesList").innerHTML =
      '<div class="no-shares">Error loading sharing information.</div>';
  }
}

// Create share item element
function createShareItem(share) {
  const div = document.createElement("div");
  div.className = "share-item";

  div.innerHTML = `
    <div class="share-user-info">
      <div class="share-email">${escapeHtml(share.shared_with_email)}</div>
      ${
        share.display_name
          ? `<div class="share-name">${escapeHtml(share.display_name)}</div>`
          : ""
      }
      <div class="share-date">Shared on ${formatDate(share.shared_at)}</div>
    </div>
    <div class="share-controls">
      <span class="permission-badge permission-${share.permission}">
        ${share.permission}
      </span>
      <button class="btn-small btn-edit" onclick="togglePermission('${share.shared_with_email}', '${share.permission}')">
        Toggle
      </button>
      <button class="btn-small btn-remove" onclick="removeShare('${share.shared_with_email}')">
        Remove
      </button>
    </div>
  `;

  return div;
}

// Handle share form submission
document.addEventListener("DOMContentLoaded", () => {
  const shareForm = document.getElementById("shareForm");
  if (shareForm) {
    shareForm.addEventListener("submit", async function (e) {
      e.preventDefault();

      if (!currentNoteId) {
        alert("No note selected");
        return;
      }

      const emailText = document.getElementById("shareEmails").value.trim();
      const permission = document.getElementById("sharePermission").value;

      if (!emailText) {
        alert("Please enter at least one email address");
        return;
      }

      // Parse emails (split by newlines or commas)
      const emails = emailText
        .split(/[,\n]/)
        .map((email) => email.trim())
        .filter((email) => email.length > 0);

      if (emails.length === 0) {
        alert("Please enter valid email addresses");
        return;
      }

      // Show loading state
      const submitBtn = shareForm.querySelector("button[type='submit']");
      const originalText = submitBtn.textContent;
      submitBtn.textContent = "Sharing...";
      submitBtn.disabled = true;

      try {
        const response = await fetch("/ltw-noteapp-final/backend/api/share_note.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            note_id: currentNoteId,
            emails: emails,
            permission: permission,
          }),
        });

        const result = await response.json();

        if (response.ok) {
          displayShareResults(result);
          // Reload current shares
          loadCurrentShares(currentNoteId);
        } else {
          alert(result.error || "Failed to share note");
        }
      } catch (error) {
        console.error("Error sharing note:", error);
        alert("Failed to share note. Please check your connection.");
      } finally {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
    });
  }
});

function displayShareResults(result) {
  const resultsDiv = document.getElementById("shareResultsContent");
  let html = "";

  if (result.successful_shares.length > 0) {
    html +=
      '<h5 style="color: #28a745; margin-bottom: 10px;">✅ Successfully shared with:</h5>';
    result.successful_shares.forEach((share) => {
      html += `<div class="share-success">✓ ${escapeHtml(share.email)} (${share.action})</div>`;
    });
  }

  if (result.failed_shares.length > 0) {
    html +=
      '<h5 style="color: #dc3545; margin-bottom: 10px;">❌ Failed to share with:</h5>';
    result.failed_shares.forEach((share) => {
      html += `<div class="share-error">✗ ${escapeHtml(share.email)}: ${escapeHtml(
        share.reason
      )}</div>`;
    });
  }

  resultsDiv.innerHTML = html;
  document.getElementById("shareResults").style.display = "block";

  // Clear the form
  document.getElementById("shareForm").reset();

  // Auto-hide results after 5 seconds if all successful
  if (result.failed_shares.length === 0) {
    setTimeout(() => {
      const resultsEl = document.getElementById("shareResults");
      if (resultsEl) {
        resultsEl.style.display = "none";
      }
    }, 5000);
  }
}

// Toggle permission for a share
async function togglePermission(email, currentPermission) {
  const newPermission = currentPermission === "read" ? "edit" : "read";

  if (!confirm(`Change permission for ${email} to "${newPermission}"?`)) {
    return;
  }

  try {
    const response = await fetch("/ltw-noteapp-final/backend/api/update_share.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        note_id: currentNoteId,
        shared_with_email: email,
        action: "update",
        permission: newPermission,
      }),
    });

    const result = await response.json();

    if (response.ok) {
      alert("Permission updated successfully!");
      loadCurrentShares(currentNoteId); // Reload shares
    } else {
      alert(result.error || "Failed to update permission");
    }
  } catch (error) {
    console.error("Error updating permission:", error);
    alert("Failed to update permission");
  }
}

// Remove a share
async function removeShare(email) {
  if (!confirm(`Remove sharing access for ${email}?`)) {
    return;
  }

  try {
    const response = await fetch("/ltw-noteapp-final/backend/api/update_share.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        note_id: currentNoteId,
        shared_with_email: email,
        action: "remove",
      }),
    });

    const result = await response.json();

    if (response.ok) {
      alert("Share removed successfully!");
      loadCurrentShares(currentNoteId); // Reload shares
    } else {
      alert(result.error || "Failed to remove share");
    }
  } catch (error) {
    console.error("Error removing share:", error);
    alert("Failed to remove share");
  }
}

// Close modal when clicking outside
window.onclick = function (event) {
  const modal = document.getElementById("sharingModal");
  if (event.target === modal) {
    closeSharingModal();
  }
};

// Utility functions
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return (
    date.toLocaleDateString() +
    " at " +
    date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
  );
}
