// User Mentions Functionality
class MentionManager {
    constructor(textareaId, options = {}) {
        this.textarea = document.getElementById(textareaId);
        this.mentionsList = null;
        this.users = [];
        this.currentMention = '';
        this.mentionStartPos = -1;
        this.selectedIndex = -1;
        this.isLoading = false;
        this.options = {
            trigger: '@',
            minChars: 1,
            ...options
        };
        
        console.log('MentionManager initialized for:', textareaId);
        this.init();
    }
    
    init() {
        if (!this.textarea) {
            console.error('Textarea not found!');
            return;
        }
        
        console.log('Creating mentions dropdown...');
        // Create mentions dropdown
        this.createMentionsDropdown();
        
        console.log('Loading users...');
        // Load users from server
        this.loadUsers();
        
        // Add event listeners
        this.textarea.addEventListener('input', (e) => this.handleInput(e));
        this.textarea.addEventListener('keydown', (e) => this.handleKeydown(e));
        this.textarea.addEventListener('blur', () => setTimeout(() => this.hideMentions(), 200));
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (this.mentionsList && !this.mentionsList.contains(e.target) && e.target !== this.textarea) {
                this.hideMentions();
            }
        });
        
        console.log('MentionManager ready!');
    }
    
    createMentionsDropdown() {
        this.mentionsList = document.createElement('div');
        this.mentionsList.className = 'mentions-dropdown';
        this.mentionsList.style.display = 'none';
        this.mentionsList.style.position = 'absolute';
        this.mentionsList.style.background = 'white';
        this.mentionsList.style.border = '1px solid #ddd';
        this.mentionsList.style.borderRadius = '8px';
        this.mentionsList.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        this.mentionsList.style.maxHeight = '200px';
        this.mentionsList.style.overflowY = 'auto';
        this.mentionsList.style.zIndex = '1000';
        this.mentionsList.style.minWidth = '220px';
        
        // Make sure parent has position relative for absolute positioning
        if (getComputedStyle(this.textarea.parentNode).position !== 'relative') {
            this.textarea.parentNode.style.position = 'relative';
        }
        
        this.textarea.parentNode.appendChild(this.mentionsList);
        console.log('Dropdown created');
    }
    
    async loadUsers() {
        this.isLoading = true;
        
        try {
            console.log('Fetching users from API...');
            const response = await fetch('../../modules/notifications/get_users.php');
            const data = await response.json();
            console.log('API Response:', data);
            
            if (data.success && data.users) {
                this.users = data.users;
                console.log('Users loaded:', this.users.length);
            } else {
                console.error('Failed to load users:', data);
                // Fallback sample users
                this.users = [
                    { id: 1, username: 'superadmin', full_name: 'Super Administrator', initial: 'S', role: 'super_admin', role_display: 'Super Admin' },
                    { id: 2, username: 'records_officer', full_name: 'Records Officer', initial: 'R', role: 'records_officer', role_display: 'Records Officer' },
                    { id: 3, username: 'admin_user', full_name: 'Administrator', initial: 'A', role: 'admin', role_display: 'Administrator' }
                ];
            }
        } catch (error) {
            console.error('Error loading users:', error);
            // Fallback sample users
            this.users = [
                { id: 1, username: 'superadmin', full_name: 'Super Administrator', initial: 'S', role: 'super_admin', role_display: 'Super Admin' },
                { id: 2, username: 'records_officer', full_name: 'Records Officer', initial: 'R', role: 'records_officer', role_display: 'Records Officer' },
                { id: 3, username: 'admin_user', full_name: 'Administrator', initial: 'A', role: 'admin', role_display: 'Administrator' }
            ];
        }
        
        this.isLoading = false;
    }
    
    handleInput(e) {
        const cursorPos = this.textarea.selectionStart;
        const textBeforeCursor = this.textarea.value.substring(0, cursorPos);
        
        // Find the last @ symbol
        const lastAtIndex = textBeforeCursor.lastIndexOf(this.options.trigger);
        
        if (lastAtIndex !== -1) {
            // Check if @ is at the beginning or preceded by space/newline
            const charBeforeAt = lastAtIndex > 0 ? textBeforeCursor[lastAtIndex - 1] : '';
            const isValidTrigger = lastAtIndex === 0 || /[\s\n]/.test(charBeforeAt);
            
            if (isValidTrigger) {
                const searchText = textBeforeCursor.substring(lastAtIndex + 1);
                // Check if we're still typing the mention (no space after)
                if (!searchText.includes(' ') && !searchText.includes('\n')) {
                    this.mentionStartPos = lastAtIndex;
                    this.showMentions(searchText);
                    return;
                }
            }
        }
        
        this.hideMentions();
    }
    
    handleKeydown(e) {
        if (!this.mentionsList || this.mentionsList.style.display === 'none') return;
        
        const items = this.mentionsList.querySelectorAll('.mention-item');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.updateSelectedItem(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelectedItem(items);
                break;
            case 'Enter':
                if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                    e.preventDefault();
                    items[this.selectedIndex].click();
                }
                break;
            case 'Escape':
                this.hideMentions();
                break;
        }
    }
    
    updateSelectedItem(items) {
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }
    
    showMentions(searchText) {
        console.log('Showing mentions for:', searchText);
        
        if (!this.users.length) {
            console.log('No users loaded yet');
            return;
        }
        
        let filteredUsers = this.users;
        if (searchText && searchText.length >= this.options.minChars) {
            const searchLower = searchText.toLowerCase();
            filteredUsers = this.users.filter(user => 
                user.full_name.toLowerCase().includes(searchLower) ||
                user.username.toLowerCase().includes(searchLower)
            );
        }
        
        console.log('Filtered users:', filteredUsers.length);
        
        if (filteredUsers.length === 0) {
            this.hideMentions();
            return;
        }
        
        // Position the dropdown
        const rect = this.textarea.getBoundingClientRect();
        
        // Get cursor position in pixels
        const cursorPos = this.textarea.selectionStart;
        const tempDiv = document.createElement('div');
        const textareaStyles = window.getComputedStyle(this.textarea);
        
        tempDiv.style.cssText = textareaStyles.cssText;
        tempDiv.style.position = 'absolute';
        tempDiv.style.visibility = 'hidden';
        tempDiv.style.whiteSpace = 'pre-wrap';
        tempDiv.style.width = rect.width + 'px';
        tempDiv.style.top = '-9999px';
        tempDiv.style.left = '-9999px';
        tempDiv.textContent = this.textarea.value.substring(0, cursorPos);
        document.body.appendChild(tempDiv);
        
        const cursorHeight = tempDiv.offsetHeight;
        const lineHeight = parseInt(textareaStyles.lineHeight);
        
        document.body.removeChild(tempDiv);
        
        this.mentionsList.innerHTML = '';
        filteredUsers.forEach((user, index) => {
            const item = document.createElement('div');
            item.className = 'mention-item';
            item.style.cssText = 'padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f0;';
            item.innerHTML = `
                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px; flex-shrink: 0;">
                    ${this.escapeHtml(user.initial)}
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 500; font-size: 13px; color: #333;">${this.escapeHtml(user.full_name)}</div>
                    <div style="font-size: 10px; color: #999;">@${this.escapeHtml(user.username)}</div>
                    <div style="font-size: 10px; color: #666;">${this.escapeHtml(user.role_display)}</div>
                </div>
            `;
            item.addEventListener('click', () => this.insertMention(user));
            this.mentionsList.appendChild(item);
        });
        
        // Position the dropdown below the cursor
        const scrollTop = this.textarea.scrollTop;
        const top = rect.top + window.scrollY + cursorHeight + 5;
        const left = rect.left + window.scrollX + 10;
        
        this.mentionsList.style.top = top + 'px';
        this.mentionsList.style.left = left + 'px';
        this.mentionsList.style.display = 'block';
        
        this.selectedIndex = -1;
        console.log('Dropdown shown at:', top, left);
    }
    
    hideMentions() {
        if (this.mentionsList) {
            this.mentionsList.style.display = 'none';
        }
        this.selectedIndex = -1;
        this.mentionStartPos = -1;
    }
    
    insertMention(user) {
        console.log('Inserting mention for:', user.full_name);
        
        if (this.mentionStartPos === -1) return;
        
        const before = this.textarea.value.substring(0, this.mentionStartPos);
        const after = this.textarea.value.substring(this.textarea.selectionStart);
        const mentionText = `@${user.username} `;
        
        this.textarea.value = before + mentionText + after;
        
        // Set cursor position after the mention
        const newCursorPos = before.length + mentionText.length;
        this.textarea.selectionStart = newCursorPos;
        this.textarea.selectionEnd = newCursorPos;
        
        this.textarea.focus();
        this.hideMentions();
        
        // Trigger input event to update any other listeners
        this.textarea.dispatchEvent(new Event('input'));
        
        console.log('Mention inserted');
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize mention manager when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking for comment input...');
    const commentInput = document.getElementById('commentInput');
    if (commentInput) {
        console.log('Comment input found, initializing MentionManager...');
        window.mentionManager = new MentionManager('commentInput', {
            trigger: '@',
            minChars: 1
        });
    } else {
        console.log('Comment input not found on this page');
    }
});