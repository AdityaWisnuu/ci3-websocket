// WebSocket Client Application
class WebSocketClient {
    constructor() {
        this.ws = null;
        this.connected = false;
        this.clientId = null;
        this.rooms = [];
        this.sentCount = 0;
        this.receivedCount = 0;
        this.connectedAt = null;
        this.uptimeInterval = null;
        
        this.initElements();
        this.attachEventListeners();
    }
    
    initElements() {
        // Connection elements
        this.wsUrlInput = document.getElementById('ws-url');
        this.connectBtn = document.getElementById('connect-btn');
        this.disconnectBtn = document.getElementById('disconnect-btn');
        this.connectionStatus = document.getElementById('connection-status');
        this.clientIdDisplay = document.getElementById('client-id');
        
        // Room elements
        this.roomNameInput = document.getElementById('room-name');
        this.joinRoomBtn = document.getElementById('join-room-btn');
        this.leaveRoomBtn = document.getElementById('leave-room-btn');
        this.roomList = document.getElementById('room-list');
        
        // Message elements
        this.simpleInput = document.getElementById('simple-input');
        this.simpleSendBtn = document.getElementById('simple-send-btn');
        this.simpleMessages = document.getElementById('simple-messages');
        
        this.broadcastInput = document.getElementById('broadcast-input');
        this.broadcastSendBtn = document.getElementById('broadcast-send-btn');
        this.broadcastMessages = document.getElementById('broadcast-messages');
        
        this.roomsInput = document.getElementById('rooms-input');
        this.roomsSendBtn = document.getElementById('rooms-send-btn');
        this.roomsMessages = document.getElementById('rooms-messages');
        
        this.logsMessages = document.getElementById('logs-messages');
        this.clearLogsBtn = document.getElementById('clear-logs-btn');
        
        // Stats elements
        this.sentCountDisplay = document.getElementById('sent-count');
        this.receivedCountDisplay = document.getElementById('received-count');
        this.uptimeDisplay = document.getElementById('uptime');
        
        // Tab elements
        this.tabs = document.querySelectorAll('.tab');
        this.tabContents = document.querySelectorAll('.tab-content');
    }
    
