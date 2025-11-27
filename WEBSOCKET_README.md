# CodeIgniter 3 WebSocket Implementation

This project contains a complete WebSocket implementation for CodeIgniter 3, including server library, configuration, controller, and comprehensive testing tools.

## 📁 Project Structure

```
ci3-websocket/
├── application/
│   ├── config/
│   │   └── websocket.php          # WebSocket configuration
│   ├── controllers/
│   │   └── Websocket.php          # CLI controller to manage WebSocket server
│   └── libraries/
│       └── Websocket_server.php   # WebSocket server library
├── test_websocket.html            # HTML test client
├── websocket_client.js            # JavaScript client implementation
└── WEBSOCKET_README.md            # This file
```

## 🚀 Quick Start

### 1. Test Configuration

First, verify that the WebSocket configuration is loaded correctly:

```bash
php index.php websocket test
```

This will display your WebSocket configuration settings.

### 2. Start the WebSocket Server

Run the WebSocket server from the command line:

```bash
php index.php websocket start
```

The server will start on `ws://localhost:8080` (configurable in `application/config/websocket.php`).

You should see:
```
Starting WebSocket Server...
Press Ctrl+C to stop

WebSocket Server started on 0.0.0.0:8080
```

### 3. Test with HTML Client

1. Open `test_websocket.html` in your browser (you can open multiple tabs to test multiple clients)
2. Click the "Connect" button
3. Start testing different features:
   - **Simple Messages**: Send and receive echo messages
   - **Broadcast**: Send messages to all connected clients
   - **Room Chat**: Join rooms and chat with room members
   - **Logs**: View detailed connection and message logs

## ⚙️ Configuration

Edit `application/config/websocket.php` to customize your WebSocket server:

```php
// Basic Configuration
$config['websocket_host'] = '0.0.0.0';        // Bind address
$config['websocket_port'] = 8080;             // Server port
$config['websocket_max_clients'] = 100;       // Maximum concurrent clients
$config['websocket_timeout'] = 300;           // Connection timeout (seconds)

// SSL/TLS (for wss://)
$config['websocket_ssl_enabled'] = FALSE;
$config['websocket_ssl_cert'] = '';
$config['websocket_ssl_key'] = '';

// CORS
$config['websocket_cors_enabled'] = TRUE;
$config['websocket_cors_origins'] = ['*'];    // Change to specific domains in production

// Authentication
$config['websocket_auth_enabled'] = TRUE;

// Logging
$config['websocket_log_enabled'] = TRUE;
$config['websocket_log_path'] = APPPATH . 'logs/websocket/';

// Heartbeat/Ping
$config['websocket_ping_interval'] = 30;      // Ping interval (seconds)
$config['websocket_ping_timeout'] = 10;       // Ping timeout (seconds)

// Room/Channel Support
$config['websocket_rooms_enabled'] = TRUE;
$config['websocket_default_room'] = 'general';
```

## 📡 WebSocket Events

### Server → Client Events

| Event | Description | Data |
|-------|-------------|------|
| `welcome` | Sent when client connects | `client_id`, `message`, `timestamp` |
| `echo` | Echo response to simple message | `original_message`, `timestamp` |
| `broadcast` | Broadcast message from another client | `message`, `from`, `timestamp` |
| `user_joined` | User joined a room | `client_id`, `room`, `timestamp` |
| `user_left` | User left a room | `client_id`, `room`, `timestamp` |
| `room_message` | Message in a room | `message`, `from`, `room`, `timestamp` |

### Client → Server Events

| Event | Description | Required Data |
|-------|-------------|---------------|
| `message` | Send a simple message | `message` |
| `broadcast` | Broadcast to all clients | `message` |
| `join_room` | Join a room/channel | `room` |
| `leave_room` | Leave a room/channel | `room` |
| `room_message` | Send message to room | `room`, `message` |

## 🧪 Testing Scenarios

### Test 1: Basic Connection
1. Start the server
2. Open the HTML test client
3. Click "Connect"
4. Verify you receive a welcome message with your client ID

