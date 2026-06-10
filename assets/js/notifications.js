// Desktop Notification Manager
class NotificationManager {
    constructor() {
        this.permission = false;
        this.notificationSound = new Audio('../../assets/sounds/notification.mp3');
        this.checkInterval = null;
        this.lastCheckTime = localStorage.getItem('lastNotificationCheck') || Date.now();
        this.init();
    }
    
    // Initialize notification system
    async init() {
        // Request permission
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            this.permission = permission === 'granted';
            
            if (this.permission) {
                console.log('Desktop notifications enabled');
                this.startPolling();
            }
        }
        
        // Check if browser supports service workers for push notifications
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../../sw.js')
                .then(reg => console.log('Service Worker registered', reg))
                .catch(err => console.log('Service Worker registration failed', err));
        }
    }
    
    // Start polling for new notifications
    startPolling() {
        // Check every 30 seconds
        this.checkInterval = setInterval(() => {
            this.checkForUpdates();
        }, 30000);
        
        // Also check when page becomes visible again
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkForUpdates();
            }
        });
    }
    
    // Check for new updates from server
    async checkForUpdates() {
        if (!this.permission) return;
        
        try {
            const response = await fetch('../../modules/notifications/check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    last_check: this.lastCheckTime
                })
            });
            
            const data = await response.json();
            
            if (data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    this.showNotification(notification);
                });
                this.lastCheckTime = Date.now();
                localStorage.setItem('lastNotificationCheck', this.lastCheckTime);
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }
    
    // Show desktop notification
    showNotification(notification) {
        if (!this.permission) return;
        
        const options = {
            body: notification.message,
            icon: notification.icon || '../../assets/images/logo.png',
            badge: '../../assets/images/badge.png',
            tag: notification.type + '_' + notification.id,
            requireInteraction: notification.requireInteraction || false,
            data: {
                url: notification.url,
                id: notification.id,
                type: notification.type
            }
        };
        
        const notif = new Notification(notification.title, options);
        
        // Play sound
        this.playSound();
        
        // Handle click
        notif.onclick = (event) => {
            event.preventDefault();
            window.focus();
            if (notification.url) {
                window.location.href = notification.url;
            }
            notif.close();
        };
        
        // Auto close after 10 seconds
        setTimeout(() => {
            notif.close();
        }, 10000);
    }
    
    // Play notification sound
    playSound() {
        // Check if sound is enabled in settings
        const soundEnabled = localStorage.getItem('notificationSound') !== 'false';
        if (soundEnabled && this.notificationSound) {
            this.notificationSound.play().catch(e => console.log('Sound play failed:', e));
        }
    }
    
    // Send custom notification
    sendNotification(title, message, url, type = 'custom') {
        this.showNotification({
            title: title,
            message: message,
            url: url,
            type: type,
            icon: '../../assets/images/logo.png',
            requireInteraction: type === 'urgent'
        });
    }
    
    // Stop polling
    stopPolling() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }
    
    // Update user preferences
    updatePreferences(settings) {
        if (settings.sound !== undefined) {
            localStorage.setItem('notificationSound', settings.sound);
        }
        if (settings.enabled !== undefined) {
            if (!settings.enabled && this.checkInterval) {
                this.stopPolling();
            } else if (settings.enabled && !this.checkInterval) {
                this.startPolling();
            }
        }
    }
}

// Initialize on page load
let notificationManager = null;

document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if user is logged in
    if (typeof isLoggedIn !== 'undefined' && isLoggedIn) {
        notificationManager = new NotificationManager();
    }
});