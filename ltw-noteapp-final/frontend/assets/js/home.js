// Load user preferences on page load
document.addEventListener('DOMContentLoaded', () => {
  loadUserPreferences();
  initializeMobileMenu();
});

// Initialize mobile menu functionality
function initializeMobileMenu() {
  // Create mobile menu elements if they don't exist
  if (!document.querySelector('.mobile-menu-toggle')) {
    createMobileMenuElements();
  }
  
  const mobileToggle = document.querySelector('.mobile-menu-toggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.mobile-menu-overlay');
  
  if (mobileToggle && sidebar && overlay) {
    // Toggle sidebar on button click
    mobileToggle.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
      overlay.style.display = sidebar.classList.contains('mobile-open') ? 'block' : 'none';
    });
    
    // Close sidebar when clicking overlay
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      overlay.style.display = 'none';
    });
    
    // Close sidebar when clicking on navigation links
    const navLinks = sidebar.querySelectorAll('a, .nav-btn');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 767) {
          sidebar.classList.remove('mobile-open');
          overlay.style.display = 'none';
        }
      });
    });
    
    // Handle window resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 767) {
        sidebar.classList.remove('mobile-open');
        overlay.style.display = 'none';
      }
    });
  }
}

// Create mobile menu elements
function createMobileMenuElements() {
  // Create mobile menu toggle button
  const mobileToggle = document.createElement('button');
  mobileToggle.className = 'mobile-menu-toggle';
  mobileToggle.innerHTML = '☰';
  mobileToggle.setAttribute('aria-label', 'Toggle menu');
  
  // Create overlay
  const overlay = document.createElement('div');
  overlay.className = 'mobile-menu-overlay';
  
  // Add to DOM
  document.body.insertBefore(mobileToggle, document.body.firstChild);
  document.body.appendChild(overlay);
}

// Toggle sub-list visibility
document.querySelectorAll('.toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId = btn.dataset.target;
    const list = document.getElementById(targetId);
    list.classList.toggle('hidden');
    
    // Update arrow direction
    const arrow = btn.querySelector('span:last-child') || btn.lastChild;
    if (arrow && arrow.textContent) {
      arrow.textContent = list.classList.contains('hidden') ? '▼' : '▲';
    }
  });
});

// Toggle grid/list view
document.querySelectorAll('.view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const view = btn.dataset.view;
    document.querySelectorAll('.note-container').forEach(container => {
      container.classList.remove('grid-view', 'list-view');
      container.classList.add(view + '-view');
    });
    
    // Save view preference to localStorage
    localStorage.setItem('noteViewPreference', view);
  });
});

// Load saved view preference
function loadViewPreference() {
  const savedView = localStorage.getItem('noteViewPreference');
  if (savedView) {
    const viewBtn = document.querySelector(`[data-view="${savedView}"]`);
    if (viewBtn) {
      viewBtn.click();
    }
  }
}

// Toggle dropdown menu
function toggleDropdown() {
  const menu = document.getElementById('dropdownMenu');
  const isVisible = menu.style.display === 'block';
  
  // Close all other dropdowns first
  document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
    dropdown.style.display = 'none';
  });
  
  // Toggle current dropdown
  menu.style.display = isVisible ? 'none' : 'block';
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
  const dropdown = document.getElementById('dropdownMenu');
  const userIcon = document.querySelector('.user-icon');
  const dropdownContainer = document.querySelector('.dropdown-container');
  
  if (dropdown && !dropdownContainer.contains(e.target)) {
    dropdown.style.display = 'none';
  }
});

// Handle touch events for mobile
document.addEventListener('touchstart', (e) => {
  const dropdown = document.getElementById('dropdownMenu');
  const dropdownContainer = document.querySelector('.dropdown-container');
  
  if (dropdown && !dropdownContainer.contains(e.target)) {
    dropdown.style.display = 'none';
  }
});

// Load and apply user preferences
function loadUserPreferences() {
  const preferencesData = document.body.getAttribute('data-preferences');
  if (preferencesData) {
    try {
      const preferences = JSON.parse(preferencesData);
      applyPreferences(preferences);
      
      localStorage.setItem('userPreferences', JSON.stringify(preferences));
    } catch (error) {
      console.error('Error parsing preferences:', error);
    }
  }
  
  // Load view preference after other preferences
  setTimeout(loadViewPreference, 100);
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

// Handle orientation change
window.addEventListener('orientationchange', () => {
  setTimeout(() => {
    // Recalculate layout if needed
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-menu-overlay');
    
    if (window.innerWidth > 767 && sidebar && overlay) {
      sidebar.classList.remove('mobile-open');
      overlay.style.display = 'none';
    }
  }, 100);
});

// Improved touch handling for iOS Safari
let touchStartY = 0;
let touchStartX = 0;

document.addEventListener('touchstart', (e) => {
  touchStartY = e.touches[0].clientY;
  touchStartX = e.touches[0].clientX;
}, { passive: true });

document.addEventListener('touchmove', (e) => {
  const sidebar = document.querySelector('.sidebar');
  if (sidebar && sidebar.classList.contains('mobile-open')) {
    const touchY = e.touches[0].clientY;
    const touchX = e.touches[0].clientX;
    const deltaY = touchY - touchStartY;
    const deltaX = touchX - touchStartX;
    
    // Prevent scrolling when sidebar is open and swipe is horizontal
    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
      e.preventDefault();
    }
  }
}, { passive: false });

