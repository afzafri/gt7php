<?php

declare(strict_types=1);

/**
 * Salsa20 stream cipher (256-bit key, 8-byte nonce) — pure PHP.
 *
 * GT7 encrypts its telemetry with plain Salsa20. PHP's libsodium only exposes
 * XSalsa20 (24-byte nonce), which is incompatible, so we implement Salsa20 here.
 *
 * Verified against the official ECRYPT Salsa20 256-bit test vectors.
 */

/**
 * Generate one 64-byte Salsa20 keystream block for the given block counter.
 */
function salsa20_block(string $key, string $nonce, int $counter): string
{
    $k = array_values(unpack('V8', $key));   // key as 8 little-endian words
    $n = array_values(unpack('V2', $nonce)); // nonce as 2 little-endian words

    // "expand 32-byte k"
    $s0 = 0x61707865; $s1 = 0x3320646e; $s2 = 0x79622d32; $s3 = 0x6b206574;

    $x = [
        $s0,   $k[0], $k[1], $k[2],
        $k[3], $s1,   $n[0], $n[1],
        $counter & 0xffffffff, ($counter >> 32) & 0xffffffff, $s2, $k[4],
        $k[5], $k[6], $k[7], $s3,
    ];
    $j = $x;

    $rotl = static fn(int $v, int $c): int =>
        ((($v << $c) | (($v & 0xffffffff) >> (32 - $c))) & 0xffffffff);

    for ($i = 0; $i < 10; $i++) {
        // column rounds
        $x[4]  ^= $rotl(($x[0]  + $x[12]) & 0xffffffff, 7);
        $x[8]  ^= $rotl(($x[4]  + $x[0])  & 0xffffffff, 9);
        $x[12] ^= $rotl(($x[8]  + $x[4])  & 0xffffffff, 13);
        $x[0]  ^= $rotl(($x[12] + $x[8])  & 0xffffffff, 18);

        $x[9]  ^= $rotl(($x[5]  + $x[1])  & 0xffffffff, 7);
        $x[13] ^= $rotl(($x[9]  + $x[5])  & 0xffffffff, 9);
        $x[1]  ^= $rotl(($x[13] + $x[9])  & 0xffffffff, 13);
        $x[5]  ^= $rotl(($x[1]  + $x[13]) & 0xffffffff, 18);

        $x[14] ^= $rotl(($x[10] + $x[6])  & 0xffffffff, 7);
        $x[2]  ^= $rotl(($x[14] + $x[10]) & 0xffffffff, 9);
        $x[6]  ^= $rotl(($x[2]  + $x[14]) & 0xffffffff, 13);
        $x[10] ^= $rotl(($x[6]  + $x[2])  & 0xffffffff, 18);

        $x[3]  ^= $rotl(($x[15] + $x[11]) & 0xffffffff, 7);
        $x[7]  ^= $rotl(($x[3]  + $x[15]) & 0xffffffff, 9);
        $x[11] ^= $rotl(($x[7]  + $x[3])  & 0xffffffff, 13);
        $x[15] ^= $rotl(($x[11] + $x[7])  & 0xffffffff, 18);

        // row rounds
        $x[1]  ^= $rotl(($x[0]  + $x[3])  & 0xffffffff, 7);
        $x[2]  ^= $rotl(($x[1]  + $x[0])  & 0xffffffff, 9);
        $x[3]  ^= $rotl(($x[2]  + $x[1])  & 0xffffffff, 13);
        $x[0]  ^= $rotl(($x[3]  + $x[2])  & 0xffffffff, 18);

        $x[6]  ^= $rotl(($x[5]  + $x[4])  & 0xffffffff, 7);
        $x[7]  ^= $rotl(($x[6]  + $x[5])  & 0xffffffff, 9);
        $x[4]  ^= $rotl(($x[7]  + $x[6])  & 0xffffffff, 13);
        $x[5]  ^= $rotl(($x[4]  + $x[7])  & 0xffffffff, 18);

        $x[11] ^= $rotl(($x[10] + $x[9])  & 0xffffffff, 7);
        $x[8]  ^= $rotl(($x[11] + $x[10]) & 0xffffffff, 9);
        $x[9]  ^= $rotl(($x[8]  + $x[11]) & 0xffffffff, 13);
        $x[10] ^= $rotl(($x[9]  + $x[8])  & 0xffffffff, 18);

        $x[12] ^= $rotl(($x[15] + $x[14]) & 0xffffffff, 7);
        $x[13] ^= $rotl(($x[12] + $x[15]) & 0xffffffff, 9);
        $x[14] ^= $rotl(($x[13] + $x[12]) & 0xffffffff, 13);
        $x[15] ^= $rotl(($x[14] + $x[13]) & 0xffffffff, 18);
    }

    $out = '';
    for ($i = 0; $i < 16; $i++) {
        $out .= pack('V', ($x[$i] + $j[$i]) & 0xffffffff);
    }
    return $out;
}

/**
 * XOR `$data` with the Salsa20 keystream (counter starts at 0).
 * Symmetric: same call encrypts or decrypts.
 */
function salsa20_xor(string $key, string $nonce, string $data): string
{
    $out = '';
    $len = strlen($data);
    $counter = 0;
    for ($offset = 0; $offset < $len; $offset += 64) {
        $ks = salsa20_block($key, $nonce, $counter);
        $chunk = substr($data, $offset, 64);
        $out .= $chunk ^ substr($ks, 0, strlen($chunk));
        $counter++;
    }
    return $out;
}
