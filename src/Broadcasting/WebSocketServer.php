<?php

namespace Nemesis\Broadcasting;

class WebSocketServer {
    protected $socket;
    protected $clients = [];
    protected $host;
    protected $port;

    public function __construct($host = '127.0.0.1', $port = 8080) {
        $this->host = $host;
        $this->port = $port;
    }

    public function start() {
        $address = "tcp://{$this->host}:{$this->port}";
        $this->socket = stream_socket_server($address, $errno, $errstr);

        if (!$this->socket) {
            die("Error: $errstr ($errno)\n");
        }

        echo "WebSocket Server started on $address\n";

        while (true) {
            // Check for new connections
            $read = [$this->socket];
            $write = null;
            $except = null;
            
            // Wait for activity
            if (stream_select($read, $write, $except, 0, 10) > 0) {
                if (in_array($this->socket, $read)) {
                    $client = stream_socket_accept($this->socket);
                    if ($client) {
                        $this->handshake($client);
                        echo "New client connected\n";
                    }
                }
            }
            
            // In a real implementation: check input from connected clients here
        }
    }

    protected function handshake($client) {
        $headers = [];
        $line = fgets($client);
        while (trim($line) !== '') {
            $line = fgets($client);
        }

        $secAccept = base64_encode(pack('H*', sha1('key' . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        
        fwrite($client, $upgrade);
    }
}