    attachEventListeners() {
        // Connection buttons
        this.connectBtn.addEventListener('click', () => this.connect());
        this.disconnectBtn.addEventListener('click', () => this.disconnect());
        
        // Room buttons
        this.joinRoomBtn.addEventListener('click', () => this.joinRoom());
        this.leaveRoomBtn.addEventListener('click', () => this.leaveRoom());
        
        // Send buttons
        this.simpleSendBtn.addEventListener('click', () => this.sendSimpleMessage());
        this.broadcastSendBtn.addEventListener('click', () => this.sendBroadcast());
        this.roomsSendBtn.addEventListener('click', () => this.sendRoomMessage());
        
        // Enter key handlers
        this.simpleInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendSimpleMessage();
        });
        this.broadcastInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendBroadcast();
        });
        this.roomsInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendRoomMessage();
        });
        
        // Clear logs
        this.clearLogsBtn.addEventListener('click', () => {
            this.logsMessages.innerHTML = '';
        });
        
        // Tab switching
        this.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.getAttribute('data-tab');
                this.switchTab(tabName);
            });
        });
    }
    
    connect() {
        const url = this.wsUrlInput.value;
        
        if (!url) {
            alert('Please enter WebSocket URL');
            return;
        }
        
        this.log(`Connecting to ${url}...`, 'info');
        
        try {
            this.ws = new WebSocket(url);
            
            this.ws.onopen = () => this.onOpen();
            this.ws.onmessage = (event) => this.onMessage(event);
            this.ws.onerror = (error) => this.onError(error);
            this.ws.onclose = () => this.onClose();
            
        } catch (error) {
            this.log(`Connection error: ${error.message}`, 'error');
            alert('Failed to connect: ' + error.message);
        }
    }
    
    disconnect() {
        if (this.ws) {
            this.ws.close();
        }
    }
    
    onOpen() {
        this.connected = true;
        this.connectedAt = Date.now();
        this.updateConnectionStatus(true);
        this.log('Connected to WebSocket server', 'success');
        
        // Start uptime counter
        this.uptimeInterval = setInterval(() => this.updateUptime(), 1000);
        
        // Enable controls
        this.enableControls(true);
    }
    
    onMessage(event) {
        this.receivedCount++;
        this.updateStats();
        
        const data = JSON.parse(event.data);
        this.log(`Received: ${JSON.stringify(data)}`, 'info');
        
        // Handle different event types
        switch(data.event) {
            case 'welcome':
                this.clientId = data.client_id;
                this.clientIdDisplay.textContent = this.clientId;
                this.addMessage('simple', data, 'system');
                break;
                
            case 'echo':
                this.addMessage('simple', data, 'received');
                break;
                
            case 'broadcast':
                this.addMessage('broadcast', data, 'received');
                break;
                
            case 'user_joined':
                this.addMessage('rooms', data, 'system');
                break;
                
            case 'user_left':
                this.addMessage('rooms', data, 'system');
                break;
                
            case 'room_message':
                this.addMessage('rooms', data, 'received');
                break;
                
            default:
                this.addMessage('simple', data, 'received');
        }
    }
    
    onError(error) {
        this.log(`WebSocket error: ${error}`, 'error');
    }
    
    onClose() {
        this.connected = false;
        this.updateConnectionStatus(false);
        this.log('Disconnected from WebSocket server', 'warning');
        
        // Clear uptime counter
        if (this.uptimeInterval) {
            clearInterval(this.uptimeInterval);
            this.uptimeInterval = null;
        }
        
        // Disable controls
        this.enableControls(false);
        
        // Reset client ID
        this.clientId = null;
        this.clientIdDisplay.textContent = 'Not connected';
    }
    
    sendSimpleMessage() {
        const message = this.simpleInput.value.trim();
        
        if (!message) return;
        
        const data = {
            event: 'message',
            message: message
        };
        
        this.send(data);
        this.addMessage('simple', { message: message }, 'sent');
        this.simpleInput.value = '';
    }
    
    sendBroadcast() {
        const message = this.broadcastInput.value.trim();
        
        if (!message) return;
        
        const data = {
            event: 'broadcast',
            message: message
        };
        
        this.send(data);
        this.addMessage('broadcast', { message: message, from: 'You' }, 'sent');
        this.broadcastInput.value = '';
    }
    
    sendRoomMessage() {
        const message = this.roomsInput.value.trim();
        const room = this.roomNameInput.value.trim();
        
        if (!message || !room) return;
        
        const data = {
            event: 'room_message',
            room: room,
            message: message
        };
        
        this.send(data);
        this.addMessage('rooms', { message: message, room: room, from: 'You' }, 'sent');
        this.roomsInput.value = '';
    }
    
    joinRoom() {
        const room = this.roomNameInput.value.trim();
        
        if (!room) {
            alert('Please enter room name');
            return;
        }
        
        if (this.rooms.includes(room)) {
            alert('Already in this room');
            return;
        }
        
        const data = {
            event: 'join_room',
            room: room
        };
        
        this.send(data);
        this.rooms.push(room);
        this.updateRoomList();
        this.log(`Joined room: ${room}`, 'success');
    }
    
    leaveRoom() {
        const room = this.roomNameInput.value.trim();
        
        if (!room) {
            alert('Please enter room name');
            return;
        }
        
        if (!this.rooms.includes(room)) {
            alert('Not in this room');
            return;
        }
        
        const data = {
            event: 'leave_room',
            room: room
        };
        
        this.send(data);
        this.rooms = this.rooms.filter(r => r !== room);
        this.updateRoomList();
        this.log(`Left room: ${room}`, 'warning');
    }
    
    send(data) {
        if (!this.connected || !this.ws) {
            alert('Not connected to server');
            return;
        }
        
        this.ws.send(JSON.stringify(data));
        this.sentCount++;
        this.updateStats();
        this.log(`Sent: ${JSON.stringify(data)}`, 'info');
    }
    
    addMessage(tab, data, type) {
        const container = document.getElementById(`${tab}-messages`);
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        
        const time = new Date().toLocaleTimeString();
        let content = '';
        
        if (data.event) {
            content += `<div class="event">${data.event.toUpperCase()}</div>`;
        }
        
        if (data.message) {
            content += `<div class="content">${this.escapeHtml(data.message)}</div>`;
        }
        
        if (data.from) {
            content += `<div class="content"><strong>From:</strong> ${data.from}</div>`;
        }
        
        if (data.room) {
            content += `<div class="content"><strong>Room:</strong> ${data.room}</div>`;
        }
        
        if (data.client_id && tab === 'simple') {
            content += `<div class="content"><strong>Client ID:</strong> ${data.client_id}</div>`;
        }
        
        messageDiv.innerHTML = `
            <div class="time">${time}</div>
            ${content}
        `;
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
    }
    
    log(message, level) {
        const logDiv = document.createElement('div');
        logDiv.className = 'message system';
        
        const time = new Date().toLocaleTimeString();
        const levelColors = {
            info: '#3b82f6',
            success: '#10b981',
            warning: '#f59e0b',
            error: '#ef4444'
        };
        
        logDiv.innerHTML = `
            <div class="time">${time}</div>
            <div class="content" style="color: ${levelColors[level] || '#374151'}">
                [${level.toUpperCase()}] ${this.escapeHtml(message)}
            </div>
        `;
        
        this.logsMessages.appendChild(logDiv);
        this.logsMessages.scrollTop = this.logsMessages.scrollHeight;
    }
    
    updateConnectionStatus(connected) {
        if (connected) {
            this.connectionStatus.textContent = 'Connected';
            this.connectionStatus.className = 'status connected';
        } else {
            this.connectionStatus.textContent = 'Disconnected';
            this.connectionStatus.className = 'status disconnected';
        }
    }
    
    enableControls(enable) {
        this.connectBtn.disabled = enable;
        this.disconnectBtn.disabled = !enable;
        this.joinRoomBtn.disabled = !enable;
        this.leaveRoomBtn.disabled = !enable;
        this.simpleInput.disabled = !enable;
        this.simpleSendBtn.disabled = !enable;
        this.broadcastInput.disabled = !enable;
        this.broadcastSendBtn.disabled = !enable;
        this.roomsInput.disabled = !enable;
        this.roomsSendBtn.disabled = !enable;
    }
    
    updateStats() {
        this.sentCountDisplay.textContent = this.sentCount;
        this.receivedCountDisplay.textContent = this.receivedCount;
    }
    
    updateUptime() {
        if (!this.connectedAt) return;
        
        const uptime = Math.floor((Date.now() - this.connectedAt) / 1000);
        const hours = Math.floor(uptime / 3600);
        const minutes = Math.floor((uptime % 3600) / 60);
        const seconds = uptime % 60;
        
        let uptimeStr = '';
        if (hours > 0) uptimeStr += `${hours}h `;
        if (minutes > 0) uptimeStr += `${minutes}m `;
        uptimeStr += `${seconds}s`;
        
        this.uptimeDisplay.textContent = uptimeStr;
    }
    
    updateRoomList() {
        this.roomList.innerHTML = '';
        
        this.rooms.forEach(room => {
            const li = document.createElement('li');
            li.className = 'room-item';
            li.innerHTML = `
                <span>${room}</span>
                <span class="badge">Joined</span>
            `;
            this.roomList.appendChild(li);
        });
    }
    
    switchTab(tabName) {
        // Remove active class from all tabs
        this.tabs.forEach(tab => tab.classList.remove('active'));
        this.tabContents.forEach(content => content.classList.remove('active'));
        
        // Add active class to selected tab
        const selectedTab = document.querySelector(`[data-tab="${tabName}"]`);
        const selectedContent = document.getElementById(`${tabName}-tab`);
        
        if (selectedTab) selectedTab.classList.add('active');
        if (selectedContent) selectedContent.classList.add('active');
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the WebSocket client when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.wsClient = new WebSocketClient();
});