// Add this to your main JavaScript file
function checkVerificationStatus() {
    fetch('/ltw-noteapp-final/backend/api/check-verification.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && !data.is_verified) {
                showVerificationNotification();
            } else {
                hideVerificationNotification();
            }
        });
}

function showVerificationNotification() {
    // Remove existing notification
    const existing = document.getElementById('verification-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.id = 'verification-notification';
    
    // Adjust positioning for mobile
    const isMobile = window.innerWidth <= 767;
    const positionStyle = isMobile 
      ? 'position: fixed; top: 70px; left: 10px; right: 10px;'
      : 'position: fixed; top: 20px; right: 20px; max-width: 400px;';
    
    notification.innerHTML = `
        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; ${positionStyle} z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <strong>Account Not Verified</strong><br>
            Please check your email and click the verification link to activate your account.
            <button onclick="resendVerificationEmail()" style="margin-top: ${isMobile ? '10px' : '0'}; margin-left: ${isMobile ? '0' : '10px'}; padding: 5px 10px; background: #ffc107; border: none; border-radius: 3px; cursor: pointer; ${isMobile ? 'display: block; width: 100%;' : ''}">
                Resend Email
            </button>
            <button onclick="hideVerificationNotification()" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer; position: absolute; top: 5px; right: 10px;">&times;</button>
        </div>
    `;
    document.body.appendChild(notification);
}

function hideVerificationNotification() {
    const notification = document.getElementById('verification-notification');
    if (notification) {
        notification.remove();
    }
}

function resendVerificationEmail() {
    fetch('/ltw-noteapp-final/backend/api/resend-verification.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
    });
}

// Function to view user's avatar in a modal
function viewAvatar() {
    const userIcon = document.querySelector('.user-icon');
    const avatarSrc = userIcon.src;
    
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'avatar-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 20px;
    `;
    
    // Create avatar image
    const avatarImg = document.createElement('img');
    avatarImg.src = avatarSrc;
    avatarImg.style.cssText = `
        max-width: 90%;
        max-height: 90%;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        cursor: default;
        object-fit: contain;
    `;
    
    // Create close button
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = `
        position: absolute;
        top: 20px;
        right: 30px;
        background: none;
        border: none;
        color: white;
        font-size: 40px;
        cursor: pointer;
        z-index: 2001;
        padding: 10px;
        line-height: 1;
    `;
    
    // Close modal when clicking close button or overlay
    closeBtn.onclick = closeAvatarModal;
    modal.onclick = closeAvatarModal;
    avatarImg.onclick = (e) => e.stopPropagation(); 
    
    modal.appendChild(avatarImg);
    modal.appendChild(closeBtn);
    document.body.appendChild(modal);
    
    // Add ESC key listener
    document.addEventListener('keydown', handleEscKey);
    
    // Prevent body scrolling on mobile
    document.body.style.overflow = 'hidden';
}

function closeAvatarModal() {
    const modal = document.getElementById('avatar-modal');
    if (modal) {
        modal.remove();
        document.removeEventListener('keydown', handleEscKey);
        document.body.style.overflow = ''; // Restore scrolling
    }
}

function handleEscKey(e) {
    if (e.key === 'Escape') {
        closeAvatarModal();
    }
}

// Check verification status when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkVerificationStatus();
    // Check periodically
    setInterval(checkVerificationStatus, 30000); // Check every 30 seconds
    
    // Add double-click event to user icon for viewing avatar
    const userIcon = document.querySelector('.user-icon');
    if (userIcon) {
        userIcon.addEventListener('dblclick', viewAvatar);
        userIcon.title = 'Double-click to view avatar';
        
        // Add touch support for mobile
        let touchCount = 0;
        userIcon.addEventListener('touchend', (e) => {
            touchCount++;
            if (touchCount === 1) {
                setTimeout(() => {
                    if (touchCount === 1) {
                        // Single tap - toggle dropdown
                        toggleDropdown();
                    } else if (touchCount === 2) {
                        // Double tap - view avatar
                        viewAvatar();
                    }
                    touchCount = 0;
                }, 300);
            }
            e.preventDefault();
        });
    }
});

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Optimized scroll handling
const handleScroll = debounce(() => {
    // Add any scroll-based functionality here if needed
}, 100);

window.addEventListener('scroll', handleScroll, { passive: true });
