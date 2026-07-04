<?php

declare(strict_types=1);

/**
 * scrape-cars.php — build data/cars.json (car_id -> car info).
 *
 * The GT7 site ships its car database as a content-hashed JS asset, so the
 * filenames change on every rebuild and can't be hardcoded. This walks the
 * chain to find the current one:
 *
 *   carlist page  ->  index-*.js  ->  cars.us-*.js  ->  car data
 *
 * Run it once, or again whenever new cars are added. The TUI just reads the
 * resulting data/cars.json; it never scrapes.
 *
 * Usage:
 *   php tools/scrape-cars.php
 */

const HOST      = 'https://www.gran-turismo.com';
const CARLIST   = HOST . '/us/gt7/carlist/';
const ASSET_DIR = '/common/dist/gt7/carlist/assets/';
const OUT_FILE  = __DIR__ . '/../data/cars.json';

/** GET a URL and return the body, or exit on failure. */
function http_get(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);

    if ($body === false || $code >= 400) {
        fwrite(STDERR, "fetch failed ({$code} {$err}): {$url}\n");
        exit(1);
    }
    return (string) $body;
}

/** Find the first regex capture in $haystack, or exit. */
function must_match(string $pattern, string $haystack, string $what): string
{
    if (!preg_match($pattern, $haystack, $m)) {
        fwrite(STDERR, "could not find {$what}\n");
        exit(1);
    }
    return $m[1];
}

/**
 * Convert a minified JS object literal into valid JSON in one pass.
 * Handles unquoted identifier keys and both '…' and "…" string values
 * (the site uses single quotes when a name contains double quotes).
 */
function js_to_json(string $s): string
{
    $out = '';
    $n = strlen($s);
    for ($i = 0; $i < $n;) {
        $c = $s[$i];

        // string literal (" … ", ' … ' or ` … `) -> JSON double-quoted
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;
            $i++;
            $out .= '"';
            while ($i < $n) {
                $ch = $s[$i];
                if ($ch === '\\') {
                    $next = $s[$i + 1] ?? '';
                    // keep only escapes JSON understands; drop the rest (\' \` -> bare)
                    $out .= in_array($next, ['"', '\\', '/', 'b', 'f', 'n', 'r', 'u'], true)
                        ? '\\' . $next : $next;
                    $i += 2;
                    continue;
                }
                if ($ch === $quote) {
                    $i++;
                    break;
                }
                // a bare " inside a ' or ` string must be escaped for JSON
                $out .= $ch === '"' ? '\\"' : $ch;
                $i++;
            }
            $out .= '"';
            continue;
        }

        // bare identifier -> key (quote it), unless it's a JS keyword value
        if (ctype_alpha($c) || $c === '_' || $c === '$') {
            $j = $i;
            while ($j < $n && (ctype_alnum($s[$j]) || $s[$j] === '_' || $s[$j] === '$')) {
                $j++;
            }
            $word = substr($s, $i, $j - $i);
            $out .= in_array($word, ['true', 'false', 'null'], true) ? $word : '"' . $word . '"';
            $i = $j;
            continue;
        }

        // structural char, number, or whitespace
        $out .= $c;
        $i++;
    }
    return $out;
}

// ── 1. carlist page -> index-*.js ───────────────────────────────────────────
echo "Fetching car list page...\n";
$page = http_get(CARLIST);
$indexFile = must_match(
    '#/common/dist/gt7/carlist/assets/(index-[A-Za-z0-9_-]+\.js)#',
    $page,
    'index-*.js reference'
);
echo "  index asset: {$indexFile}\n";

// ── 2. index-*.js -> cars.us-*.js ───────────────────────────────────────────
echo "Fetching index asset...\n";
$index = http_get(HOST . ASSET_DIR . $indexFile);
$carsFile = must_match('#(cars\.us-[A-Za-z0-9_-]+\.js)#', $index, 'cars.us-*.js reference');
echo "  cars asset:  {$carsFile}\n";

// ── 3. fetch the car data module ────────────────────────────────────────────
echo "Fetching car data...\n";
$js = http_get(HOST . ASSET_DIR . $carsFile);

// ── 4. extract the object literal and turn it into JSON ─────────────────────
// Shape: const r={car102:{...},...,carN:{...}};export{r as Cars};
$eq = strpos($js, 'const r=');
$export = strrpos($js, ';export');
if ($eq === false || $export === false) {
    fwrite(STDERR, "unexpected cars module format\n");
    exit(1);
}
$objStart = $eq + strlen('const r=');
$literal = substr($js, $objStart, $export - $objStart);

$json = js_to_json($literal);

$cars = json_decode($json, true);
if (!is_array($cars)) {
    fwrite(STDERR, "failed to parse car data: " . json_last_error_msg() . "\n");
    exit(1);
}

// ── 5. reshape to car_id (int) -> trimmed record ────────────────────────────
$out = [];
foreach ($cars as $key => $car) {
    if (!preg_match('/(\d+)/', (string) $key, $m)) {
        continue;
    }
    $out[(int) $m[1]] = [
        'nameShort'      => $car['nameShort']      ?? '',
        'nameLong'       => $car['nameLong']       ?? '',
        'manufacturerId' => $car['manufacturerId'] ?? '',
        'carClass'       => $car['carClass']       ?? '',
    ];
}
ksort($out, SORT_NUMERIC);

// ── 6. write data/cars.json ─────────────────────────────────────────────────
@mkdir(dirname(OUT_FILE), 0755, true);
file_put_contents(
    OUT_FILE,
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

printf("\nWrote %d cars to %s\n", count($out), realpath(OUT_FILE) ?: OUT_FILE);
