# Protocol

GT7 exposes a "Simulator Interface" over UDP. This page covers the two ports,
the heartbeat that starts the stream, and the receive loop that keeps it alive.

## Ports

| Direction          | Port    | Purpose                                               |
| ------------------ | ------- | ----------------------------------------------------- |
| App to PlayStation | `33739` | Heartbeat that asks GT7 to start streaming            |
| PlayStation to App | `33740` | Encrypted telemetry stream, about 60 packets a second |

The app binds a local UDP socket to `0.0.0.0:33740` and sends the heartbeat from
it to the PlayStation on `33739`.

## The heartbeat

GT7 sends nothing until it receives a heartbeat. The heartbeat is a single
byte, the letter `A`.

```php
const SEND_PORT = 33739;
const RECV_PORT = 33740;

$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($sock, '0.0.0.0', RECV_PORT);
socket_sendto($sock, 'A', 1, 0, $ps5, SEND_PORT);
```

GT7 stops streaming after a while, so the heartbeat is resent every 100 packets
and again whenever the socket read times out.

## The receive loop

The socket uses a short receive timeout so the loop keeps running even when no
packets arrive. That lets the cockpit repaint a "no signal" state and re-arm the
stream.

```php
socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);

while (true) {
    $bytes = @socket_recvfrom($sock, $buf, 4096, 0, $from, $port);
    if ($bytes !== false && $bytes > 0) {
        // decrypt, parse, update state
        if (++$packets > 100) { $sendHeartbeat(); $packets = 0; }
    } else {
        $sendHeartbeat(); // timed out, re-arm
        $packets = 0;
    }
}
```

## macOS note

macOS refuses to send to the global broadcast address `255.255.255.255` and
returns "No route to host". Pass the PlayStation IP directly, or use the subnet
broadcast such as `192.168.1.255`.

## Next Steps

- [Decryption](03-decryption.md)
- [Telemetry format](04-telemetry-format.md)
