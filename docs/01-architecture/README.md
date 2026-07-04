# Architecture

## Overview

This section explains how gt7php turns encrypted UDP packets from a PlayStation
into a live terminal cockpit. Start with the overview, then read each stage in
order.

## Table of Contents

### [1. Overview](01-overview.md)

The end to end data pipeline and where each piece of code lives.

### [2. Protocol](02-protocol.md)

The GT7 UDP interface: the two ports, the heartbeat, and the receive loop.

### [3. Decryption](03-decryption.md)

Salsa20, the nonce derivation from the packet, and the magic number check.

### [4. Telemetry format](04-telemetry-format.md)

The binary packet layout and the fields decoded from it.

### [5. TUI rendering](05-tui-rendering.md)

The ANSI cockpit, redraw timing, and the glyph choices that keep the box aligned.

## Related Documentation

- [Development](../02-development/README.md)
