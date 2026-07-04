# Documentation

## Overview

gt7php reads Gran Turismo 7 telemetry from a PlayStation over UDP and shows it as
a live cockpit in the terminal. This documentation explains how the telemetry
pipeline works and how to run and extend the tool.

## Documentation Structure

### [01. Architecture](01-architecture/README.md)

How telemetry gets from the PlayStation to the screen: the UDP protocol, Salsa20
decryption, the packet format, and TUI rendering.

### [02. Development](02-development/README.md)

Running the tool, the helper scripts, and refreshing the car list.

## Quick Start

New here? Start with [Getting started](02-development/01-getting-started.md).

## Finding Information

- **How it works**: the [Architecture](01-architecture/README.md) section
- **Running it**: the [Development](02-development/README.md) section
- **Car names**: [Car names](02-development/03-car-names.md)
