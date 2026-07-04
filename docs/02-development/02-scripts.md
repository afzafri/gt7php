# Scripts

The `examples/` folder has small scripts that each show one stage of the
pipeline. They are useful for learning how the tool works and for debugging a
connection.

## The scripts

Run each with the PlayStation IP as the argument.

| Script                          | What it does                                        |
| ------------------------------- | --------------------------------------------------- |
| `php examples/connect.php <ip>` | Sends the heartbeat and confirms one packet arrives |
| `php examples/decrypt.php <ip>` | Decrypts one packet and checks the magic number     |
| `php examples/read.php <ip>`    | Decodes one packet and prints every parsed field    |
| `php tui.php <ip>`              | The live cockpit                                    |

## Suggested order

Work up from the smallest when something is not working:

1. `connect.php` proves the network path and the handshake.
2. `decrypt.php` proves the cipher and packet validity.
3. `read.php` proves the field parsing looks sane.
4. `tui.php` is the full experience.

## Example output

```text
$ php examples/decrypt.php 192.168.1.50
Received 296 encrypted bytes from 192.168.1.50.

Decryption OK - magic 'G7S0' matched.

  package id : 1478355
  rpm        : 1,026
  speed      : 9.3 km/h
```

## Next Steps

- [Getting started](01-getting-started.md)
- [Car names](03-car-names.md)
- [Architecture overview](../01-architecture/01-overview.md)
