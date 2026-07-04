# Decryption

Every telemetry packet is encrypted with Salsa20. This page covers the cipher,
how the nonce is derived from the packet, and the magic number that confirms a
successful decode.

## Why a custom Salsa20

GT7 uses plain Salsa20 with an 8 byte nonce. PHP's built in libsodium only
exposes XSalsa20, which uses a 24 byte nonce and is not compatible. So Salsa20
is implemented directly in `lib/salsa20.php`. It is verified against the
official ECRYPT Salsa20 test vectors.

## The key

The key is the first 32 bytes of a fixed string:

```php
const GT7_KEY = 'Simulator Interface Packet GT7 ver 0.0';
```

## The nonce

The nonce seed lives at offset `0x40` in the encrypted packet. Read it as a
little endian integer, XOR with `0xDEADBEAF` (note: BEAF, not BEEF), and lay the
two values out little endian.

```php
$iv1 = unpack('V', substr($packet, 0x40, 4))[1];
$iv2 = $iv1 ^ 0xDEADBEAF;
$nonce = pack('V', $iv2) . pack('V', $iv1);
```

## Decrypt and validate

Salsa20 is a stream cipher, so the same XOR operation decrypts the packet. A
correct decode starts with the magic number `0x47375330`, which is the ASCII
`G7S0`. Anything else means a wrong key, wrong nonce, or a non GT7 packet.

```php
const GT7_MAGIC = 0x47375330;

function gt7_decrypt(string $packet): string
{
    $iv1 = unpack('V', substr($packet, 0x40, 4))[1];
    $iv2 = $iv1 ^ 0xDEADBEAF;
    $nonce = pack('V', $iv2) . pack('V', $iv1);

    $plain = salsa20_xor(substr(GT7_KEY, 0, 32), $nonce, $packet);

    $magic = unpack('V', substr($plain, 0, 4))[1];
    return $magic === GT7_MAGIC ? $plain : '';
}
```

## Next Steps

- [Telemetry format](04-telemetry-format.md)
- [Protocol](02-protocol.md)
