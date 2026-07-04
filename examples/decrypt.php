<?php

declare(strict_types=1);

/**
 * decrypt.php — step 2: receive one packet, decrypt it, validate it.
 *
 * Sends the heartbeat, grabs a single telemetry packet, decrypts it with
 * Salsa20, and checks the magic number to confirm we decoded it correctly.
 * Prints a couple of raw fields as proof. Still no full parsing yet.
 *
 * Usage:
 *   php decrypt.php 192.168.100.114
 */

require __DIR__ . '/../lib/salsa20.php';

const SEND_PORT = 33739;
const RECV_PORT = 33740;

// GT7 Salsa20 key — first 32 bytes of this string.
const GT7_KEY = 'Simulator Interface Packet GT7 ver 0.0';
// Decrypted packets start with this magic ("G7S0", little-endian).
const GT7_MAGIC = 0x47375330;

/**
 * Decrypt a raw GT7 packet. Returns the plaintext, or '' if the magic
 * number doesn't match (wrong key/nonce/not a GT7 packet).
 */
function gt7_decrypt(string $packet): string
{
    // The nonce seed lives at offset 0x40 in the *encrypted* packet.
    $iv1 = unpack('V', substr($packet, 0x40, 4))[1];
    $iv2 = $iv1 ^ 0xDEADBEAF;                       // note: BEAF, not BEEF
    $nonce = pack('V', $iv2) . pack('V', $iv1);     // [iv2][iv1], little-endian

    $plain = salsa20_xor(substr(GT7_KEY, 0, 32), $nonce, $packet);

    $magic = unpack('V', substr($plain, 0, 4))[1];
    return $magic === GT7_MAGIC ? $plain : '';
}

// ---------------------------------------------------------------------------

$ps5 = $argv[1] ?? '255.255.255.255';

echo "GT7 decrypt test\n";
echo "  target : {$ps5}\n\n";

$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($sock === false) {
    exit("socket_create failed: " . socket_strerror(socket_last_error()) . "\n");
}
if ($ps5 === '255.255.255.255') {
    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
}
if (!socket_bind($sock, '0.0.0.0', RECV_PORT)) {
    exit("socket_bind failed: " . socket_strerror(socket_last_error($sock)) . "\n");
}
socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

if (socket_sendto($sock, 'A', 1, 0, $ps5, SEND_PORT) === false) {
    exit("heartbeat send failed: " . socket_strerror(socket_last_error($sock)) . "\n");
}
echo "Heartbeat sent. Waiting for a packet...\n\n";

$buf = '';
$from = '';
$fromPort = 0;
$bytes = socket_recvfrom($sock, $buf, 4096, 0, $from, $fromPort);
socket_close($sock);

if ($bytes === false || $bytes === 0) {
    exit("No packet received. Is GT7 running?\n");
}

echo "Received {$bytes} encrypted bytes from {$from}.\n";

$plain = gt7_decrypt($buf);
if ($plain === '') {
    echo "\nDecryption FAILED — magic number didn't match.\n";
    echo "First 4 decrypted bytes were not 'G7S0'.\n";
    exit(1);
}

// Sanity fields to prove the plaintext is real telemetry.
$packageId = unpack('V', substr($plain, 0x70, 4))[1];
$rpm       = unpack('g', substr($plain, 0x3C, 4))[1];  // 'g' = little-endian float
$speedKmh  = unpack('g', substr($plain, 0x4C, 4))[1] * 3.6;

echo "\nDecryption OK — magic 'G7S0' matched. ✓\n\n";
echo "  package id : {$packageId}\n";
echo "  rpm        : " . number_format($rpm, 0) . "\n";
echo "  speed      : " . number_format($speedKmh, 1) . " km/h\n";
echo "\nWe can now read GT7 telemetry. Full field parsing comes next.\n";