### Test 2: Simple Echo Messages
1. Connect to the server
2. Go to "Simple Messages" tab
3. Type a message and click "Send"
4. You should receive an echo response

### Test 3: Broadcasting
1. Open the test client in 2-3 different browser tabs
2. Connect all clients
3. Go to "Broadcast" tab on any client
4. Send a broadcast message
5. Verify all OTHER clients receive the message (sender doesn't receive their own broadcast)

### Test 4: Room/Channel Communication
1. Open the test client in 2-3 different browser tabs
2. Connect all clients
3. Have clients 1 and 2 join room "test-room"
4. Have client 3 join room "other-room"
5. Send a room message from client 1 to "test-room"
6. Verify only client 2 receives the message (not client 3)

### Test 5: Connection Statistics
1. Connect to the server
2. Send various messages
3. Monitor the statistics panel:
   - Sent count
   - Received count
   - Uptime

### Test 6: Multiple Rooms
1. Connect a client
2. Join multiple rooms (e.g., "general", "support", "sales")
3. Verify the room list updates correctly
4. Leave a room and verify it's removed from the list

## 🔌 WebSocket Server API

### Using the Library in Your Code

```php
$this->load->library('websocket_server');

// Register event handlers
$this->websocket_server->on('connection', function($client_id, $data, $server) {
    // Handle new connection
    echo "Client connected: {$client_id}\n";
});

$this->websocket_server->on('message', function($client_id, $data, $server) {
    // Handle message
    $server->send($client_id, ['response' => 'Message received']);
});

// Start the server
$this->websocket_server->start();
```

### Available Methods

```php
// Send message to a specific client
$this->websocket_server->send($client_id, $message);

// Broadcast to all clients (optionally exclude sender)
$this->websocket_server->broadcast($message, $exclude_client_id);

// Send to all clients in a room
$this->websocket_server->sendToRoom($room, $message, $exclude_client_id);

// Join a room
$this->websocket_server->joinRoom($client_id, $room);

// Leave a room
$this->websocket_server->leaveRoom($client_id, $room);

// Get connected clients count
$count = $this->websocket_server->getClientCount();

// Get all clients
$clients = $this->websocket_server->getClients();
```

## 🛠️ JavaScript Client API

### Basic Usage

```javascript
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => {
    console.log('Connected');
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};

// Send message
ws.send(JSON.stringify({
    event: 'message',
    message: 'Hello World'
}));
```

## 📝 Production Considerations

1. **Security**
   - Enable SSL/TLS for production (`wss://` instead of `ws://`)
   - Implement proper authentication
   - Restrict CORS origins to specific domains
   - Validate all incoming messages

2. **Performance**
   - Adjust `max_clients` based on server capacity
   - Consider using Redis for message queuing (configure in config file)
   - Monitor server logs for performance issues

3. **Monitoring**
   - Check logs at `application/logs/websocket/`
   - Monitor client connections
   - Track message throughput

4. **Deployment**
   - Run WebSocket server as a background process
   - Use process managers like Supervisor or systemd
   - Set up automatic restart on failure

## 🐛 Troubleshooting

### Server won't start
- Check if port 8080 is already in use
- Verify PHP has socket extension enabled
- Check file permissions for log directory

### Clients can't connect
- Verify server is running
- Check firewall settings
- Ensure WebSocket URL is correct (ws:// not http://)

### Messages not being received
- Check server logs for errors
- Verify client is properly connected
- Ensure messages are properly JSON formatted

## 📚 Additional Resources

- [WebSocket Protocol RFC 6455](https://tools.ietf.org/html/rfc6455)
- [MDN WebSocket API](https://developer.mozilla.org/en-US/docs/Web/API/WebSocket)
- [CodeIgniter 3 Documentation](https://codeigniter.com/userguide3/)

## 📄 License

This implementation is provided as-is for use with CodeIgniter 3 projects.

## 🤝 Support

For issues or questions:
1. Check the troubleshooting section
2. Review server logs
3. Test with the provided HTML client
4. Verify configuration settings

---

**Note**: This is a basic WebSocket implementation. For production use with high traffic, consider using established solutions like Socket.IO with Node.js or Laravel Echo Server.
