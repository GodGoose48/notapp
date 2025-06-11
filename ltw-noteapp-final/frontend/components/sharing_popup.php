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
                </div>
                
                <div class="form-group">
                    <label for="sharePermission">Permission:</label>
                    <select id="sharePermission" required>
                        <option value="read">Read Only</option>
                        <option value="edit">Can Edit</option>
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
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group textarea,
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    height: 80px;
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-actions button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.form-actions button[type="submit"] {
    background-color: #007bff;
    color: white;
}

.form-actions button[type="button"] {
    background-color: #6c757d;
    color: white;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

#shareResults {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.share-success {
    color: #28a745;
    margin-bottom: 5px;
}

.share-error {
    color: #dc3545;
    margin-bottom: 5px;
}
</style>

<script>
let currentNoteId = null;

function openSharingModal(noteId) {
    currentNoteId = noteId;
    document.getElementById('sharingModal').style.display = 'block';
    document.getElementById('shareResults').style.display = 'none';
    document.getElementById('shareForm').reset();
}

function closeSharingModal() {
    document.getElementById('sharingModal').style.display = 'none';
    currentNoteId = null;
}

document.getElementById('shareForm').addEventListener('submit', async function(e) {
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
        } else {
            alert(result.error || 'Failed to share note');
        }
    } catch (error) {
        console.error('Error sharing note:', error);
        alert('Failed to share note');
    }
});

function displayShareResults(result) {
    const resultsDiv = document.getElementById('shareResultsContent');
    let html = '';
    
    if (result.successful_shares.length > 0) {
        html += '<h5>Successfully shared with:</h5>';
        result.successful_shares.forEach(share => {
            html += `<div class="share-success">✓ ${share.email} (${share.action})</div>`;
        });
    }
    
    if (result.failed_shares.length > 0) {
        html += '<h5>Failed to share with:</h5>';
        result.failed_shares.forEach(share => {
            html += `<div class="share-error">✗ ${share.email}: ${share.reason}</div>`;
        });
    }
    
    resultsDiv.innerHTML = html;
    document.getElementById('shareResults').style.display = 'block';
    
    // Hide the form
    document.getElementById('shareForm').style.display = 'none';
    
    // Auto-close after 3 seconds if all successful
    if (result.failed_shares.length === 0) {
        setTimeout(() => {
            closeSharingModal();
            // Refresh the page if we're on sharing management
            if (window.location.pathname.includes('sharing_management.php')) {
                location.reload();
            }
        }, 2000);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('sharingModal');
    if (event.target === modal) {
        closeSharingModal();
    }
}
</script>