# TUI rendering

The cockpit is drawn with raw ANSI escape codes, no libraries. This page covers
the box layout, redraw timing, and the glyph choices that keep the box aligned.

## Layout

Every content line is padded to a fixed inner width and wrapped in box borders.
`vwidth()` measures visible width while ignoring ANSI color codes, so padding
stays correct even on colored lines.

```php
const INNER_W = 45; // characters between the box borders

function pad(string $s): string
{
    $w = vwidth($s);
    return $w < INNER_W ? $s . str_repeat(' ', INNER_W - $w) : $s;
}
```

## Redraw timing

The loop decodes every packet at about 60 a second but repaints only about 15
times a second. Terminals cannot keep up with 60 frames a second, and decoding
every packet keeps the state and heartbeat cadence correct.

```php
if ($now - $lastDraw >= 0.066) { // ~15 fps
    echo render($state, $status);
    $lastDraw = $now;
}
```

To avoid flicker, each frame moves the cursor home and overwrites, rather than
clearing the whole screen.

## Mono safe glyphs

Some terminal fonts render certain glyphs at a different width, which pushes the
right border out of line. The shade block, the degree sign, and the ellipsis all
caused this. The fix is to use only glyphs the font renders at a uniform width:

| Avoid                       | Use instead                             |
| --------------------------- | --------------------------------------- |
| Shade block for empty bars  | Full block in grey (same glyph, darker) |
| Degree sign in temperatures | Plain `C`                               |
| Ellipsis                    | Three dots                              |

Bars are a solid track: a bright colored full block for the filled part and a
grey full block for the rest, so every cell is the same glyph.

## Status states

| State          | Shown when                              |
| -------------- | --------------------------------------- |
| Green LIVE     | A packet arrived within the last second |
| Red NO SIGNAL  | Data stopped after being connected      |
| Yellow WAITING | No data has arrived yet                 |

## Next Steps

- [Getting started](../02-development/01-getting-started.md)
- [Overview](01-overview.md)
