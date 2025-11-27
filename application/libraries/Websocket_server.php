<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WebSocket Server Library for CodeIgniter 3
 * 
 * This library provides WebSocket server functionality using PHP sockets
 */
class Websocket_server
{
    protected $CI;
    protected $config;
    protected $socket;
    protected $clients = [];
    protected $rooms = [];
    protected $handlers = [];
    
    public function __construct($config = [])
    {
        $this->CI =& get_instance();
        $this->CI->load->config('websocket');
        
        // Merge custom config with default config
        $this->config = array_merge([
            'host' => $this->CI->config->item('websocket_host'),
            'port' => $this->CI->config->item('websocket_port'),
            'max_clients' => $this->CI->config->item('websocket_max_clients'),
            'timeout' => $this->CI->config->item('websocket_timeout'),
        ], $config);
        
        $this->log('WebSocket Server initialized');
    }
    
    /**
     * Start the WebSocket server
     */
    public function start()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if ($this->socket === false) {
            $this->log('Failed to create socket: ' . socket_strerror(socket_last_error()), 'error');
            return false;
        }
        
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (!socket_bind($this->socket, $this->config['host'], $this->config['port'])) {
            $this->log('Failed to bind socket: ' . socket_strerror(socket_last_error()), 'error');
            return false;
        }
        
        if (!socket_listen($this->socket, $this->config['max_clients'])) {
            $this->log('Failed to listen on socket: ' . socket_strerror(socket_last_error()), 'error');
            return false;
        }
        
        $this->log("WebSocket Server started on {$this->config['host']}:{$this->config['port']}");
        
