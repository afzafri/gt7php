# gt7php

> Gran Turismo 7 telemetry in your terminal, written in PHP.

Reads the live telemetry GT7 streams over UDP on the local network and paints a
cockpit in the terminal.

Why PHP? Why not.

```
┌─────────────────────────────────────────────┐
│  GT7 COCKPIT                      ● LIVE     │
├─────────────────────────────────────────────┤
│  SPEED 248 km/h    GEAR 4    7320 rpm        │
│                                              │
│  RPM ███████████████████████████░░░          │
│  THR ████████████████████░░░░   82%          │
│  BRK ░░░░░░░░░░░░░░░░░░░░░░░░    0%           │
│                                              │
│  BOOST +0.45 bar     FUEL 42.3 / 60.0        │
│  WATER 92°C    OIL 104°C                      │
│  TYRE  FL 78  FR 81  RL 74  RR 76 °C         │
│                                              │
│  LAP 3/5   LAST 1:23.456   BEST 1:22.104     │
└─────────────────────────────────────────────┘
```

## Features

- Live cockpit at ~15fps: speed, rpm, gear, boost, fuel, temps, lap times
- RPM bar shifts yellow near the shift point, red at the limiter
- Throttle and brake bars
- Pure PHP Salsa20 decryption, no external dependencies
- Ctrl+C to quit, cleanly

## Requirements

- PHP 8.1+ (`ext-sockets`, `ext-sodium`, `ext-mbstring`)
- Same network as the PS4/PS5
- GT7 running

## Quick Start

Grab the PlayStation IP from Settings → Network → Connection Status, then:

```
php tui.php 192.168.x.x
```

The `examples/` folder has smaller scripts, in order of how much they do:

| Script | What it does |
| --- | --- |
| `php examples/connect.php <ip>` | proves the connection works |
| `php examples/decrypt.php <ip>` | decrypts one packet and validates it |
| `php examples/read.php <ip>` | dumps every parsed field once |
| `php tui.php <ip>` | the live cockpit |

## How it works

GT7 streams telemetry over UDP. Send a heartbeat byte to port 33739 and the
console streams ~60 encrypted packets a second back to port 33740. Every packet
is Salsa20-encrypted and carries a fixed binary layout, decoded here into
speed, rpm, gear, temps, fuel, lap times and the rest.
