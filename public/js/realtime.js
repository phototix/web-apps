// Real-time Client for Long Polling
class RealtimeClient {
    constructor(userId) {
        this.userId = userId;
        this.lastUpdateId = 0;
        this.isPolling = false;
        this.handlers = {
            qr_update: [],
            new_message: [],
            session_status: [],
            group_update: [],
            message_sent: []
        };
    }
    
    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.poll();
    }
    
    stopPolling() {
        this.isPolling = false;
    }
    
    async poll() {
        while (this.isPolling) {
            try {
                const response = await fetch(`/api/realtime/updates?last_id=${this.lastUpdateId}&timeout=10`);
                const data = await response.json();
                
                if (data.success && data.data.updates.length > 0) {
                    this.lastUpdateId = data.data.last_id;
                    this.processUpdates(data.data.updates);
                    
                    // Mark as read
                    await fetch('/api/realtime/mark-read', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({update_id: this.lastUpdateId})
                    });
                }
            } catch (error) {
                console.error('Polling error:', error);
                await this.sleep(5000); // Wait 5 seconds on error
            }
        }
    }
    
    processUpdates(updates) {
        updates.forEach(update => {
            const handlers = this.handlers[update.update_type] || [];
            handlers.forEach(handler => handler(update.data));
        });
    }
    
    on(eventType, handler) {
        if (!this.handlers[eventType]) {
            this.handlers[eventType] = [];
        }
        this.handlers[eventType].push(handler);
    }
    
    off(eventType, handler) {
        if (!this.handlers[eventType]) return;
        const index = this.handlers[eventType].indexOf(handler);
        if (index > -1) {
            this.handlers[eventType].splice(index, 1);
        }
    }
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealtimeClient;
}