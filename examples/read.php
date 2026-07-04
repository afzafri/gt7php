<?php

declare(strict_types=1);

/**
 * read.php — step 3: read one packet and print every parsed field.
 *
 * Connect, decrypt, parse, dump everything once. Use this to eyeball that
 * each value looks sane before we build the live TUI.
 *
 * Usage:
 *   php read.php 192.168.100.114
 */

require __DIR__ . '/../lib/gt7telemetry.php';

const SEND_PORT = 33739;
const RECV_PORT = 33740;

$ps5 = $argv[1] ?? '255.255.255.255';

$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($ps5 === '255.255.255.255') {
    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
}
socket_bind($sock, '0.0.0.0', RECV_PORT);
socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
socket_sendto($sock, 'A', 1, 0, $ps5, SEND_PORT);

$buf = '';
$from = '';
$fromPort = 0;
$bytes = socket_recvfrom($sock, $buf, 4096, 0, $from, $fromPort);
socket_close($sock);

if ($bytes === false || $bytes === 0) {
    exit("No packet received. Is GT7 running?\n");
}

$plain = gt7_decrypt($buf);
if ($plain === '') {
    exit("Decryption failed (magic mismatch).\n");
}

$t = gt7_parse($plain);

$line = fn(string $label, string $val) => printf("  %-16s %s\n", $label, $val);

echo "\n=== GT7 telemetry ===  (from {$from}, {$bytes} bytes)\n";

echo "\n[ status ]\n";
$line('package id', (string) $t['package_id']);
$line('in race', $t['in_race'] ? 'yes' : 'no');
$line('paused', $t['is_paused'] ? 'yes' : 'no');
$line('lap', "{$t['current_lap']} / {$t['total_laps']}");
$line('position', "{$t['position']} / {$t['total_positions']}");
$line('best lap', gt7_lap_time($t['best_lap_ms']));
$line('last lap', gt7_lap_time($t['last_lap_ms']));

echo "\n[ engine / drive ]\n";
$line('speed', number_format($t['speed_kmh'], 1) . ' km/h');
$line('rpm', number_format($t['rpm'], 0));
$line('rev warning', number_format($t['rpm_rev_warning']) . ' rpm');
$line('rev limiter', number_format($t['rpm_rev_limiter']) . ' rpm');
$line('gear', gt7_gear_label($t['current_gear']) . '  (suggest ' . gt7_gear_label($t['suggested_gear']) . ')');
$line('throttle', number_format($t['throttle'], 0) . ' %');
$line('brake', number_format($t['brake'], 0) . ' %');
$line('boost', number_format($t['boost'], 2) . ' bar');
$line('est top speed', $t['est_top_speed'] . ' km/h');
$line('clutch', number_format($t['clutch'], 2));

echo "\n[ fluids / temps ]\n";
$line('water temp', number_format($t['water_temp'], 1) . ' °C');
$line('oil temp', number_format($t['oil_temp'], 1) . ' °C');
$line('oil pressure', number_format($t['oil_pressure'], 2));
$line('ride height', number_format($t['ride_height_mm'], 1) . ' mm');

echo "\n[ fuel ]\n";
$line('fuel', number_format($t['current_fuel'], 1) . ' / ' . number_format($t['fuel_capacity'], 1));

echo "\n[ tyre temps ]\n";
$line('front', number_format($t['tyre_temp_fl'], 1) . '  |  ' . number_format($t['tyre_temp_fr'], 1) . ' °C');
$line('rear', number_format($t['tyre_temp_rl'], 1) . '  |  ' . number_format($t['tyre_temp_rr'], 1) . ' °C');

echo "\n[ position ]\n";
$line('x / y / z', sprintf('%.1f  %.1f  %.1f', $t['pos_x'], $t['pos_y'], $t['pos_z']));

echo "\n[ gear ratios ]\n";
$ratios = array_map(fn($r) => number_format($r, 2), $t['gear_ratios']);
$line('1-8', implode('  ', $ratios));

echo "\ncar id: {$t['car_id']}\n\n";
