<?php

declare(strict_types=1);

/**
 * gt7telemetry.php — decode a decrypted GT7 packet into a flat array.
 *
 * Field offsets follow the GT7 "Simulator Interface" packet layout
 * (as documented by snipem/gt7dashboard and Nenkai/PDTools).
 */

require_once __DIR__ . '/salsa20.php';

const GT7_KEY = 'Simulator Interface Packet GT7 ver 0.0';
const GT7_MAGIC = 0x47375330;

/**
 * Decrypt a raw GT7 UDP packet. Returns plaintext, or '' if it isn't a
 * valid GT7 packet (magic mismatch).
 */
function gt7_decrypt(string $packet): string
{
    $iv1 = unpack('V', substr($packet, 0x40, 4))[1];
    $iv2 = $iv1 ^ 0xDEADBEAF;                    // note: BEAF, not BEEF
    $nonce = pack('V', $iv2) . pack('V', $iv1);

    $plain = salsa20_xor(substr(GT7_KEY, 0, 32), $nonce, $packet);

    $magic = unpack('V', substr($plain, 0, 4))[1];
    return $magic === GT7_MAGIC ? $plain : '';
}

/**
 * Parse decrypted telemetry into a flat associative array.
 */
function gt7_parse(string $d): array
{
    // little-endian readers
    $f   = static fn(int $o): float => unpack('g', substr($d, $o, 4))[1]; // float
    $u32 = static fn(int $o): int   => unpack('V', substr($d, $o, 4))[1];
    $u16 = static fn(int $o): int   => unpack('v', substr($d, $o, 2))[1];
    $u8  = static fn(int $o): int   => unpack('C', substr($d, $o, 1))[1];
    $s32 = static function (int $o) use ($u32): int {
        $v = $u32($o);
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    };
    $s16 = static function (int $o) use ($u16): int {
        $v = $u16($o);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    };

    $gearByte = $u8(0x90);
    $flags    = $u8(0x8E);

    // wheel radial velocity (rad/s) × tyre diameter → tyre surface speed (km/h)
    $tyreDiaFL = $f(0xB4); $tyreDiaFR = $f(0xB8);
    $tyreDiaRL = $f(0xBC); $tyreDiaRR = $f(0xC0);
    $tyreSpeedFL = abs(3.6 * $tyreDiaFL * $f(0xA4));
    $tyreSpeedFR = abs(3.6 * $tyreDiaFR * $f(0xA8));
    $tyreSpeedRL = abs(3.6 * $tyreDiaRL * $f(0xAC));
    $tyreSpeedRR = abs(3.6 * $tyreDiaRR * $f(0xB0));

    $carSpeed = 3.6 * $f(0x4C); // m/s → km/h

    return [
        // identity / timing
        'package_id'      => $s32(0x70),
        'car_id'          => $s32(0x124),
        'in_race'         => (bool) ($flags & 0x01),
        'is_paused'       => (bool) (($flags >> 1) & 0x01),
        'current_lap'     => $s16(0x74),
        'total_laps'      => $s16(0x76),
        'best_lap_ms'     => $s32(0x78),
        'last_lap_ms'     => $s32(0x7C),
        'time_of_day_ms'  => $s32(0x80),
        'position'        => $s16(0x84),
        'total_positions' => $s16(0x86),

        // engine / drivetrain
        'rpm'             => $f(0x3C),
        'rpm_rev_warning' => $u16(0x88),
        'rpm_rev_limiter' => $u16(0x8A),
        'est_top_speed'   => $s16(0x8C),
        'speed_kmh'       => $carSpeed,
        'boost'           => $f(0x50) - 1.0,
        'current_gear'    => $gearByte & 0x0F,
        'suggested_gear'  => $gearByte >> 4,
        'throttle'        => $u8(0x91) / 2.55, // 0..100 %
        'brake'           => $u8(0x92) / 2.55, // 0..100 %
        'clutch'          => $f(0xF4),
        'clutch_engaged'  => $f(0xF8),
        'rpm_after_clutch' => $f(0xFC),

        // fluids / temps
        'oil_pressure'    => $f(0x54),
        'water_temp'      => $f(0x58),
        'oil_temp'        => $f(0x5C),
        'ride_height_mm'  => 1000.0 * $f(0x38),

        // fuel
        'current_fuel'    => $f(0x44),
        'fuel_capacity'   => $f(0x48),

        // tyres
        'tyre_temp_fl'    => $f(0x60),
        'tyre_temp_fr'    => $f(0x64),
        'tyre_temp_rl'    => $f(0x68),
        'tyre_temp_rr'    => $f(0x6C),
        'tyre_dia_fl'     => $tyreDiaFL,
        'tyre_dia_fr'     => $tyreDiaFR,
        'tyre_dia_rl'     => $tyreDiaRL,
        'tyre_dia_rr'     => $tyreDiaRR,
        'tyre_speed_fl'   => $tyreSpeedFL,
        'tyre_speed_fr'   => $tyreSpeedFR,
        'tyre_speed_rl'   => $tyreSpeedRL,
        'tyre_speed_rr'   => $tyreSpeedRR,

        // suspension
        'suspension_fl'   => $f(0xC4),
        'suspension_fr'   => $f(0xC8),
        'suspension_rl'   => $f(0xCC),
        'suspension_rr'   => $f(0xD0),

        // position / motion
        'pos_x'           => $f(0x04),
        'pos_y'           => $f(0x08),
        'pos_z'           => $f(0x0C),
        'vel_x'           => $f(0x10),
        'vel_y'           => $f(0x14),
        'vel_z'           => $f(0x18),
        'rot_pitch'       => $f(0x1C),
        'rot_yaw'         => $f(0x20),
        'rot_roll'        => $f(0x24),
        'ang_vel_x'       => $f(0x2C),
        'ang_vel_y'       => $f(0x30),
        'ang_vel_z'       => $f(0x34),

        // gear ratios
        'gear_ratios'     => [
            $f(0x104), $f(0x108), $f(0x10C), $f(0x110),
            $f(0x114), $f(0x118), $f(0x11C), $f(0x120),
        ],
    ];
}

/**
 * Format a lap time in ms as m:ss.mmm, or "--" when unset (-1).
 */
function gt7_lap_time(int $ms): string
{
    if ($ms < 0) {
        return '--';
    }
    $minutes = intdiv($ms, 60000);
    $seconds = ($ms % 60000) / 1000;
    return sprintf('%d:%06.3f', $minutes, $seconds);
}

/**
 * Human-readable gear label (0 = neutral/reverse handling).
 */
function gt7_gear_label(int $gear): string
{
    return match ($gear) {
        0       => 'N',
        15      => '-',   // no gear / unknown
        default => (string) $gear,
    };
}
