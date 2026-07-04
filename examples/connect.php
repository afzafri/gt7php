<?php

declare(strict_types=1);

/**
 * connect.php — step 1: prove we can reach GT7's telemetry stream.
 *
 * Sends the heartbeat to the PlayStation and waits for a single telemetry
 * packet. Prints what came back, then stops. No decryption yet.
 *
 * Usage:
 *   php connect.php              # broadcast to the whole LAN (255.255.255.255)
 *   php connect.php 192.168.1.50 # target a specific PS4/PS5 IP
 */

const SEND_PORT = 33739; // app -> PlayStation (heartbeat)
const RECV_PORT = 33740; // PlayStation -> app (telemetry)

$ps5 = $argv[1] ?? '255.255.255.255';
$isBroadcast = ($ps5 === '255.255.255.255');

echo "GT7 connect test\n";
echo "  target : {$ps5}" . ($isBroadcast ? " (broadcast)\n" : "\n");
echo "  send   : port " . SEND_PORT . "\n";
echo "  listen : port " . RECV_PORT . "\n\n";

// --- create the UDP socket ---
$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($sock === false) {
    exit("socket_create failed: " . socket_strerror(socket_last_error()) . "\n");
}

if ($isBroadcast) {
    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
}

// listen for the reply on 33740
if (!socket_bind($sock, '0.0.0.0', RECV_PORT)) {
    exit("socket_bind failed: " . socket_strerror(socket_last_error($sock)) . "\n");
}

// don't block forever waiting for a packet
socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

// --- send the heartbeat ('A') ---
$sent = socket_sendto($sock, 'A', 1, 0, $ps5, SEND_PORT);
if ($sent === false) {
    exit("heartbeat send failed: " . socket_strerror(socket_last_error($sock)) . "\n");
}
echo "Heartbeat sent. Waiting for a packet (5s timeout)...\n\n";

// --- wait for one packet ---
$buf = '';
$from = '';
$fromPort = 0;
$bytes = socket_recvfrom($sock, $buf, 4096, 0, $from, $fromPort);

if ($bytes === false || $bytes === 0) {
    echo "No packet received.\n";
    echo "  - Is GT7 running and on track / in a menu that streams telemetry?\n";
    echo "  - Is this machine on the same network as the PlayStation?\n";
    echo "  - Try passing the PS5 IP directly: php connect.php <ip>\n";
    socket_close($sock);
    exit(1);
}

echo "Got a packet!\n";
echo "  from  : {$from}:{$fromPort}\n";
echo "  bytes : {$bytes}\n";
echo "\nConnection works. (Data is still encrypted — decoding comes next.)\n";

socket_close($sock);
