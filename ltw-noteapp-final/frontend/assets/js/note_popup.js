document.addEventListener('DOMContentLoaded', () => {
  const titleEl = document.getElementById('title');
  const contentEl = document.getElementById('content');
  const imageEl = document.getElementById('image');
  const pinnedEl = document.getElementById('is_pinned');
  const passwordEl = document.getElementById('password');
  const labelEls = document.querySelectorAll('.label-checkbox');
  const deleteBtn = document.getElementById('deleteBtn');
  const statusEl = document.getElementById('statusMessage');
  const noteId = document.getElementById('note_id')?.value || null;

  // Real-time sync variables
  let lastUpdateTime = Date.now();
  let isUpdating = false;
  let isSharedNote = false; // Flag to track if this is a shared note

  // Password protection state
  let isPasswordProtected = document.body.getAttribute('data-password-protected') === 'true';
  let hasValidatedPassword = false;
  let currentPasswordHash = null;

  // Password overlay elements
  const passwordOverlay = document.getElementById('passwordOverlay');
  const passwordForm = document.getElementById('passwordForm');
  const passwordInput = document.getElementById('passwordInput');
  const passwordError = document.getElementById('passwordError');
  const mainContent = document.getElementById('mainContent');

  // Load user preferences
  loadUserPreferences();

  // Handle initial password protection - this handles the built-in overlay
  if (isPasswordProtected && passwordForm && passwordInput) {
    initializePasswordProtection();
  }

  // Check if this is a shared note and start real-time sync only for shared notes
  if (noteId) {
    checkIfSharedNote().then(() => {
      if (isSharedNote) {
        console.log('This is a shared note - enabling real-time sync');
        setInterval(checkForUpdates, 3000);
      } else {
        console.log('This is a personal note - real-time sync disabled');
      }
    });
  }

  // Check if the current note is shared with other users
  async function checkIfSharedNote() {
    if (!noteId) {
      isSharedNote = false;
      return;
    }

    try {
      const response = await fetch(`/ltw-noteapp-final/backend/api/check_note_sharing_status.php?note_id=${noteId}`);
      const data = await response.json();
      
      if (data.success) {
        isSharedNote = data.is_shared || data.has_collaborators;
        console.log('Note sharing status:', data);
      } else {
        isSharedNote = false;
      }
    } catch (error) {
      console.error('Error checking note sharing status:', error);
      isSharedNote = false;
    }
  }

  // Initialize password protection for protected notes
  function initializePasswordProtection() {
    // Focus on password input
    passwordInput.focus();

    // Handle password form submission
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const password = passwordInput.value.trim();

      if (!password) {
        showPasswordError('Please enter a password');
        return;
      }

      // Show loading state
      const submitBtn = passwordForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'Verifying...';
      submitBtn.disabled = true;

      try {
        const response = await fetch('/ltw-noteapp-final/backend/api/verify_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `note_id=${noteId}&password=${encodeURIComponent(password)}`
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          // Password correct - unlock note
          hasValidatedPassword = true;
          unlockNote();
        } else {
          showPasswordError(data.message || 'Incorrect password');
          passwordInput.value = '';
          passwordInput.focus();
        }
      } catch (error) {
        console.error('Password verification error:', error);
        showPasswordError('Network error. Please check your connection and try again.');
      } finally {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
    });

    // Clear error on input
    passwordInput.addEventListener('input', () => {
      clearPasswordError();
    });
  }

  function showPasswordError(message) {
    if (passwordError) {
      passwordError.textContent = message;
      passwordError.style.display = 'block';
    }
    if (passwordInput) {
      passwordInput.style.borderColor = '#dc3545';
      passwordInput.classList.add('error');
    }
  }

  function clearPasswordError() {
    if (passwordError) {
      passwordError.style.display = 'none';
      passwordError.textContent = '';
    }
    if (passwordInput) {
      passwordInput.style.borderColor = '#ddd';
      passwordInput.classList.remove('error');
    }
  }

  function unlockNote() {
    // Hide password overlay
    if (passwordOverlay) {
      passwordOverlay.style.display = 'none';
    }
    
    // Show and enable main content
    if (mainContent) {
      mainContent.classList.remove('content-hidden');
    }
    
    // Enable all form elements
    const formElements = mainContent.querySelectorAll('input, button, [contenteditable]');
    formElements.forEach(el => {
      el.disabled = false;
      if (el.hasAttribute('contenteditable')) {
        el.setAttribute('contenteditable', 'true');
      }
    });

    // Update body attribute - but keep password protection state true since it's still protected
    document.body.setAttribute('data-password-unlocked', 'true');
    // Note: Don't set data-password-protected to false here, as the note is still protected
    
    // Update password button visibility - this is crucial
    updatePasswordButtons();
    
    // Show status message
    if (statusEl) {
      statusEl.textContent = 'Note unlocked successfully';
    }
  }

  // Get auto-save delay from preferences
  let autoSaveDelay = 800; // default
  const preferencesData = document.body.getAttribute('data-preferences');
  if (preferencesData) {
    try {
      const preferences = JSON.parse(preferencesData);
      autoSaveDelay = preferences.auto_save_delay || 800;
      
      // Check if auto-save is disabled
      if (!preferences.auto_save) {
        autoSaveDelay = null; // Disable auto-save
      }
    } catch (error) {
      console.error('Error parsing preferences:', error);
    }
  }

  const debounce = (func, delay) => {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      if (delay) {
        timeout = setTimeout(() => func.apply(this, args), delay);
      }
    };
  };

  function getSelectedLabels() {
    return Array.from(labelEls)
      .filter(cb => cb.checked)
      .map(cb => cb.value);
  }

  // Check for updates from other users (only for shared notes)
  async function checkForUpdates() {
    if (!noteId || isUpdating || !isSharedNote || isPasswordProtected && !hasValidatedPassword) return;

    try {
      const response = await fetch(`/ltw-noteapp-final/backend/api/get_note_updates.php?note_id=${noteId}&last_update=${lastUpdateTime}`);
      const data = await response.json();

      if (data.success && data.has_updates) {
        applyUpdates(data.note);
      }
    } catch (error) {
      console.error('Error checking for updates:', error);
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
        labelEls.forEach(labelEl => {
          labelEl.checked = noteData.labels.includes(parseInt(labelEl.value));
        });
      }

      // Update last update time
      lastUpdateTime = new Date(noteData.updated_at).getTime();

      // Show update notification
      if (statusEl) {
        statusEl.textContent = 'Updated by collaborator at ' + new Date().toLocaleTimeString();
        statusEl.style.color = '#007bff';
        setTimeout(() => {
          if (statusEl.textContent.includes('Updated by collaborator')) {
            statusEl.textContent = '';
            statusEl.style.color = '';
          }
        }, 3000);
      }

    } finally {
      isUpdating = false;
    }
  }

  // Show password dialog
  function showPasswordDialog(mode, callback = null) {
    const dialogHtml = createPasswordDialog(mode);
    document.body.insertAdjacentHTML('beforeend', dialogHtml);
    
    const dialog = document.getElementById('passwordDialog');
    const form = dialog.querySelector('form');
    const cancelBtn = dialog.querySelector('.cancel-btn');
    
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      handlePasswordSubmit(mode, form, dialog, callback);
    });

    // Handle cancel button
    if (cancelBtn) {
      cancelBtn.addEventListener('click', () => {
        closePasswordDialog(dialog);
      });
    }

    // Close dialog on backdrop click
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) {
        closePasswordDialog(dialog);
      }
    });

    // Focus first input
    const firstInput = dialog.querySelector('input');
    if (firstInput) {
      firstInput.focus();
    }
  }

  // Enhanced password dialog creation for disable mode
  function createPasswordDialog(mode) {
    let title, fields, buttons;

    switch (mode) {
      case 'verify':
        title = 'Enter Note Password';
        fields = '<input type="password" id="currentPassword" placeholder="Enter password" required>';
        buttons = '<button type="submit">Unlock</button><button type="button" class="cancel-btn">Cancel</button>';
        break;
      
      case 'create':
        title = 'Set Note Password';
        fields = `
          <input type="password" id="newPassword" placeholder="Enter new password (min 4 characters)" required>
          <input type="password" id="confirmPassword" placeholder="Confirm password" required>
        `;
        buttons = '<button type="submit">Set Password</button><button type="button" class="cancel-btn">Cancel</button>';
        break;
      
      case 'change':
        title = 'Change Note Password';
        // Skip current password field if already unlocked
        if (hasValidatedPassword) {
          fields = `
            <input type="password" id="newPassword" placeholder="New password (min 4 characters)" required>
            <input type="password" id="confirmPassword" placeholder="Confirm new password" required>
          `;
        } else {
          fields = `
            <input type="password" id="currentPassword" placeholder="Current password" required>
            <input type="password" id="newPassword" placeholder="New password (min 4 characters)" required>
            <input type="password" id="confirmPassword" placeholder="Confirm new password" required>
          `;
        }
        buttons = '<button type="submit">Change Password</button><button type="button" class="cancel-btn">Cancel</button>';
        break;
      
      case 'disable':
        title = 'Disable Password Protection';
        // Skip current password field if already unlocked
        if (hasValidatedPassword) {
          fields = `
            <div style="margin-bottom: 20px;">
              <p style="margin-bottom: 15px; color: #666; font-weight: 500;">⚠️ Confirm Action</p>
              <p style="margin-bottom: 15px; color: #666;">Are you sure you want to disable password protection for this note?</p>
              <p style="font-size: 14px; color: #999; background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 3px solid #ffc107;">
                <strong>Warning:</strong> This action will make the note accessible without a password. Anyone with access to your account will be able to view this note.
              </p>
            </div>
          `;
        } else {
          fields = `
            <p style="margin-bottom: 15px; color: #666;">Enter your current password to disable protection:</p>
            <input type="password" id="currentPassword" placeholder="Enter current password" required>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">This will remove password protection from this note.</p>
          `;
        }
        buttons = '<button type="submit" class="disable-btn" style="background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Disable Protection</button><button type="button" class="cancel-btn" style="margin-left: 10px;">Cancel</button>';
        break;
    }

    return `
      <div id="passwordDialog" class="password-dialog-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;">
        <div class="password-dialog" style="background: white; padding: 24px; border-radius: 8px; max-width: 400px; width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
          <h3 style="margin: 0 0 20px 0; color: #333;">${title}</h3>
          <form>
            ${fields}
            <div class="dialog-buttons" style="margin-top: 20px; text-align: right;">
              ${buttons}
            </div>
          </form>
        </div>
      </div>
    `;
  }

  // Handle password form submission
  async function handlePasswordSubmit(mode, form, dialog, callback) {
    const currentPassword = form.querySelector('#currentPassword')?.value;
    const newPassword = form.querySelector('#newPassword')?.value;
    const confirmPassword = form.querySelector('#confirmPassword')?.value;

    // Clear any existing errors
    const existingError = form.querySelector('.error-message');
    if (existingError) {
      existingError.remove();
    }

    try {
      switch (mode) {
        case 'verify':
          if (!currentPassword) {
            showError('Please enter a password', form);
            return;
          }
          await verifyPassword(currentPassword, dialog, callback);
          break;
        
        case 'create':
          if (!newPassword || !confirmPassword) {
            showError('Please fill in all fields', form);
            return;
          }
          if (newPassword !== confirmPassword) {
            showError('Passwords do not match', form);
            return;
          }
          if (newPassword.length < 4) {
            showError('Password must be at least 4 characters', form);
            return;
          }
          await createPassword(newPassword, dialog, callback);
          break;
        
        case 'change':
          // Validate required fields based on current state
          if (!hasValidatedPassword && !currentPassword) {
            showError('Please enter your current password', form);
            return;
          }
          if (!newPassword || !confirmPassword) {
            showError('Please fill in all password fields', form);
            return;
          }
          if (newPassword !== confirmPassword) {
            showError('New passwords do not match', form);
            return;
          }
          if (newPassword.length < 4) {
            showError('Password must be at least 4 characters', form);
            return;
          }
          
          await changePassword(currentPassword, newPassword, dialog, callback);
          break;
        
        case 'disable':

          if (!hasValidatedPassword && !currentPassword) {
            showError('Please enter your current password', form);
            return;
          }
          await disablePassword(currentPassword, dialog, callback);
          break;
      }
    } catch (error) {
      console.error('Password operation error:', error);
      showError('An unexpected error occurred. Please try again.', form);
    }
  }

  // Verify password
  async function verifyPassword(password, dialog, callback) {
    const form = dialog.querySelector('form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    try {
      submitBtn.textContent = 'Verifying...';
      submitBtn.disabled = true;

      const response = await fetch('/ltw-noteapp-final/backend/api/verify_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${noteId}&password=${encodeURIComponent(password)}`
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        hasValidatedPassword = true;
        closePasswordDialog(dialog);
        enableNoteAccess();
        
        // Ensure password buttons are updated after verification
        updatePasswordButtons();
        
        if (callback) callback();
      } else {
        showError(data.message || 'Incorrect password', form);
      }
    } catch (error) {
      console.error('Verify password error:', error);
      showError('Network error. Please check your connection.', form);
    } finally {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    }
  }

  // Enhanced error/success message display
  function showMessage(message, form, type = 'error') {
    // Remove existing messages
    const existingMessage = form.querySelector('.error-message, .success-message');
    if (existingMessage) {
      existingMessage.remove();
    }

    const messageEl = document.createElement('div');
    messageEl.className = type === 'error' ? 'error-message' : 'success-message';
    messageEl.textContent = message;
    
    form.insertBefore(messageEl, form.querySelector('.dialog-buttons'));
    
    // Auto-remove success messages after 3 seconds
    if (type === 'success') {
      setTimeout(() => {
        if (messageEl.parentNode) {
          messageEl.remove();
        }
      }, 3000);
    }
  }

  // Update showError to use showMessage
  function showError(message, form) {
    showMessage(message, form, 'error');
  }

  // Add showSuccess function
  function showSuccess(message, form) {
    showMessage(message, form, 'success');
  }

  // Create password
  async function createPassword(password, dialog, callback) {
    const form = dialog.querySelector('form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    try {
      submitBtn.textContent = 'Setting...';
      submitBtn.disabled = true;

      const response = await fetch('/ltw-noteapp-final/backend/api/set_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${noteId}&password=${encodeURIComponent(password)}`
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        isPasswordProtected = true;
        hasValidatedPassword = true;
        currentPasswordHash = data.password_hash;
        showSuccess('Password protection enabled successfully!', form);
        
        setTimeout(() => {
          closePasswordDialog(dialog);
          if (statusEl) statusEl.textContent = 'Password protection enabled';
          updatePasswordButtons();
          document.body.setAttribute('data-password-protected', 'true');
        }, 1500);
        
        if (callback) callback();
      } else {
        showError(data.message || 'Failed to set password', form);
      }
    } catch (error) {
      console.error('Create password error:', error);
      showError('Network error occurred. Please try again.', form);
    } finally {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    }
  }

  // Change password
  async function changePassword(currentPassword, newPassword, dialog, callback) {
    const form = dialog.querySelector('form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    try {
      submitBtn.textContent = 'Changing...';
      submitBtn.disabled = true;

      // Prepare request body - skip current password if already validated
      let body;
      if (hasValidatedPassword) {
        body = `note_id=${encodeURIComponent(noteId)}&new_password=${encodeURIComponent(newPassword)}&skip_current_password=true`;
      } else {
        body = `note_id=${encodeURIComponent(noteId)}&current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}`;
      }

      console.log('Change password request body:', body); // Debug logging

      const response = await fetch('/ltw-noteapp-final/backend/api/change_password.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json'
        },
        body: body
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        currentPasswordHash = data.password_hash;
        showSuccess('Password changed successfully!', form);
        
        setTimeout(() => {
          closePasswordDialog(dialog);
          if (statusEl) statusEl.textContent = 'Password changed successfully';
          
          // Keep the password protection state but ensure user remains validated
          hasValidatedPassword = true;
          updatePasswordButtons();
        }, 1500);
        
        if (callback) callback();
      } else {
        showError(data.message || 'Failed to change password', form);
      }
    } catch (error) {
      console.error('Change password error:', error);
      
      // Provide more specific error messages
      if (error.message.includes('HTTP error')) {
        showError('Server error occurred. Please try again.', form);
      } else if (error.message.includes('Failed to fetch')) {
        showError('Network connection error. Please check your internet connection.', form);
      } else {
        showError('Network error occurred. Please try again.', form);
      }
    } finally {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    }
  }

  // Disable password protection - Enhanced version
  async function disablePassword(currentPassword, dialog, callback) {
    const form = dialog.querySelector('form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    try {
      submitBtn.textContent = 'Disabling...';
      submitBtn.disabled = true;

      // Prepare request body - skip current password if already validated
      let body;
      if (hasValidatedPassword) {
        body = `note_id=${encodeURIComponent(noteId)}&skip_current_password=true`;
      } else {
        body = `note_id=${encodeURIComponent(noteId)}&password=${encodeURIComponent(currentPassword || '')}`;
      }

      console.log('Disable password request body:', body); // Debug logging

      const response = await fetch('/ltw-noteapp-final/backend/api/disable_password.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json'
        },
        body: body
      });

      console.log('Disable password response status:', response.status); // Debug logging

      if (!response.ok) {
        const errorText = await response.text();
        console.error('HTTP error response:', errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const responseText = await response.text();
      console.log('Raw response:', responseText); // Debug logging
      
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        console.error('JSON parse error:', parseError);
        console.error('Response text:', responseText);
        throw new Error('Invalid JSON response from server');
      }
      
      if (data.success) {
        // Update local state
        isPasswordProtected = false;
        hasValidatedPassword = false;
        currentPasswordHash = null;
        
        showSuccess('Password protection disabled successfully!', form);
        
        setTimeout(() => {
          closePasswordDialog(dialog);
          
          // Update UI elements
          if (statusEl) statusEl.textContent = 'Password protection disabled';
          updatePasswordButtons();
          document.body.setAttribute('data-password-protected', 'false');
          document.body.removeAttribute('data-password-unlocked');
          
          // Ensure note remains accessible
          enableNoteAccess();
          
          // Reload the page to ensure all states are properly reset
          setTimeout(() => {
            window.location.reload();
          }, 1000);
          
        }, 1500);
        
        if (callback) callback();
      } else {
        showError(data.message || 'Failed to disable password protection', form);
      }
    } catch (error) {
      console.error('Disable password error:', error);
      
      // Provide more specific error messages
      if (error.message.includes('HTTP error')) {
        showError('Server error occurred. Please try again.', form);
      } else if (error.message.includes('JSON')) {
        showError('Invalid response from server. Please try again.', form);
      } else if (error.message.includes('Failed to fetch')) {
        showError('Network connection error. Please check your internet connection.', form);
      } else {
        showError('Network error occurred. Please try again.', form);
      }
    } finally {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    }
  }

  // Close password dialog
  function closePasswordDialog(dialog) {
    if (dialog && dialog.parentNode) {
      dialog.remove();
    }
  }

  // Function to update password button visibility
  function updatePasswordButtons() {
    const setPasswordBtn = document.getElementById('setPasswordBtn');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const disablePasswordBtn = document.getElementById('disablePasswordBtn');

    console.log('Updating password buttons - isPasswordProtected:', isPasswordProtected, 'hasValidatedPassword:', hasValidatedPassword);

    if (isPasswordProtected) {
      // Note is password protected
      if (setPasswordBtn) {
        setPasswordBtn.style.display = 'none';
      }
      if (changePasswordBtn) {
        changePasswordBtn.style.display = 'inline-flex';
      }
      if (disablePasswordBtn) {
        disablePasswordBtn.style.display = 'inline-flex';
      }
    } else {
      // Note is not password protected
      if (setPasswordBtn) {
        setPasswordBtn.style.display = 'inline-flex';
      }
      if (changePasswordBtn) {
        changePasswordBtn.style.display = 'none';
      }
      if (disablePasswordBtn) {
        disablePasswordBtn.style.display = 'none';
      }
    }
  }

  // Enhanced enable note access function
  function enableNoteAccess() {
    // Remove disabled state from form elements
    const formElements = [titleEl, contentEl, pinnedEl, passwordEl, imageEl, ...labelEls];
    formElements.forEach(el => {
      if (el) el.disabled = false;
    });
    
    // Show delete button if hidden
    if (deleteBtn) deleteBtn.style.display = 'block';
    
    // Ensure password management buttons are visible and properly configured
    updatePasswordButtons();
  }

  // Disable note access for password-protected notes
  function disableNoteAccess() {
    const formElements = [titleEl, contentEl, pinnedEl, passwordEl, imageEl, ...labelEls];
    formElements.forEach(el => {
      if (el) el.disabled = true;
    });
    
    if (deleteBtn) deleteBtn.style.display = 'none';
  }

  // Check if action is allowed (for password-protected notes)
  function isActionAllowed() {
    if (isPasswordProtected && !hasValidatedPassword) {
      showPasswordDialog('verify');
      return false;
    }
    return true;
  }

  function saveNote() {
    if (!isActionAllowed()) return;

    const formData = new FormData();
    formData.append('title', titleEl.value);
    formData.append('content', contentEl.innerHTML);
    formData.append('labels', JSON.stringify(getSelectedLabels()));
    formData.append('is_pinned', pinnedEl?.checked ? 1 : 0);

    if (noteId) {
      formData.append('id', noteId);
      // Only add collaborative_save flag for shared notes
      if (isSharedNote) {
        formData.append('collaborative_save', 'true');
      }
      
      fetch('/ltw-noteapp-final/backend/api/update_note.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (statusEl) {
              statusEl.textContent = 'Saved at ' + new Date().toLocaleTimeString();
              statusEl.style.color = '#28a745';
            }
            
            // Update last update time for sync (only for shared notes)
            if (data.updated_at && isSharedNote) {
              lastUpdateTime = new Date(data.updated_at).getTime();
            }
          } else {
            if (statusEl) {
              statusEl.textContent = 'Error saving note: ' + (data.error || 'Unknown error');
              statusEl.style.color = '#dc3545';
            }
          }
        })
        .catch(error => {
          console.error('Save error:', error);
          if (statusEl) {
            statusEl.textContent = 'Error saving note';
            statusEl.style.color = '#dc3545';
          }
        });
    } else {
      fetch('/ltw-noteapp-final/backend/api/create_note.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success && data.note_id) {
            location.href = `note_popup.php?id=${data.note_id}`;
          } else {
            if (statusEl) statusEl.textContent = 'Error saving.';
          }
        })
        .catch(error => {
          console.error('Create error:', error);
          if (statusEl) statusEl.textContent = 'Error creating note';
        });
    }
  }

  // Create debounced save function only if auto-save is enabled
  const debouncedSave = autoSaveDelay ? debounce(() => {
    if (!isUpdating) {
      if (isSharedNote) {
        lastUpdateTime = Date.now();
      }
      saveNote();
    }
  }, autoSaveDelay) : null;

  // Add event listeners based on auto-save preference
  if (debouncedSave) {
    if (titleEl) titleEl.addEventListener('input', debouncedSave);
    if (contentEl) contentEl.addEventListener('input', debouncedSave);
    if (pinnedEl) pinnedEl.addEventListener('change', debouncedSave);
    labelEls.forEach(cb => cb.addEventListener('change', debouncedSave));
  } else {
    // Add manual save button or save on blur events
    if (titleEl) titleEl.addEventListener('blur', saveNote);
    if (contentEl) contentEl.addEventListener('blur', saveNote);
    if (pinnedEl) pinnedEl.addEventListener('change', saveNote);
    labelEls.forEach(cb => cb.addEventListener('change', saveNote));
  }

  // Upload images
  if (imageEl && noteId) {
    imageEl.addEventListener('change', () => {
      if (!isActionAllowed()) return;

      const formData = new FormData();
      formData.append('note_id', noteId);
      for (let i = 0; i < imageEl.files.length; i++) {
        formData.append('images[]', imageEl.files[i]);
      }

      fetch('/ltw-noteapp-final/backend/api/upload_image.php', {
        method: 'POST',
        body: formData
      }).then(() => location.reload());
    });
  }

  // Delete note
  if (deleteBtn) {
    deleteBtn.addEventListener('click', () => {
      if (!isActionAllowed()) return;
      
      if (!confirm('Are you sure you want to delete this note?')) return;
      fetch('/ltw-noteapp-final/backend/api/delete_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${noteId}`
      }).then(() => window.location.href = 'home.php');
    });
  }

  // Load attached images
  if (noteId) {
    fetch(`/ltw-noteapp-final/backend/api/get_images.php?note_id=${noteId}`)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const container = document.getElementById('imageList');
          if (container) {
            data.images.forEach(img => {
              const wrapper = document.createElement('div');
              wrapper.className = 'image-wrapper';

              const image = document.createElement('img');
              image.src = `/ltw-noteapp-final/uploads/${img.filename}`;
              image.style.cursor = 'pointer';
              image.onclick = () => {
                if (!isActionAllowed()) return;
                const lightboxImg = document.getElementById('lightbox-img');
                const lightbox = document.getElementById('lightbox');
                if (lightboxImg && lightbox) {
                  lightboxImg.src = image.src;
                  lightbox.style.display = 'flex';
                }
              };

              const btn = document.createElement('button');
              btn.textContent = '🗑';
              btn.onclick = () => {
                if (!isActionAllowed()) return;
                if (confirm('Delete this image?')) {
                  fetch('/ltw-noteapp-final/backend/api/delete_image.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `image_id=${img.id}`
                  }).then(() => wrapper.remove());
                }
              };

              wrapper.appendChild(image);
              wrapper.appendChild(btn);
              container.appendChild(wrapper);
            });
          }
        }
      });
  }

  // Lightbox close
  const lightbox = document.getElementById('lightbox');
  const lightboxClose = document.getElementById('lightbox-close');
  if (lightbox && lightboxClose) {
    lightboxClose.onclick = () => {
      lightbox.style.display = 'none';
    };
  }

  // Toolbar buttons (bold, italic, underline)
  document.querySelectorAll('.toolbar button').forEach(button => {
    button.addEventListener('click', () => {
      if (!isActionAllowed()) return;
      document.execCommand(button.dataset.command);
      button.classList.toggle('active');
    });
  });

  // Password management buttons
  const setPasswordBtn = document.getElementById('setPasswordBtn');
  const changePasswordBtn = document.getElementById('changePasswordBtn');
  const disablePasswordBtn = document.getElementById('disablePasswordBtn');

  // Sharing buttons
  const shareNoteBtn = document.getElementById('shareNoteBtn');
  const manageSharingBtn = document.getElementById('manageSharingBtn');

  if (setPasswordBtn) {
    setPasswordBtn.addEventListener('click', () => {
      if (!noteId) {
        if (statusEl) statusEl.textContent = 'Please save the note first';
        return;
      }
      showPasswordDialog('create');
    });
  }

  if (changePasswordBtn) {
    changePasswordBtn.addEventListener('click', () => {
      if (!isPasswordProtected) {
        if (statusEl) statusEl.textContent = 'Note is not password protected';
        return;
      }
      showPasswordDialog('change');
    });
  }

  if (disablePasswordBtn) {
    disablePasswordBtn.addEventListener('click', () => {
      if (!noteId) {
        if (statusEl) statusEl.textContent = 'Please save the note first';
        return;
      }
      
      if (!isPasswordProtected) {
        if (statusEl) statusEl.textContent = 'Note is not password protected';
        return;
      }
      
      showPasswordDialog('disable');
    });
  }

  // Sharing functionality
  if (shareNoteBtn) {
    shareNoteBtn.addEventListener('click', () => {
      if (!noteId) {
        if (statusEl) statusEl.textContent = 'Please save the note first';
        return;
      }
      if (!isActionAllowed()) return;
      openSharingModal(noteId);
    });
  }

  if (manageSharingBtn) {
    manageSharingBtn.addEventListener('click', () => {
      if (!noteId) {
        if (statusEl) statusEl.textContent = 'Please save the note first';
        return;
      }
      window.location.href = `sharing_management.php`;
    });
  }

  // Initially disable access if password protected and not validated
  if (isPasswordProtected && !hasValidatedPassword) {
    disableNoteAccess();
    // Hide password management buttons initially
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const disablePasswordBtn = document.getElementById('disablePasswordBtn');
    if (changePasswordBtn) changePasswordBtn.style.display = 'none';
    if (disablePasswordBtn) disablePasswordBtn.style.display = 'none';
  } else {
    // Update password button visibility for unlocked or unprotected notes
    if (noteId) {
      updatePasswordButtons();
    }
  }
});

