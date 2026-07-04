<?php

declare(strict_types=1);

/**
 * tui.php — step 4: live single-screen cockpit.
 *
 * Streams GT7 telemetry and paints a terminal cockpit in real time.
 * Receives at ~60Hz, repaints at ~15fps. Ctrl+C to quit.
 *
 * Usage:
 *   php tui.php 192.168.100.114
 */

require __DIR__ . '/lib/gt7telemetry.php';

const SEND_PORT = 33739;
const RECV_PORT = 33740;
const INNER_W   = 45; // characters between the box borders

// ── ANSI helpers ───────────────────────────────────────────────────────────
const ESC   = "\033[";
const RESET = "\033[0m";
const BOLD  = "\033[1m";
const DIM   = "\033[2m";
const C_GRN = "\033[92m";
const C_YEL = "\033[93m";
const C_RED = "\033[91m";
const C_CYN = "\033[96m";
const C_WHT = "\033[97m";
const C_GRY = "\033[90m";

/** Visible width, ignoring ANSI color codes. */
function vwidth(string $s): int
{
    return mb_strwidth(preg_replace('/\033\[[0-9;]*m/', '', $s) ?? $s);
}

/** Pad a (possibly colored) string to INNER_W visible columns. */
function pad(string $s): string
{
    $w = vwidth($s);
    return $w < INNER_W ? $s . str_repeat(' ', INNER_W - $w) : $s;
}

/** Wrap a content line in box borders. */
function row(string $s): string
{
    return C_GRY . '│' . RESET . pad($s) . C_GRY . '│' . RESET . "\n";
}

/** A horizontal bar of the given width; `$frac` in 0..1, filled part colored. */
function bar(float $frac, int $width, string $color): string
{
    $frac = max(0.0, min(1.0, $frac));
    $fill = (int) round($frac * $width);
    return $color . str_repeat('█', $fill)
        . C_GRY . str_repeat('░', $width - $fill) . RESET;
}

// ── terminal setup / teardown ──────────────────────────────────────────────
function cleanup(): void
{
    echo ESC . "?25h";  // show cursor
    echo RESET . "\n";
}

$cleaned = false;
$cleanupOnce = function () use (&$cleaned) {
    if (!$cleaned) {
        $cleaned = true;
        cleanup();
    }
};
register_shutdown_function($cleanupOnce);
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () use ($cleanupOnce) {
        $cleanupOnce();
        exit(0);
    });
}

