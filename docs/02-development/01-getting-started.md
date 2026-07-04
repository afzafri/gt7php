# Getting started

This page covers what you need, how to find the PlayStation IP, and how to run
the cockpit.

## Requirements

- PHP 8.1 or newer with `ext-sockets`, `ext-sodium`, and `ext-mbstring`
- A machine on the same local network as the PS4 or PS5
- Gran Turismo 7 running

All three extensions ship with a standard PHP build.

## Find the PlayStation IP

On the console, go to Settings, then Network, then Connection Status. The IP
looks like `192.168.1.50`.

## Run the cockpit

```bash
php tui.php 192.168.x.x
```

The cockpit shows speed, gear, rpm, throttle and brake bars, boost, fuel, temps,
and lap times, and refreshes about 15 times a second. Press Ctrl+C to quit,
which restores the cursor.

## Troubleshooting

| Symptom                 | Cause and fix                                                 |
| ----------------------- | ------------------------------------------------------------- |
| "No route to host"      | macOS cannot broadcast. Pass the PlayStation IP directly.     |
| No packets arrive       | GT7 is not running, or the machine is on a different network. |
| Status stuck on WAITING | The heartbeat is not reaching the console. Check the IP.      |
| Car shows as `Car #123` | The car list is not built. See [Car names](03-car-names.md).  |

## Next Steps

- [Scripts](02-scripts.md)
- [Car names](03-car-names.md)