        $this->run();
    }
    
    /**
     * Main server loop
     */
    protected function run()
    {
        while (true) {
            $read = array_merge([$this->socket], $this->getClientSockets());
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0, 200000) < 1) {
                continue;
            }
            
            // Handle new connections
            if (in_array($this->socket, $read)) {
                $this->handleNewConnection();
                unset($read[array_search($this->socket, $read)]);
            }
            
            // Handle client messages
            foreach ($read as $client_socket) {
                $this->handleClientMessage($client_socket);
            }
        }
    }
    
    /**
     * Handle new client connection
     */
    protected function handleNewConnection()
    {
        $new_socket = socket_accept($this->socket);
        
        if ($new_socket === false) {
            $this->log('Failed to accept connection: ' . socket_strerror(socket_last_error()), 'error');
            return;
        }
        
        $header = socket_read($new_socket, 1024);
        
        if (!$this->performHandshake($new_socket, $header)) {
            socket_close($new_socket);
            return;
        }
        
        $client_id = uniqid('client_');
        $this->clients[$client_id] = [
            'socket' => $new_socket,
            'id' => $client_id,
            'rooms' => [],
            'data' => [],
            'connected_at' => time()
        ];
        
        $this->log("New client connected: {$client_id}");
        $this->trigger('connection', $client_id, []);
    }
    
    /**
     * Perform WebSocket handshake
     */
    protected function performHandshake($client_socket, $header)
    {
        if (!preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $header, $matches)) {
            return false;
        }
        
        $key = trim($matches[1]);
        $accept_key = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept: {$accept_key}\r\n\r\n";
        
        socket_write($client_socket, $upgrade, strlen($upgrade));
        
        return true;
    }
    
    /**
     * Handle client message
     */
    protected function handleClientMessage($client_socket)
    {
        $client_id = $this->getClientIdBySocket($client_socket);
        
        if (!$client_id) {
            return;
        }
        
        $data = socket_read($client_socket, 2048);
        
        if ($data === false || $data === '') {
            $this->disconnectClient($client_id);
            return;
        }
        
        $decoded = $this->decode($data);
        
        if ($decoded === false) {
            return;
        }
        
        $this->handleMessage($client_id, $decoded);
    }
    
    /**
     * Handle decoded message
     */
    protected function handleMessage($client_id, $message)
    {
        $this->log("Message from {$client_id}: {$message}");
        
        // Try to decode JSON
        $data = json_decode($message, true);
        
        if ($data && isset($data['event'])) {
            $this->trigger($data['event'], $client_id, $data);
        } else {
            $this->trigger('message', $client_id, ['message' => $message]);
        }
    }
    
    /**
     * Send message to a client
     */
    public function send($client_id, $message)
    {
        if (!isset($this->clients[$client_id])) {
            return false;
        }
        
        if (is_array($message)) {
            $message = json_encode($message);
        }
        
        $encoded = $this->encode($message);
        socket_write($this->clients[$client_id]['socket'], $encoded, strlen($encoded));
        
        return true;
    }
    
    /**
     * Broadcast message to all clients
     */
    public function broadcast($message, $exclude_client_id = null)
    {
        foreach ($this->clients as $client_id => $client) {
            if ($client_id !== $exclude_client_id) {
                $this->send($client_id, $message);
            }
        }
    }
    
    /**
     * Send message to clients in a room
     */
    public function sendToRoom($room, $message, $exclude_client_id = null)
    {
        if (!isset($this->rooms[$room])) {
            return;
        }
        
        foreach ($this->rooms[$room] as $client_id) {
            if ($client_id !== $exclude_client_id) {
                $this->send($client_id, $message);
            }
        }
    }
    
    /**
     * Join a room
     */
    public function joinRoom($client_id, $room)
    {
        if (!isset($this->clients[$client_id])) {
            return false;
        }
        
        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = [];
        }
        
        if (!in_array($client_id, $this->rooms[$room])) {
            $this->rooms[$room][] = $client_id;
            $this->clients[$client_id]['rooms'][] = $room;
            $this->log("Client {$client_id} joined room {$room}");
        }
        
        return true;
    }
    
    /**
     * Leave a room
     */
    public function leaveRoom($client_id, $room)
    {
        if (isset($this->rooms[$room])) {
            $key = array_search($client_id, $this->rooms[$room]);
            if ($key !== false) {
                unset($this->rooms[$room][$key]);
            }
        }
        
        if (isset($this->clients[$client_id])) {
            $key = array_search($room, $this->clients[$client_id]['rooms']);
            if ($key !== false) {
                unset($this->clients[$client_id]['rooms'][$key]);
            }
        }
    }
    
    /**
     * Disconnect a client
     */
    protected function disconnectClient($client_id)
    {
        if (!isset($this->clients[$client_id])) {
            return;
        }
        
        // Remove from all rooms
        foreach ($this->clients[$client_id]['rooms'] as $room) {
            $this->leaveRoom($client_id, $room);
        }
        
        socket_close($this->clients[$client_id]['socket']);
        unset($this->clients[$client_id]);
        
        $this->log("Client disconnected: {$client_id}");
        $this->trigger('disconnect', $client_id, []);
    }
    
    /**
     * Register event handler
     */
    public function on($event, $callback)
    {
        if (!isset($this->handlers[$event])) {
            $this->handlers[$event] = [];
        }
        
        $this->handlers[$event][] = $callback;
    }
    
    /**
     * Trigger event
     */
    protected function trigger($event, $client_id, $data)
    {
        if (isset($this->handlers[$event])) {
            foreach ($this->handlers[$event] as $callback) {
                call_user_func($callback, $client_id, $data, $this);
            }
        }
    }
    
    /**
     * Encode message for WebSocket
     */
    protected function encode($message)
    {
        $length = strlen($message);
        $header = chr(129); // Text frame
        
        if ($length <= 125) {
            $header .= chr($length);
        } elseif ($length <= 65535) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('J', $length);
        }
        
        return $header . $message;
    }
    
    /**
     * Decode WebSocket message
     */
    protected function decode($data)
    {
        if (strlen($data) < 2) {
            return false;
        }
        
        $length = ord($data[1]) & 127;
        $masks_index = 2;
        
        if ($length == 126) {
            $masks_index = 4;
        } elseif ($length == 127) {
            $masks_index = 10;
        }
        
        $masks = substr($data, $masks_index, 4);
        $data_index = $masks_index + 4;
        $decoded = '';
        
        for ($i = $data_index; $i < strlen($data); $i++) {
            $decoded .= $data[$i] ^ $masks[($i - $data_index) % 4];
        }
        
        return $decoded;
    }
    
    /**
     * Get client ID by socket
     */
    protected function getClientIdBySocket($socket)
    {
        foreach ($this->clients as $client_id => $client) {
            if ($client['socket'] === $socket) {
                return $client_id;
            }
        }
        
        return null;
    }
    
    /**
     * Get all client sockets
     */
    protected function getClientSockets()
    {
        $sockets = [];
        foreach ($this->clients as $client) {
            $sockets[] = $client['socket'];
        }
        return $sockets;
    }
    
    /**
     * Log message
     */
    protected function log($message, $level = 'info')
    {
        if (!$this->CI->config->item('websocket_log_enabled')) {
            return;
        }
        
        $log_path = $this->CI->config->item('websocket_log_path');
        
        if (!is_dir($log_path)) {
            mkdir($log_path, 0755, true);
        }
        
        $log_file = $log_path . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
        
        // Also echo to console
        echo $log_message;
    }
    
    /**
     * Get connected clients count
     */
    public function getClientCount()
    {
        return count($this->clients);
    }
    
    /**
     * Get all clients
     */
    public function getClients()
    {
        return $this->clients;
    }
}
