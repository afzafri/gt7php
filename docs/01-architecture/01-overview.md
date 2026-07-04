# Overview

gt7php reads Gran Turismo 7 telemetry from a PlayStation on the local network
and paints a live cockpit in the terminal. This page shows the full pipeline and
where each piece of code lives.

## The pipeline

Every stage runs in a single loop, roughly 60 times a second.

```text
heartbeat  ->  receive  ->  decrypt  ->  parse  ->  render
 (33739)      (33740)      (Salsa20)   (offsets)   (ANSI)
```

1. Send a one byte heartbeat to the PlayStation so it starts streaming.
2. Receive an encrypted UDP packet (about 296 bytes).
3. Decrypt it with Salsa20 and check the magic number.
4. Parse the fixed binary layout into readable values.
5. Render the cockpit, throttled to about 15 frames a second.

## Where the code lives

| File                    | Responsibility                                      |
| ----------------------- | --------------------------------------------------- |
| `tui.php`               | The main loop and the ANSI cockpit renderer         |
| `lib/salsa20.php`       | Pure PHP Salsa20 stream cipher                      |
| `lib/gt7telemetry.php`  | Packet decryption and field parsing                 |
| `lib/cars.php`          | Car id to name lookup from `data/cars.json`         |
| `tools/scrape-cars.php` | Builds `data/cars.json` from the GT7 site           |
| `examples/`             | Small scripts that each show one stage in isolation |

## Design notes

- No external dependencies. Salsa20 is implemented in PHP because the built in
  libsodium only exposes XSalsa20, which uses a different nonce size.
- The receive loop decodes every packet but repaints far less often, so the
  terminal never has to keep up with 60 frames a second.
- The scraper is separate from the TUI. It runs on demand and writes a JSON
  file the TUI reads. The TUI never touches the network for car names.

## Next Steps

- [Protocol](02-protocol.md)
- [Decryption](03-decryption.md)
- [Telemetry format](04-telemetry-format.md)
- [TUI rendering](05-tui-rendering.md)