// ── frame builder ──────────────────────────────────────────────────────────
function render(?array $t, string $status): string
{
    $border = C_GRY . str_repeat('─', INNER_W) . RESET;
    $top    = C_GRY . '┌' . RESET . $border . C_GRY . '┐' . RESET . "\n";
    $mid    = C_GRY . '├' . RESET . $border . C_GRY . '┤' . RESET . "\n";
    $bot    = C_GRY . '└' . RESET . $border . C_GRY . '┘' . RESET . "\n";

    $out = ESC . 'H';           // cursor home (overwrite, no full clear = no flicker)
    $out .= $top;
    $out .= row('  ' . BOLD . C_WHT . 'GT7 COCKPIT' . RESET
        . str_repeat(' ', 22) . $status);
    $out .= $mid;

    if ($t === null) {
        $out .= row('');
        $out .= row('  ' . DIM . 'waiting for telemetry…' . RESET);
        $out .= row('');
        for ($i = 0; $i < 9; $i++) {
            $out .= row('');
        }
        $out .= $bot;
        return $out;
    }

    $limiter = max(1, $t['rpm_rev_limiter']);
    $warn    = $t['rpm_rev_warning'] > 0 ? $t['rpm_rev_warning'] : (int) ($limiter * 0.9);
    $rpm     = (int) round($t['rpm']);
    $rpmCol  = $rpm >= $limiter ? C_RED : ($rpm >= $warn ? C_YEL : C_GRN);

    $out .= row(sprintf(
        '  %sSPEED%s %s%3d%s km/h    %sGEAR%s %s%s%s    %s%4d%s rpm',
        DIM, RESET, BOLD . C_CYN, (int) round($t['speed_kmh']), RESET,
        DIM, RESET, BOLD . C_WHT, gt7_gear_label($t['current_gear']), RESET,
        $rpmCol, $rpm, RESET
    ));
    $out .= row('');
    $out .= row('  ' . DIM . 'RPM' . RESET . ' ' . bar($rpm / $limiter, 30, $rpmCol));
    $out .= row(sprintf('  %sTHR%s %s  %s%3d%%%s',
        DIM, RESET, bar($t['throttle'] / 100, 24, C_GRN), C_GRN, (int) round($t['throttle']), RESET));
    $out .= row(sprintf('  %sBRK%s %s  %s%3d%%%s',
        DIM, RESET, bar($t['brake'] / 100, 24, C_RED), C_RED, (int) round($t['brake']), RESET));
    $out .= row('');
    $out .= row(sprintf('  %sBOOST%s %+.2f bar     %sFUEL%s %.1f / %.1f',
        DIM, RESET, $t['boost'], DIM, RESET, $t['current_fuel'], $t['fuel_capacity']));
    $out .= row(sprintf('  %sWATER%s %.0f°C    %sOIL%s %.0f°C',
        DIM, RESET, $t['water_temp'], DIM, RESET, $t['oil_temp']));
    $out .= row(sprintf('  %sTYRE%s  FL %2.0f  FR %2.0f  RL %2.0f  RR %2.0f °C',
        DIM, RESET, $t['tyre_temp_fl'], $t['tyre_temp_fr'], $t['tyre_temp_rl'], $t['tyre_temp_rr']));
    $out .= row('');
    $out .= row(sprintf('  %sLAP%s %s/%s   %sLAST%s %s   %sBEST%s %s',
        DIM, RESET,
        $t['current_lap'] < 0 ? '-' : (string) $t['current_lap'],
        $t['total_laps'] <= 0 ? '-' : (string) $t['total_laps'],
        DIM, RESET, gt7_lap_time($t['last_lap_ms']),
        DIM, RESET, gt7_lap_time($t['best_lap_ms'])));
    $out .= $bot;

    return $out;
}

// ── connection ─────────────────────────────────────────────────────────────
// Allow the file to be required for testing without starting the loop.
if (defined('GT7_TUI_NO_RUN')) {
    return;
}

$ps5 = $argv[1] ?? '255.255.255.255';

$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($ps5 === '255.255.255.255') {
    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
}
if (!socket_bind($sock, '0.0.0.0', RECV_PORT)) {
    exit("socket_bind failed: " . socket_strerror(socket_last_error($sock)) . "\n");
}
// short timeout so the loop keeps repainting even with no data
socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);

$sendHeartbeat = fn() => socket_sendto($sock, 'A', 1, 0, $ps5, SEND_PORT);
$sendHeartbeat();

echo ESC . "?25l";  // hide cursor
echo ESC . "2J";    // clear screen once

$state          = null;
$packets        = 0;
$lastPacketTime = 0.0;
$lastDraw       = 0.0;

while (true) {
    $buf = '';
    $from = '';
    $fromPort = 0;
    $bytes = @socket_recvfrom($sock, $buf, 4096, 0, $from, $fromPort);

    if ($bytes !== false && $bytes > 0) {
        $plain = gt7_decrypt($buf);
        if ($plain !== '') {
            $state = gt7_parse($plain);
            $lastPacketTime = microtime(true);
            if (++$packets > 100) {
                $sendHeartbeat();
                $packets = 0;
            }
        }
    } else {
        // timed out → re-arm the stream
        $sendHeartbeat();
        $packets = 0;
    }

    $now = microtime(true);
    if ($now - $lastDraw >= 0.066) { // ~15 fps
        $connected = ($now - $lastPacketTime) <= 1.0;
        if ($connected) {
            $status = C_GRN . '● LIVE' . RESET;
        } elseif ($state !== null) {
            $status = C_RED . '● NO SIGNAL' . RESET;
        } else {
            $status = C_YEL . '● WAITING' . RESET;
        }
        echo render($state, $status);
        $lastDraw = $now;
    }
}