// Sharing Modal Functions
let currentNoteId = null;

function openSharingModal(noteId) {
  currentNoteId = noteId;
  document.getElementById('sharingModal').style.display = 'block';
  document.getElementById('shareResults').style.display = 'none';
  document.getElementById('shareForm').reset();
  
  // Load current shares
  loadCurrentShares(noteId);
}

function closeSharingModal() {
  document.getElementById('sharingModal').style.display = 'none';
  currentNoteId = null;
}

// Load current shares for the note
async function loadCurrentShares(noteId) {
  try {
    const response = await fetch(`/ltw-noteapp-final/backend/api/get_note_shares.php?note_id=${noteId}`);
    const data = await response.json();
    
    const sharesList = document.getElementById('currentSharesList');
    
    if (data.success && data.shares.length > 0) {
      sharesList.innerHTML = '';
      data.shares.forEach(share => {
        const shareItem = createShareItem(share);
        sharesList.appendChild(shareItem);
      });
    } else {
      sharesList.innerHTML = '<div class="no-shares">This note is not currently shared with anyone.</div>';
    }
  } catch (error) {
    console.error('Error loading current shares:', error);
    document.getElementById('currentSharesList').innerHTML = '<div class="no-shares">Error loading sharing information.</div>';
  }
}

// Create share item element
function createShareItem(share) {
  const div = document.createElement('div');
  div.className = 'share-item';
  
  div.innerHTML = `
    <div class="share-user-info">
      <div class="share-email">${escapeHtml(share.shared_with_email)}</div>
      ${share.display_name ? `<div class="share-name">${escapeHtml(share.display_name)}</div>` : ''}
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
document.addEventListener('DOMContentLoaded', () => {
  const shareForm = document.getElementById('shareForm');
  if (shareForm) {
    shareForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      if (!currentNoteId) {
        alert('No note selected');
        return;
      }
      
      const emailText = document.getElementById('shareEmails').value.trim();
      const permission = document.getElementById('sharePermission').value;
      
      if (!emailText) {
        alert('Please enter at least one email address');
        return;
      }
      
      // Parse emails (split by newlines or commas)
      const emails = emailText.split(/[,\n]/)
        .map(email => email.trim())
        .filter(email => email.length > 0);
      
      if (emails.length === 0) {
        alert('Please enter valid email addresses');
        return;
      }
      
      // Show loading state
      const submitBtn = shareForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'Sharing...';
      submitBtn.disabled = true;
      
      try {
        const response = await fetch('/ltw-noteapp-final/backend/api/share_note.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            note_id: currentNoteId,
            emails: emails,
            permission: permission
          })
        });
        
        const result = await response.json();
        
        if (response.ok) {
          displayShareResults(result);
          // Reload current shares
          loadCurrentShares(currentNoteId);
        } else {
          alert(result.error || 'Failed to share note');
        }
      } catch (error) {
        console.error('Error sharing note:', error);
        alert('Failed to share note. Please check your connection.');
      } finally {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
    });
  }
});

function displayShareResults(result) {
  const resultsDiv = document.getElementById('shareResultsContent');
  let html = '';
  
  if (result.successful_shares.length > 0) {
    html += '<h5 style="color: #28a745; margin-bottom: 10px;">✅ Successfully shared with:</h5>';
    result.successful_shares.forEach(share => {
      html += `<div class="share-success">✓ ${escapeHtml(share.email)} (${share.action})</div>`;
    });
  }
  
  if (result.failed_shares.length > 0) {
    html += '<h5 style="color: #dc3545; margin-bottom: 10px;">❌ Failed to share with:</h5>';
    result.failed_shares.forEach(share => {
      html += `<div class="share-error">✗ ${escapeHtml(share.email)}: ${escapeHtml(share.reason)}</div>`;
    });
  }
  
  resultsDiv.innerHTML = html;
  document.getElementById('shareResults').style.display = 'block';
  
  // Clear the form
  document.getElementById('shareForm').reset();
  
  // Auto-hide results after 5 seconds if all successful
  if (result.failed_shares.length === 0) {
    setTimeout(() => {
      const resultsEl = document.getElementById('shareResults');
      if (resultsEl) {
        resultsEl.style.display = 'none';
      }
    }, 5000);
  }
}

// Toggle permission for a share
async function togglePermission(email, currentPermission) {
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
        note_id: currentNoteId,
        shared_with_email: email,
        action: 'update',
        permission: newPermission
      })
    });

    const result = await response.json();

    if (response.ok) {
      alert('Permission updated successfully!');
      loadCurrentShares(currentNoteId); // Reload shares
    } else {
      alert(result.error || 'Failed to update permission');
    }
  } catch (error) {
    console.error('Error updating permission:', error);
    alert('Failed to update permission');
  }
}

// Remove a share
async function removeShare(email) {
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
        note_id: currentNoteId,
        shared_with_email: email,
        action: 'remove'
      })
    });

    const result = await response.json();

    if (response.ok) {
      alert('Share removed successfully!');
      loadCurrentShares(currentNoteId); // Reload shares
    } else {
      alert(result.error || 'Failed to remove share');
    }
  } catch (error) {
    console.error('Error removing share:', error);
    alert('Failed to remove share');
  }
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('sharingModal');
  if (event.target === modal) {
    closeSharingModal();
  }
}

// Utility functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString() + ' at ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

// Load and apply user preferences
function loadUserPreferences() {
  const preferencesData = document.body.getAttribute('data-preferences');
  if (preferencesData) {
    try {
      const preferences = JSON.parse(preferencesData);
      applyPreferences(preferences);
      
      // Store in localStorage for consistency
      localStorage.setItem('userPreferences', JSON.stringify(preferences));
    } catch (error) {
      console.error('Error parsing preferences:', error);
    }
  }
}

// Apply preferences to the page
function applyPreferences(preferences) {
  // Apply theme
  if (preferences.theme) {
    document.body.className = preferences.theme + '-theme';
  }
  
  // Apply font size and note color via CSS variables
  const root = document.documentElement;
  if (preferences.font_size) {
    root.style.setProperty('--user-font-size', preferences.font_size + 'px');
  }
  
  if (preferences.note_color) {
    const noteColors = {
      'default': '#f8ddc2',
      'blue': '#b3d9ff',
      'green': '#c8e6c9',
      'yellow': '#fff59d',
      'pink': '#f8bbd9',
      'purple': '#e1bee7',
      'orange': '#ffcc80'
    };
    const color = noteColors[preferences.note_color] || noteColors['default'];
    root.style.setProperty('--user-note-color', color);
  }
}

// Listen for preference changes from other tabs/windows
window.addEventListener('storage', (e) => {
  if (e.key === 'userPreferences' && e.newValue) {
    try {
      const preferences = JSON.parse(e.newValue);
      applyPreferences(preferences);
      
      // Update the data-preferences attribute for consistency
      document.body.setAttribute('data-preferences', e.newValue);
    } catch (error) {
      console.error('Error applying updated preferences:', error);
    }
  }
});
