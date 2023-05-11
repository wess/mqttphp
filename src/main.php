<?php
require __DIR__ . '/../vendor/autoload.php';

use Swoole\Server;
use Swoole\WebSocket\Server as WebSocketServer;

$server = new WebSocketServer("0.0.0.0", 9501);

// Define channels and queues
$channels = [];
$queues = [];

$server->on('open', function ($server, $request) use (&$channels) {
    echo "connection open: {$request->fd}\n";
    
    // No initial subscriptions for a new client
});

$server->on('message', function ($server, $frame) use (&$channels, &$queues) {
    echo "received message: {$frame->data}\n";

    list($command, $topic, $message) = explode(':', $frame->data, 3) + [null, null, null];

    // Check if the message is a subscription request
    if ($command === 'sub') {
        // Add the subscription to the topic
        $channels[$topic][$frame->fd] = $frame->fd;
    } elseif ($command === 'pub' && isset($channels[$topic])) {
        // If it's a regular message, send it to all subscribers of the topic
        foreach ($channels[$topic] as $subscriber) {
            $server->push($subscriber, $message);
        }
    }
});

$server->on('close', function ($server, $fd) use (&$channels) {
    echo "connection close: {$fd}\n";
    
    // Remove client from all topics
    foreach ($channels as $topic => $subscribers) {
        unset($channels[$topic][$fd]);
    }
});

$server->start();
