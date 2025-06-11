class CollaborativeEditor {
    constructor() {
        this.noteId = document.body.dataset.noteId;
        this.userId = document.body.dataset.userId;
        this.canEdit = document.body.dataset.canEdit === 'true';
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.typingTimeout = null;
        this.lastContent = '';
        this.version = 1;
        this.activeUsers = new Map();
        
        this.titleEl = document.getElementById('title');
        this.contentEl = document.getElementById('content');
        this.statusEl = document.getElementById('statusMessage');
        this.connectionStatusEl = document.getElementById('connectionStatus');
        this.activeUsersEl = document.getElementById('activeUsers');
        this.lastSavedEl = document.getElementById('lastSaved');
        this.versionEl = document.getElementById('version');
        
        this.init();
    }

    init() {
        this.setupWebSocket();
        this.setupEventListeners();
        this.loadInitialData();
    }

    setupWebSocket() {
        // For now, we'll simulate WebSocket with polling
        // In production, you'd use: new WebSocket('ws://localhost:8080');
        this.simulateWebSocket();
    }

    simulateWebSocket() {
        // Simulate WebSocket connection with polling
        this.connected = true;
        this.connectionStatusEl.textContent = 'Connected';
        
        // Poll for changes every 2 seconds
        this.pollInterval = setInterval(() => {
            this.pollForChanges();
        }, 2000);

        // Poll for active users every 5 seconds
        this.userPollInterval = setInterval(() => {
            this.pollActiveUsers();
        }, 5000);
    }

    async pollForChanges() {
        if (!this.canEdit) return;

        try {
            const response = await fetch('/ltw-noteapp-final/backend/api/get_note_changes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    note_id: this.noteId,
                    version: this.version
                })
            });

            const data = await response.json();
            
            if (data.success && data.changes.length > 0) {
                this.applyChanges(data.changes);
            }
        } catch (error) {
            console.error('Error polling for changes:', error);
        }
    }

    async pollActiveUsers() {
        try {
            const response = await fetch(`/ltw-noteapp-final/backend/api/collaboration_status.php?note_id=${this.noteId}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateActiveUsers(data.active_users_list || []);
            }
        } catch (error) {
            console.error('Error polling active users:', error);
        }
    }

    setupEventListeners() {
        if (!this.canEdit) return;

        // Title changes
        this.titleEl.addEventListener('input', this.debounce(() => {
            this.sendChange('title', this.titleEl.value);
        }, 500));

        // Content changes
        this.contentEl.addEventListener('input', this.debounce(() => {
            this.sendChange('content', this.contentEl.innerHTML);
        }, 500));

        // Typing indicators
        this.contentEl.addEventListener('input', () => {
            this.sendTypingIndicator();
        });

        // Cursor position tracking
        this.contentEl.addEventListener('selectionchange', () => {
            this.sendCursorPosition();
        });

        // Toolbar formatting
        document.querySelectorAll('.toolbar button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const command = btn.dataset.command;
                document.execCommand(command, false, null);
                this.sendChange('content', this.contentEl.innerHTML);
            });
        });

        // Mark user as active
        this.markUserActive();
        
        // Heartbeat to keep user active
        setInterval(() => {
            this.markUserActive();
        }, 30000); // Every 30 seconds

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }

    async loadInitialData() {
        try {
            const response = await fetch(`/ltw-noteapp-final/backend/api/get_note_version.php?note_id=${this.noteId}`);
            const data = await response.json();
            
            if (data.success) {
                this.version = data.version;
                this.versionEl.textContent = this.version;
                this.lastSavedEl.textContent = this.formatDate(data.last_saved);
            }
        } catch (error) {
            console.error('Error loading initial data:', error);
        }
    }

    async sendChange(field, value) {
        if (!this.canEdit) return;

        try {
            const response = await fetch('/ltw-noteapp-final/backend/api/save_collaborative_change.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    note_id: this.noteId,
                    field: field,
                    value: value,
                    version: this.version,
                    user_id: this.userId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.version = data.new_version;
                this.versionEl.textContent = this.version;
                this.lastSavedEl.textContent = 'Just now';
                this.statusEl.textContent = 'Saved';
                setTimeout(() => {
                    this.statusEl.textContent = '';
                }, 2000);
            } else {
                this.statusEl.textContent = 'Error saving: ' + (data.error || 'Unknown error');
            }
        } catch (error) {
            console.error('Error sending change:', error);
            this.statusEl.textContent = 'Error saving changes';
        }
    }

    applyChanges(changes) {
        changes.forEach(change => {
            if (change.user_id == this.userId) return; // Skip own changes

            const element = change.field === 'title' ? this.titleEl : this.contentEl;
            const currentValue = change.field === 'title' ? element.value : element.innerHTML;
            
            if (currentValue !== change.value) {
                // Apply change with highlight
                if (change.field === 'title') {
                    element.value = change.value;
                } else {
                    element.innerHTML = change.value;
                }
                
                // Highlight the change
                element.classList.add('change-highlight');
                setTimeout(() => {
                    element.classList.remove('change-highlight');
                }, 2000);

                this.version = Math.max(this.version, change.version);
                this.versionEl.textContent = this.version;
                
                // Show who made the change
                this.showChangeNotification(change.user_name, change.field);
            }
        });
    }

    showChangeNotification(userName, field) {
        const notification = document.createElement('div');
        notification.className = 'typing-indicator visible';
        notification.textContent = `${userName} updated the ${field}`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.remove('visible');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    async sendTypingIndicator() {
        if (!this.canEdit) return;

        clearTimeout(this.typingTimeout);
        
        // Send typing start
        fetch('/ltw-noteapp-final/backend/api/typing_indicator.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                note_id: this.noteId,
                user_id: this.userId,
                action: 'start'
            })
        });

        this.typingTimeout = setTimeout(() => {
            // Send typing stop
            fetch('/ltw-noteapp-final/backend/api/typing_indicator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    note_id: this.noteId,
                    user_id: this.userId,
                    action: 'stop'
                })
            });
        }, 2000);
    }

    async markUserActive() {
        try {
            await fetch('/ltw-noteapp-final/backend/api/mark_user_active.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    note_id: this.noteId,
                    user_id: this.userId
                })
            });
        } catch (error) {
            console.error('Error marking user active:', error);
        }
    }

    updateActiveUsers(users) {
        this.activeUsersEl.innerHTML = '';
        
        users.forEach(user => {
            if (user.id != this.userId) {
                const userEl = document.createElement('div');
                userEl.className = 'user-cursor';
                userEl.style.backgroundColor = this.getUserColor(user.id);
                userEl.dataset.name = user.name || user.email;
                userEl.textContent = (user.name || user.email).charAt(0).toUpperCase();
                this.activeUsersEl.appendChild(userEl);
            }
        });

        // Update connection status
        const activeCount = users.length - 1; // Exclude self
        if (activeCount > 0) {
            this.connectionStatusEl.textContent = `Connected (${activeCount} other user${activeCount > 1 ? 's' : ''})`;
        } else {
            this.connectionStatusEl.textContent = 'Connected';
        }
    }

    getUserColor(userId) {
        const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FECA57', '#FF9FF3', '#54A0FF'];
        return colors[userId % colors.length];
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} min ago`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
        return date.toLocaleDateString();
    }

    debounce(func, wait) {
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

    cleanup() {
        if (this.pollInterval) clearInterval(this.pollInterval);
        if (this.userPollInterval) clearInterval(this.userPollInterval);
        
        // Mark user as inactive
        fetch('/ltw-noteapp-final/backend/api/mark_user_inactive.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                note_id: this.noteId,
                user_id: this.userId
            })
        });
    }
}

// Initialize collaborative editor when page loads
document.addEventListener('DOMContentLoaded', () => {
    new CollaborativeEditor();
});