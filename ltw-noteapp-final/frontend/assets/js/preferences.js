document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('preferencesForm');
  const fontSizeSlider = document.getElementById('fontSize');
  const fontSizeDisplay = document.getElementById('fontSizeDisplay');
  const fontPreview = document.getElementById('fontPreview');
  const themeInputs = document.querySelectorAll('input[name="theme"]');
  const statusMessage = document.getElementById('statusMessage');

  // Update font size display and preview
  fontSizeSlider.addEventListener('input', () => {
    const fontSize = fontSizeSlider.value;
    fontSizeDisplay.textContent = fontSize + 'px';
    fontPreview.style.fontSize = fontSize + 'px';
  });

  // Theme change preview
  themeInputs.forEach(input => {
    input.addEventListener('change', () => {
      document.body.className = input.value + '-theme';
    });
  });

  // Form submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(form);
    
    try {
      const response = await fetch('/ltw-noteapp-final/backend/api/update_preferences.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        statusMessage.textContent = 'Preferences saved successfully!';
        statusMessage.className = 'status-message success';
        
        // Apply preferences immediately
        applyPreferences(Object.fromEntries(formData));
      } else {
        statusMessage.textContent = 'Error saving preferences: ' + (result.message || 'Unknown error');
        statusMessage.className = 'status-message error';
      }
    } catch (error) {
      statusMessage.textContent = 'Error saving preferences: ' + error.message;
      statusMessage.className = 'status-message error';
    }
    
    // Clear status message after 3 seconds
    setTimeout(() => {
      statusMessage.textContent = '';
      statusMessage.className = 'status-message';
    }, 3000);
  });

  // Apply preferences function
  function applyPreferences(preferences) {
    // Apply theme immediately
    if (preferences.theme) {
      document.body.className = preferences.theme + '-theme';
    }
    
    localStorage.setItem('userPreferences', JSON.stringify(preferences));
    
    document.body.setAttribute('data-preferences', JSON.stringify(preferences));
    
    localStorage.setItem('userPreferencesUpdated', Date.now().toString());
  }

  // Initialize font preview on page load
  const currentFontSize = fontSizeSlider.value;
  fontSizeDisplay.textContent = currentFontSize + 'px';
  fontPreview.style.fontSize = currentFontSize + 'px';
});