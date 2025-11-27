<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WebSocket Controller
 * 
 * Run this controller from CLI to start the WebSocket server:
 * php index.php websocket start
 */
class Websocket extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // Only allow CLI access
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be accessed via CLI');
        }
        
        $this->load->library('websocket_server');
    }
    
    /**
     * Start the WebSocket server
     */
    public function start()
    {
        echo "Starting WebSocket Server...\n";
        echo "Press Ctrl+C to stop\n\n";
        
        // Register event handlers
        $this->websocket_server->on('connection', function($client_id, $data, $server) {
            echo "Client connected: {$client_id}\n";
            
            // Send welcome message
            $server->send($client_id, [
                'event' => 'welcome',
                'message' => 'Connected to WebSocket server',
                'client_id' => $client_id,
                'timestamp' => time()
            ]);
        });
        
        $this->websocket_server->on('disconnect', function($client_id, $data, $server) {
            echo "Client disconnected: {$client_id}\n";
        });
        
        $this->websocket_server->on('message', function($client_id, $data, $server) {
            echo "Message from {$client_id}: " . print_r($data, true) . "\n";
            
            // Echo message back to sender
            $server->send($client_id, [
                'event' => 'echo',
                'original_message' => $data['message'],
                'timestamp' => time()
            ]);
        });
        
        $this->websocket_server->on('broadcast', function($client_id, $data, $server) {
            echo "Broadcasting message from {$client_id}\n";
            
            // Broadcast to all clients except sender
            $server->broadcast([
                'event' => 'broadcast',
                'message' => $data['message'] ?? '',
                'from' => $client_id,
                'timestamp' => time()
            ], $client_id);
        });
        
        $this->websocket_server->on('join_room', function($client_id, $data, $server) {
            $room = $data['room'] ?? 'general';
            $server->joinRoom($client_id, $room);
            
            // Notify room members
            $server->sendToRoom($room, [
                'event' => 'user_joined',
                'client_id' => $client_id,
                'room' => $room,
                'timestamp' => time()
            ]);
        });
        
        $this->websocket_server->on('leave_room', function($client_id, $data, $server) {
            $room = $data['room'] ?? 'general';
            $server->leaveRoom($client_id, $room);
            
            // Notify room members
            $server->sendToRoom($room, [
                'event' => 'user_left',
                'client_id' => $client_id,
                'room' => $room,
                'timestamp' => time()
            ]);
        });
        
        $this->websocket_server->on('room_message', function($client_id, $data, $server) {
            $room = $data['room'] ?? 'general';
            
            // Send message to room
            $server->sendToRoom($room, [
                'event' => 'room_message',
                'message' => $data['message'] ?? '',
                'from' => $client_id,
                'room' => $room,
                'timestamp' => time()
            ], $client_id);
        });
        
        // Start the server
        $this->websocket_server->start();
    }
    
    /**
     * Test connection
     */
    public function test()
    {
        echo "WebSocket configuration test\n";
        echo "============================\n\n";
        
        $this->load->config('websocket');
        
        echo "Host: " . $this->config->item('websocket_host') . "\n";
        echo "Port: " . $this->config->item('websocket_port') . "\n";
        echo "Max Clients: " . $this->config->item('websocket_max_clients') . "\n";
        echo "Timeout: " . $this->config->item('websocket_timeout') . " seconds\n";
        echo "\nConfiguration loaded successfully!\n";
    }
}
