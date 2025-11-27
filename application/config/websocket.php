<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| WebSocket Configuration
|--------------------------------------------------------------------------
|
| This file contains the configuration for WebSocket server
|
*/

$config['websocket_host'] = '0.0.0.0';
$config['websocket_port'] = 8080;
$config['websocket_max_clients'] = 100;
$config['websocket_timeout'] = 300; // seconds

// SSL/TLS Configuration (optional)
$config['websocket_ssl_enabled'] = FALSE;
$config['websocket_ssl_cert'] = '';
$config['websocket_ssl_key'] = '';
$config['websocket_ssl_passphrase'] = '';

// CORS Configuration
$config['websocket_cors_enabled'] = TRUE;
$config['websocket_cors_origins'] = ['*']; // Use specific domains in production

// Authentication
$config['websocket_auth_enabled'] = TRUE;
$config['websocket_auth_token_key'] = 'auth_token';

// Logging
$config['websocket_log_enabled'] = TRUE;
$config['websocket_log_path'] = APPPATH . 'logs/websocket/';

// Heartbeat/Ping Configuration
$config['websocket_ping_interval'] = 30; // seconds
$config['websocket_ping_timeout'] = 10; // seconds

// Message Queue Configuration
$config['websocket_queue_enabled'] = FALSE;
$config['websocket_queue_driver'] = 'redis'; // redis, database, file

// Redis Configuration (if queue is enabled)
$config['websocket_redis_host'] = '127.0.0.1';
$config['websocket_redis_port'] = 6379;
$config['websocket_redis_password'] = '';
$config['websocket_redis_database'] = 0;

// Room/Channel Configuration
$config['websocket_rooms_enabled'] = TRUE;
$config['websocket_default_room'] = 'general';
