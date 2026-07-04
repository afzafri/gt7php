# Telemetry format

A decrypted packet is a fixed binary layout, about 296 bytes. Values sit at
known byte offsets. This page lists the common fields and how they are read.

## Reading values

All numbers are little endian. The parser in `lib/gt7telemetry.php` uses these
`unpack` format codes:

| Code | Type                    |
| ---- | ----------------------- |
| `g`  | 32 bit float            |
| `V`  | unsigned 32 bit integer |
| `v`  | unsigned 16 bit integer |
| `C`  | single byte             |

Signed 16 and 32 bit values are read unsigned and then adjusted, so lap times
can be `-1` when unset.

## Common offsets

| Offset  | Field          | Notes                                        |
| ------- | -------------- | -------------------------------------------- |
| `0x04`  | position x/y/z | float each, world coordinates                |
| `0x3C`  | rpm            | float                                        |
| `0x44`  | current fuel   | float                                        |
| `0x48`  | fuel capacity  | float                                        |
| `0x4C`  | speed          | float, metres per second, times 3.6 for km/h |
| `0x50`  | boost          | float, minus 1                               |
| `0x58`  | water temp     | float                                        |
| `0x5C`  | oil temp       | float                                        |
| `0x60`  | tyre temps     | four floats, FL FR RL RR                     |
| `0x70`  | package id     | int, increases each packet                   |
| `0x74`  | current lap    | short                                        |
| `0x78`  | best lap       | int, milliseconds, -1 if unset               |
| `0x7C`  | last lap       | int, milliseconds                            |
| `0x8E`  | flags          | bit 0 in race, bit 1 paused                  |
| `0x90`  | gear           | low nibble current, high nibble suggested    |
| `0x91`  | throttle       | byte, divide by 2.55 for percent             |
| `0x92`  | brake          | byte, divide by 2.55 for percent             |
| `0x124` | car id         | int, maps to a car name                      |

## Example

```php
$speedKmh = 3.6 * unpack('g', substr($plain, 0x4C, 4))[1];
$rpm      = unpack('g', substr($plain, 0x3C, 4))[1];
$gearByte = unpack('C', substr($plain, 0x90, 1))[1];
$gear     = $gearByte & 0x0F;
```

The full field set is decoded by `gt7_parse()` in
[lib/gt7telemetry.php](../../lib/gt7telemetry.php).

## Next Steps

- [TUI rendering](05-tui-rendering.md)
- [Decryption](03-decryption.md)
